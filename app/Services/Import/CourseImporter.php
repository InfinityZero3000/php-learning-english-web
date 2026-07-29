<?php

namespace App\Services\Import;

use App\Models\AdminImportRun;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CourseImporter extends AbstractLexiLingoImporter
{
    public function entity(): string
    {
        return 'courses';
    }

    public function import(int $limit, bool $dryRun = false, bool $reset = false, ?int $cursor = null): ImportResult
    {
        $this->requireApplyEnabled($dryRun);
        $offset = $this->startingCursor($reset, $cursor);

        $payload = $this->client->partner()
            ->get('/api/v1/integrations/courses', [
                'page' => intdiv($offset, $limit) + 1,
                'page_size' => $limit,
            ])
            ->throw()
            ->json();

        $items = $this->stagedItems($payload);

        $processed = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $errors = $this->validator->validate('Course', $item);

            if ($errors !== []) {
                $skipped++;
                $this->logWarning('Skipped invalid course payload', [
                    'external_id' => $item['id'] ?? null,
                    'errors' => $errors,
                ]);

                if (! $dryRun) {
                    $this->archiveFailure($item['id'] ?? null, $item, $errors);
                }

                continue;
            }

            $externalId = (string) $item['id'];

            $detail = $this->client->partner()
                ->get("/api/v1/integrations/courses/{$externalId}")
                ->throw()
                ->json();

            $detailData = is_array($detail) ? ($detail['data'] ?? $detail) : [];
            $units = $detailData['units'] ?? [];

            try {
                DB::transaction(function () use ($item, $externalId, $units, $dryRun) {
                    $this->importCourseWithUnits($item, $externalId, $units, $dryRun);
                });
                $processed++;
            } catch (Throwable $e) {
                $skipped++;
                $this->logWarning('Course import failed, transaction rolled back', [
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);

                if (! $dryRun) {
                    $this->archiveFailure($externalId, $item, [$e->getMessage()]);
                }
            }
        }

        $nextCursor = $offset + count($items);

        if (! $dryRun) {
            $this->advanceCheckpoint($nextCursor, $reset);
        }

        $this->logInfo('Course import page complete', [
            'offset' => $offset,
            'processed' => $processed,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        return new ImportResult($processed, $skipped, $nextCursor);
    }

    public function stage(AdminImportRun $run, StagedImportClassifier $classifier): ImportResult
    {
        $offset = (int) $run->starting_cursor;
        $payload = $this->client->partner()->get('/api/v1/integrations/courses', [
            'page' => intdiv($offset, $run->requested_limit) + 1,
            'page_size' => $run->requested_limit,
        ])->throw()->json();
        $items = $this->stagedItems($payload, min(100, (int) $run->requested_limit));
        $this->requireValidSchema('PaginatedCourseResponse', [...$payload, 'data' => []]);
        $invalid = 0;

        foreach ($items as $item) {
            $item = is_array($item) ? $item : [];
            $externalId = isset($item['id']) ? (string) $item['id'] : null;
            $errors = $this->validator->validate('Course', $item);
            $detail = $externalId && $errors === []
                ? $this->client->partner()->get("/api/v1/integrations/courses/{$externalId}")->throw()->json()
                : [];
            $detailData = is_array($detail) ? ($detail['data'] ?? $detail) : [];
            if ($errors === []) {
                if (! is_array($detailData) || ! is_array($detailData['units'] ?? null)
                    || ! array_is_list($detailData['units'])) {
                    throw new \RuntimeException('LexiLingo returned an invalid course detail.');
                }
                $this->requireValidSchema('CourseDetailResponse', [
                    ...$detail,
                    'data' => [...$detailData, 'units' => []],
                ]);
            }
            $courseSlug = isset($item['title'], $externalId)
                ? Str::slug($item['title']).'-'.substr(md5($externalId), 0, 8)
                : null;
            $candidate = [
                'category_external_id' => isset($item['category_id']) ? (string) $item['category_id'] : null,
                'level' => $item['level'] ?? null,
                'title' => $item['title'] ?? null,
                'slug' => $courseSlug,
                'description' => $item['description'] ?? null,
                'language' => $item['language'] ?? null,
                'thumbnail_url' => $item['thumbnail_url'] ?? null,
                'estimated_duration' => $item['estimated_duration'] ?? null,
                'total_xp' => $item['total_xp'] ?? 0,
            ];
            $existing = $externalId ? Course::query()
                ->where('source_system', 'lexilingo')->where('external_id', $externalId)->first() : null;
            $natural = $courseSlug ? Course::query()->where('slug', $courseSlug)->get()->all() : [];
            $courseItem = $this->stageItem(
                $run, $classifier, 'course', $externalId, $courseSlug, $candidate,
                $existing, $natural, $errors,
                dependencies: $candidate['category_external_id'] ? [[
                    'entity' => 'category', 'external_id' => $candidate['category_external_id'],
                ]] : [],
            );
            $invalid += $courseItem->classification === 'invalid' ? 1 : 0;

            foreach ((array) ($detailData['units'] ?? []) as $unit) {
                $unit = is_array($unit) ? $unit : [];
                $unitExternalId = isset($unit['id']) ? (string) $unit['id'] : null;
                $unitCandidate = [
                    'course_external_id' => $externalId,
                    'title' => $unit['title'] ?? null,
                    'description' => $unit['description'] ?? null,
                    'sort_order' => $unit['order_index'] ?? null,
                    'icon_url' => $unit['icon_url'] ?? null,
                    'background_color' => $unit['background_color'] ?? null,
                ];
                $unitExisting = $unitExternalId ? Unit::query()
                    ->where('source_system', 'lexilingo')->where('external_id', $unitExternalId)->first() : null;
                $unitItem = $this->stageItem(
                    $run, $classifier, 'unit', $unitExternalId,
                    "{$externalId}:".($unit['order_index'] ?? '?'), $unitCandidate,
                    $unitExisting, validationErrors: $this->validator->validate('Unit', $unit),
                    parent: $courseItem,
                    dependencies: [['entity' => 'course', 'external_id' => $externalId]],
                );
                $invalid += $unitItem->classification === 'invalid' ? 1 : 0;

                foreach ((array) ($unit['lessons'] ?? []) as $lesson) {
                    $lesson = is_array($lesson) ? $lesson : [];
                    $lessonExternalId = isset($lesson['id']) ? (string) $lesson['id'] : null;
                    $lessonSlug = isset($lesson['title'], $lessonExternalId)
                        ? Str::slug($lesson['title']).'-'.substr(md5($lessonExternalId), 0, 8)
                        : null;
                    $lessonExisting = $lessonExternalId ? Lesson::query()
                        ->where('source_system', 'lexilingo')->where('external_id', $lessonExternalId)->first() : null;
                    $preserved = array_intersect_key($lessonExisting?->source_snapshot ?? [], array_flip([
                        'content', 'estimated_minutes', 'pass_threshold', 'quiz_tree',
                    ]));
                    $lessonCandidate = [
                        'unit_external_id' => $unitExternalId,
                        'title' => $lesson['title'] ?? null,
                        'slug' => $lessonSlug,
                        'sort_order' => $lesson['order_index'] ?? null,
                        'lesson_type' => $lesson['lesson_type'] ?? null,
                        'xp_reward' => $lesson['xp_reward'] ?? 0,
                        ...$preserved,
                    ];
                    $lessonItem = $this->stageItem(
                        $run, $classifier, 'lesson', $lessonExternalId, $lessonSlug,
                        $lessonCandidate, $lessonExisting,
                        validationErrors: $this->validator->validate('LessonOutline', $lesson),
                        parent: $unitItem,
                        dependencies: [['entity' => 'unit', 'external_id' => $unitExternalId]],
                    );
                    $invalid += $lessonItem->classification === 'invalid' ? 1 : 0;
                }
            }
        }

        return new ImportResult(count($items), $invalid, $offset + count($items));
    }

    /**
     * Upsert one course plus its nested units/lessons. Runs inside the
     * caller's DB::transaction so a failure anywhere here rolls back the
     * whole course (units and lessons included), not just the failing row.
     */
    private function importCourseWithUnits(array $item, string $externalId, array $units, bool $dryRun): void
    {
        $courseSlug = Str::slug($item['title']).'-'.substr(md5($externalId), 0, 8);
        $levelId = Level::query()->where('slug', $item['level'])->value('id');

        $categoryExternalId = isset($item['category_id']) ? (string) $item['category_id'] : null;
        $categoryId = $categoryExternalId
            ? CourseCategory::query()->where('source_system', 'lexilingo')->where('external_id', $categoryExternalId)->value('id')
            : null;
        $courseAttributes = [
            'level_id' => $levelId,
            'category_id' => $categoryId,
            'category_external_id' => $categoryExternalId,
            'level' => $item['level'],
            'title' => $item['title'],
            'slug' => $courseSlug,
            'description' => $item['description'] ?? null,
            'language' => $item['language'],
            'thumbnail_url' => $item['thumbnail_url'] ?? null,
            'estimated_duration' => $item['estimated_duration'] ?? null,
            'total_xp' => $item['total_xp'] ?? 0,
        ];
        $course = $dryRun ? null : Course::syncFromSource('course', 'lexilingo', $externalId, $courseAttributes)[0];

        foreach ($units as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $unitErrors = $this->validator->validate('Unit', $unit);

            if ($unitErrors !== []) {
                $this->logWarning('Skipped invalid unit payload', [
                    'external_id' => $unit['id'] ?? null,
                    'course_external_id' => $externalId,
                    'errors' => $unitErrors,
                ]);

                if (! $dryRun) {
                    $this->archiveFailure($unit['id'] ?? null, $unit, $unitErrors);
                }

                continue;
            }

            if ($dryRun) {
                continue;
            }

            $unitModel = Unit::syncFromSource('unit', 'lexilingo', (string) $unit['id'], [
                'course_id' => $course->id,
                'course_external_id' => $externalId,
                'title' => $unit['title'],
                'description' => $unit['description'] ?? null,
                'sort_order' => $unit['order_index'],
                'icon_url' => $unit['icon_url'] ?? null,
                'background_color' => $unit['background_color'] ?? null,
            ])[0];

            foreach ($unit['lessons'] as $lesson) {
                $lessonExternalId = (string) $lesson['id'];
                $lessonSlug = Str::slug($lesson['title']).'-'.substr(md5($lessonExternalId), 0, 8);

                $existingSnapshot = Lesson::query()
                    ->where('source_system', 'lexilingo')
                    ->where('external_id', $lessonExternalId)
                    ->value('source_snapshot');
                $contentAttributes = array_intersect_key(
                    is_array($existingSnapshot) ? $existingSnapshot : json_decode($existingSnapshot ?: '[]', true, flags: JSON_THROW_ON_ERROR),
                    array_flip(['content', 'estimated_minutes', 'pass_threshold', 'quiz_tree']),
                );

                Lesson::syncFromSource('lesson', 'lexilingo', $lessonExternalId, [
                    'course_id' => $course->id,
                    'unit_id' => $unitModel->id,
                    'unit_external_id' => (string) $unit['id'],
                    'title' => $lesson['title'],
                    'slug' => $lessonSlug,
                    'sort_order' => $lesson['order_index'],
                    'lesson_type' => $lesson['lesson_type'],
                    'xp_reward' => $lesson['xp_reward'],
                    ...$contentAttributes,
                ]);
            }
        }
    }
}
