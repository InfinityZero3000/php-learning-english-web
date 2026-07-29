<?php

namespace App\Services;

use App\Models\AdminImportLock;
use App\Models\AdminImportRun;
use App\Models\LexiLingoImportCheckpoint;
use App\Models\OperationsAudit;
use App\Services\Import\CategoryImporter;
use App\Services\Import\CourseImporter;
use App\Services\Import\LessonContentImporter;
use App\Services\Import\StagedImportClassifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminImportRunner
{
    public function __construct(private readonly StagedImportClassifier $classifier) {}

    public function run(AdminImportRun $run): void
    {
        if (! config('features.lexilingo_import')) {
            throw new RuntimeException('LexiLingo import is disabled.');
        }

        $claimed = DB::transaction(function () use ($run): bool {
            $lock = AdminImportLock::query()->lockForUpdate()->findOrFail($run->entity);
            $run = AdminImportRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'fetching' || (int) $lock->current_run_id !== $run->id) {
                return false;
            }
            $run->items()->delete();
            $run->update(['status' => 'validating', 'error_code' => null, 'error_message' => null]);

            return true;
        });
        if (! $claimed) {
            return;
        }

        $importer = match ($run->entity) {
            'categories' => app(CategoryImporter::class),
            'courses' => app(CourseImporter::class),
            'lessons' => app(LessonContentImporter::class),
            'vocabulary' => app(LexiLingoVocabularySync::class),
            default => throw new RuntimeException('Unsupported import entity.'),
        };
        if (! method_exists($importer, 'stage')) {
            throw new RuntimeException("Staging is not implemented for {$run->entity}.");
        }
        $result = $importer->stage($run, $this->classifier);

        DB::transaction(function () use ($run, $result): void {
            $lock = AdminImportLock::query()->lockForUpdate()->findOrFail($run->entity);
            $run = AdminImportRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'validating' || (int) $lock->current_run_id !== $run->id) {
                return;
            }
            $run->update([
                'status' => 'review_ready',
                'processed' => $result->processed,
                'skipped' => $result->skipped,
                'result_cursor' => $result->nextCursor,
            ]);
            OperationsAudit::create([
                'actor_id' => $run->actor_id,
                'action' => 'content_import.staged',
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
            $lock->update(['current_run_id' => null, 'locked_at' => null]);
        });
    }

    public function runCli(string $entity, int $limit, bool $reset = false, ?int $cursor = null): AdminImportRun
    {
        $run = DB::transaction(function () use ($entity, $limit, $reset, $cursor): AdminImportRun {
            $lock = AdminImportLock::query()->lockForUpdate()->firstOrCreate(['entity' => $entity]);
            if ($lock->current_run_id !== null) {
                throw new RuntimeException("An import for {$entity} is already running.");
            }
            $startingCursor = $cursor ?? ($reset ? 0 : (int) (LexiLingoImportCheckpoint::query()
                ->where('entity', $entity)->value('cursor') ?? 0));
            $run = AdminImportRun::create([
                'request_id' => (string) Str::uuid(),
                'entity' => $entity,
                'payload_fingerprint' => hash('sha256', json_encode([$entity, $limit, $reset, $startingCursor], JSON_THROW_ON_ERROR)),
                'initiator_type' => 'cli',
                'status' => 'fetching',
                'requested_limit' => $limit,
                'reset' => $reset,
                'starting_cursor' => $startingCursor,
            ]);
            $lock->update(['current_run_id' => $run->id, 'locked_at' => now('UTC')]);

            return $run;
        });

        try {
            $this->run($run);
        } catch (\Throwable $error) {
            $this->fail($run, $error);

            throw $error;
        }

        return $run->fresh();
    }

    public function fail(AdminImportRun $run, \Throwable $error): void
    {
        DB::transaction(function () use ($run): void {
            $lock = AdminImportLock::query()->lockForUpdate()->findOrFail($run->entity);
            $run = AdminImportRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! in_array($run->status, ['fetching', 'validating'], true)
                || (int) $lock->current_run_id !== $run->id) {
                return;
            }
            $run->update([
                'status' => 'validation_failed',
                'error_code' => 'provider_unavailable',
                'error_message' => mb_substr('LexiLingo import failed. Retry later.', 0, 500),
            ]);
            $lock->update(['current_run_id' => null, 'locked_at' => null]);
        });
    }
}
