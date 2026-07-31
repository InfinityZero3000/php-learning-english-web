<?php

namespace App\Services\Import;

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
        $offset = $this->startingCursor($reset, $cursor);

        $payload = $this->client->partner()
            ->get('/api/v1/integrations/courses', [
                'page' => intdiv($offset, $limit) + 1,
                'page_size' => $limit,
            ])
            ->throw()
            ->json();

        $items = $this->items($payload);

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
                    $this->stageItem($externalId, $item, null, 'invalid', [$e->getMessage()]);
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

    /**
     * Upsert one course plus its nested units/lessons. Runs inside the
     * caller's DB::transaction so a failure anywhere here rolls back the
     * whole course (units and lessons included), not just the failing row.
     *
     * TODO(#45): stage unit/lesson-level changes once nested dependency
     * review is in scope — today only the top-level course is staged.
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

        $course = null;
        if (! $dryRun) {
            $existing = Course::query()->where('source_system', 'lexilingo')
                ->where('external_id', $externalId)->first();
            if (! $this->stageOnly) {
                $course = Course::syncFromSource('course', 'lexilingo', $externalId, $courseAttributes)[0];
            }
            $this->stageChange($externalId, $item, $existing, $courseAttributes);
        }

        // ponytail: nested unit/lesson writes need the parent course row. A staged
        // run has none, so the whole subtree waits for apply. Nested staged review
        // (TODO #45) is what unlocks per-unit/per-lesson approval.
        if ($course === null) {
            return;
        }

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
