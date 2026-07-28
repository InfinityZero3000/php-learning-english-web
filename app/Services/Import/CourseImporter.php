<?php

namespace App\Services\Import;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Topic;
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

    public function import(int $limit, bool $dryRun = false, bool $reset = false): ImportResult
    {
        $offset = $this->startingCursor($reset);

        $payload = $this->client->backend()
            ->get('/api/v1/courses', ['limit' => $limit, 'offset' => $offset])
            ->throw()
            ->json();

        $items = is_array($payload) ? $payload : ($payload['data'] ?? []);

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

            $detail = $this->client->backend()
                ->get("/api/v1/courses/{$externalId}")
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
            $this->advanceCheckpoint($nextCursor);
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
     */
    private function importCourseWithUnits(array $item, string $externalId, array $units, bool $dryRun): void
    {
        $courseSlug = Str::slug($item['title']).'-'.substr(md5($externalId), 0, 8);
        $levelId = Level::query()->where('slug', $item['level'])->value('id');

        // Resolve category_id from payload (if category_id is provided)
        $categoryId = null;
        if (! empty($item['category_id'])) {
            $categoryId = CourseCategory::query()
                ->where('external_id', (string) $item['category_id'])
                ->orWhere('id', $item['category_id'])
                ->value('id');
        }

        // Resolve topic IDs from payload tags (if present)
        $topicIds = [];
        if (! empty($item['tags']) && is_array($item['tags'])) {
            $topicIds = Topic::query()
                ->whereIn('external_id', $item['tags'])
                ->orWhereIn('slug', array_map(fn ($tag) => Str::slug((string) $tag), $item['tags']))
                ->pluck('id')
                ->toArray();
        }

        $course = null;

        if (! $dryRun) {
            $course = Course::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'level_id' => $levelId,
                    'category_id' => $categoryId,
                    'title' => $item['title'],
                    'slug' => $courseSlug,
                    'description' => $item['description'] ?? null,
                    'language' => $item['language'],
                    'thumbnail_url' => $item['thumbnail_url'] ?? null,
                    'estimated_duration' => $item['estimated_duration'] ?? null,
                    'total_xp' => $item['total_xp'] ?? 0,
                ]
            );

            if ($topicIds !== []) {
                $course->topics()->syncWithoutDetaching($topicIds);
            }
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

            $unitModel = Unit::updateOrCreate(
                ['external_id' => (string) $unit['id']],
                [
                    'course_id' => $course->id,
                    'title' => $unit['title'],
                    'description' => $unit['description'] ?? null,
                    'sort_order' => $unit['order_index'],
                    'icon_url' => $unit['icon_url'] ?? null,
                    'background_color' => $unit['background_color'] ?? null,
                ]
            );

            foreach ($unit['lessons'] as $lesson) {
                $lessonExternalId = (string) $lesson['id'];
                $lessonSlug = Str::slug($lesson['title']).'-'.substr(md5($lessonExternalId), 0, 8);

                Lesson::updateOrCreate(
                    ['external_id' => $lessonExternalId],
                    [
                        'course_id' => $course->id,
                        'unit_id' => $unitModel->id,
                        'title' => $lesson['title'],
                        'slug' => $lessonSlug,
                        'sort_order' => $lesson['order_index'],
                        'lesson_type' => $lesson['lesson_type'],
                        'xp_reward' => $lesson['xp_reward'],
                    ]
                );
            }
        }
    }
}
