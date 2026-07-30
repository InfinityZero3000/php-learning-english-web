<?php

namespace App\Services\Import;

use App\Models\Course;
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

        [$items, $nextCursor] = $this->fetchPageSlice(
            '/api/v1/integrations/courses',
            $offset,
            $limit
        );

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
                    $this->archiveFailure($item['id'] ?? null, $item, $errors, ImportErrorCode::PayloadInvalid);
                }

                continue;
            }

            $externalId = (string) $item['id'];

            // Course detail fetch + nested write are one error boundary: a
            // provider failure or DB conflict on this course must not stop
            // the remaining courses in the page from being imported.
            try {
                $detail = $this->client->partner()
                    ->get("/api/v1/integrations/courses/{$externalId}")
                    ->throw()
                    ->json();

                $detailData = is_array($detail) ? ($detail['data'] ?? $detail) : [];
                $units = $detailData['units'] ?? [];

                DB::transaction(function () use ($item, $externalId, $units, $dryRun) {
                    $this->importCourseWithUnits($item, $externalId, $units, $dryRun);
                });
                $processed++;
            } catch (Throwable $e) {
                $skipped++;
                $errorCode = $this->classifyImportError($e);
                $this->logWarning('Course import failed, skipping this course', [
                    'external_id' => $externalId,
                    'error_code' => $errorCode->value,
                    'error' => $e->getMessage(),
                ]);

                if (! $dryRun) {
                    $this->archiveFailure($externalId, $item, [$e->getMessage()], $errorCode);
                }
            }
        }

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
     */
    private function importCourseWithUnits(array $item, string $externalId, array $units, bool $dryRun): void
    {
        $courseSlug = Str::slug($item['title']).'-'.substr(md5($externalId), 0, 8);
        $levelId = Level::query()->where('slug', $item['level'])->value('id');

        $course = null;

        if (! $dryRun) {
            $course = Course::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'level_id' => $levelId,
                    'title' => $item['title'],
                    'slug' => $courseSlug,
                    'description' => $item['description'] ?? null,
                    'language' => $item['language'],
                    'thumbnail_url' => $item['thumbnail_url'] ?? null,
                    'estimated_duration' => $item['estimated_duration'] ?? null,
                    'total_xp' => $item['total_xp'] ?? 0,
                ]
            );
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
                    $this->archiveFailure($unit['id'] ?? null, $unit, $unitErrors, ImportErrorCode::PayloadInvalid);
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

                $lessonModel = Lesson::updateOrCreate(
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

                $contentResponse = $this->client->partner()
                    ->get("/api/v1/integrations/lessons/{$lessonExternalId}/content");
                if ($contentResponse->successful()) {
                    $content = $contentResponse->json('data') ?? $contentResponse->json();
                    if (is_array($content) && $content !== []) {
                        $lessonModel->update([
                            'content' => $content['content'] ?? null,
                            'estimated_minutes' => $content['estimated_minutes'] ?? null,
                            'pass_threshold' => $content['pass_threshold'] ?? null,
                        ]);
                    }
                }
            }
        }
    }
}
