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
    abstract public function import(int $limit, bool $dryRun = false, bool $reset = false, ?int $cursor = null): ImportResult;

    protected function items(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return is_array($payload['data'] ?? null)
            ? $payload['data']
            : (array_is_list($payload) ? $payload : []);
    }

    protected function startingCursor(bool $reset, ?int $cursor = null): int
    {
        if ($cursor !== null) {
            return max(0, $cursor);
        }

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

    protected function advanceCheckpoint(int $cursor, bool $replace = false): void
    {
        $checkpoint = $this->checkpoint();
        $checkpoint->cursor = $replace ? $cursor : max((int) $checkpoint->cursor, $cursor);
        $checkpoint->last_synced_at = now();
        $checkpoint->save();
    }

    protected function archiveFailure(?string $externalId, array $payload, array $errors): void
    {
        $safePayload = array_intersect_key($payload, array_flip([
            'id', 'slug', 'name', 'title', 'word', 'language', 'level', 'part_of_speech',
        ]));
        $safeErrors = array_map(
            fn (mixed $error): string => mb_substr(is_scalar($error) ? (string) $error : 'Invalid provider payload.', 0, 500),
            array_slice($errors, 0, 20),
        );

        LexiLingoImportFailure::updateOrCreate(
            ['entity' => $this->entity(), 'external_id' => $externalId],
            ['payload' => $safePayload, 'errors' => $safeErrors],
        );
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
