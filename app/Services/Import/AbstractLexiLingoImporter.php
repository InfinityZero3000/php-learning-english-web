<?php

namespace App\Services\Import;

use App\Models\LexiLingoImportCheckpoint;
use App\Models\LexiLingoImportFailure;
use App\Support\LexiLingoClient;
use App\Support\LexiLingoSchemaValidator;
use Illuminate\Support\Facades\Log;

abstract class AbstractLexiLingoImporter
{
    public function __construct(
        protected readonly LexiLingoClient $client,
        protected readonly LexiLingoSchemaValidator $validator,
    ) {}

    /**
     * Checkpoint/failure-log key: 'categories', 'courses', 'vocabulary'.
     */
    abstract public function entity(): string;

    /**
     * Fetch, validate and (unless dry-run) persist one page starting at the
     * current checkpoint (or offset 0 when $reset is true).
     */
    abstract public function import(int $limit, bool $dryRun = false, bool $reset = false): ImportResult;

    protected function startingCursor(bool $reset): int
    {
        if ($reset) {
            return 0;
        }

        // Read-only lookup: does not create a checkpoint row, so a dry-run
        // (which never calls advanceCheckpoint()) has zero DB side effects.
        return (int) (LexiLingoImportCheckpoint::where('entity', $this->entity())->value('cursor') ?? 0);
    }

    protected function checkpoint(): LexiLingoImportCheckpoint
    {
        return LexiLingoImportCheckpoint::firstOrCreate(
            ['entity' => $this->entity()],
            ['cursor' => 0]
        );
    }

    protected function advanceCheckpoint(int $cursor): void
    {
        $checkpoint = $this->checkpoint();
        $checkpoint->cursor = $cursor;
        $checkpoint->last_synced_at = now();
        $checkpoint->save();
    }

    protected function archiveFailure(?string $externalId, array $payload, array $errors): void
    {
        LexiLingoImportFailure::create([
            'entity' => $this->entity(),
            'external_id' => $externalId,
            'payload' => $payload,
            'errors' => $errors,
        ]);
    }

    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning($message, ['entity' => $this->entity(), ...$context]);
    }

    protected function logInfo(string $message, array $context = []): void
    {
        Log::info($message, ['entity' => $this->entity(), ...$context]);
    }
}
