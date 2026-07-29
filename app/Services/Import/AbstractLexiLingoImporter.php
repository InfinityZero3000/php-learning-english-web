<?php

namespace App\Services\Import;

use App\Models\AdminImportItem;
use App\Models\AdminImportRun;
use App\Models\LexiLingoImportCheckpoint;
use App\Models\LexiLingoImportFailure;
use App\Support\CatalogFingerprint;
use App\Support\LexiLingoClient;
use App\Support\LexiLingoSchemaValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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

    protected function stagedItems(mixed $payload, ?int $maxItems = null): array
    {
        if (! is_array($payload)) {
            throw new RuntimeException('LexiLingo returned an invalid batch.');
        }

        $items = array_key_exists('data', $payload) ? $payload['data'] : $payload;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException('LexiLingo returned an invalid batch.');
        }
        if ($maxItems !== null && count($items) > $maxItems) {
            throw new RuntimeException('LexiLingo returned an oversized batch.');
        }

        return $items;
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

    protected function stageItem(
        AdminImportRun $run,
        StagedImportClassifier $classifier,
        string $entity,
        ?string $externalId,
        ?string $naturalKey,
        array $candidate,
        ?Model $existing = null,
        array $naturalKeyMatches = [],
        array $validationErrors = [],
        ?AdminImportItem $parent = null,
        array $dependencies = [],
    ): AdminImportItem {
        if ($externalId !== null && $staged = AdminImportItem::query()
            ->where('admin_import_run_id', $run->id)
            ->where('entity', $entity)
            ->where('source_system', 'lexilingo')
            ->where('external_id', $externalId)
            ->first()) {
            if ($staged->normalized_fingerprint !== CatalogFingerprint::make($entity, $candidate)) {
                throw new RuntimeException("LexiLingo returned conflicting {$entity} identities.");
            }

            return $staged;
        }

        return AdminImportItem::create([
            'admin_import_run_id' => $run->id,
            'parent_item_id' => $parent?->id,
            'entity' => $entity,
            'source_system' => 'lexilingo',
            'external_id' => $externalId,
            'natural_key' => $naturalKey,
            'candidate_payload' => $candidate,
            'dependencies' => $dependencies,
            ...$classifier->classify(
                $entity,
                $candidate,
                $existing?->only([
                    'id', 'catalog_revision', 'source_fingerprint',
                    'source_snapshot', 'local_override_at',
                ]),
                $validationErrors,
                $naturalKeyMatches,
            ),
        ]);
    }

    protected function requireApplyEnabled(bool $dryRun): void
    {
        if (! $dryRun && ! config('features.lexilingo_import_apply')) {
            throw new RuntimeException('LexiLingo import apply is disabled.');
        }
    }

    protected function requireValidSchema(string $schema, array $payload): void
    {
        if ($this->validator->validate($schema, $payload) !== []) {
            throw new RuntimeException('LexiLingo response schema validation failed.');
        }
    }
}
