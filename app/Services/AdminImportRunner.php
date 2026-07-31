<?php

namespace App\Services;

use App\Models\AdminImportLock;
use App\Models\AdminImportRun;
use App\Models\OperationsAudit;
use App\Services\Import\CategoryImporter;
use App\Services\Import\CourseImporter;
use App\Services\Import\LessonContentImporter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminImportRunner
{
    public function run(AdminImportRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $lock = AdminImportLock::query()->lockForUpdate()->findOrFail($run->entity);
            if ((int) $lock->current_run_id !== $run->id) {
                throw new RuntimeException('Import lock is no longer owned by this run.');
            }
            $run->update(['status' => 'running', 'error_code' => null, 'error_message' => null]);
        });

        $importer = match ($run->entity) {
            'categories' => app(CategoryImporter::class),
            'courses' => app(CourseImporter::class),
            'vocabulary' => app(LexiLingoVocabularySync::class),
            'lessons' => app(LessonContentImporter::class),
            default => throw new RuntimeException('Unsupported import entity.'),
        };
        $importer->forRun($run->id);
        $importer->stageOnly($run->entity === 'categories');
        $result = $importer->import(
            limit: $run->requested_limit,
            reset: $run->reset,
            cursor: $run->starting_cursor,
        );

        DB::transaction(function () use ($run, $result): void {
            // TODO(#45): introduce applying/applied states once staged
            // items gate the write path — today the write already
            // happened above, so 'review-ready' just means "browse what
            // was written."
            $run->update([
                'status' => 'review-ready',
                'processed' => $result->processed,
                'skipped' => $result->skipped,
                'result_cursor' => $result->nextCursor,
            ]);
            OperationsAudit::create([
                'actor_id' => $run->actor_id,
                'action' => 'content_import.succeeded',
                'target_type' => 'admin_import_run',
                'target_id' => (string) $run->id,
                'request_id' => $run->request_id,
                'context' => ['entity' => $run->entity],
                'after_state' => [
                    'processed' => $result->processed,
                    'skipped' => $result->skipped,
                    'cursor' => $result->nextCursor,
                ],
                'occurred_at' => now('UTC'),
            ]);
            AdminImportLock::query()->whereKey($run->entity)
                ->where('current_run_id', $run->id)
                ->update(['current_run_id' => null, 'locked_at' => null]);
        });
    }

    public function fail(AdminImportRun $run, \Throwable $error): void
    {
        DB::transaction(function () use ($run): void {
            $run->update([
                'status' => 'failed',
                'error_code' => 'provider_unavailable',
                'error_message' => mb_substr('LexiLingo import failed. Retry later.', 0, 500),
            ]);
            AdminImportLock::query()->whereKey($run->entity)
                ->where('current_run_id', $run->id)
                ->update(['current_run_id' => null, 'locked_at' => null]);
        });
    }
}
