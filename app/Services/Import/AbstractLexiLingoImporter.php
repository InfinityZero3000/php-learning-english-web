<?php

namespace App\Services\Import;

use App\Models\LexiLingoImportCheckpoint;
use App\Models\LexiLingoImportFailure;
use App\Models\StagedItem;
use App\Support\LexiLingoClient;
use App\Support\LexiLingoSchemaValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

abstract class AbstractLexiLingoImporter
{
    /**
     * Allowlisted fields copied into a StagedItem's incoming/existing
     * snapshot for the admin review diff. Deliberately separate from (and
     * wider than) archiveFailure()'s allowlist, but still a strict
     * intersection — anything not named here can never leak into a
     * snapshot regardless of what the provider sends. Nested
     * units/lessons arrays and raw lesson HTML content are intentionally
     * excluded (Course->Unit->Lesson staged review is out of scope here).
     */
    private const STAGED_SNAPSHOT_FIELDS = [
        'id', 'external_id', 'slug', 'name', 'title', 'word', 'description', 'definition',
        'translation', 'pronunciation', 'language', 'level', 'part_of_speech',
        'difficulty_level', 'tags', 'color', 'icon', 'status', 'thumbnail_url', 'estimated_duration',
    ];

    protected ?int $runId = null;

    protected bool $stageOnly = false;

    public function __construct(
        protected readonly LexiLingoClient $client,
        protected readonly LexiLingoSchemaValidator $validator,
    ) {}

    /**
     * Checkpoint/failure-log key: 'categories', 'courses', 'vocabulary'.
     */
    abstract public function entity(): string;

    /**
     * Associate this importer instance with an admin_import_run so
     * stageItem() records staged-review rows. No-op (never called) for
     * direct/CLI/dry-run usage, which keeps the existing direct-apply
     * import path byte-for-byte unchanged.
     */
    public function forRun(?int $runId): static
    {
        $this->runId = $runId;

        return $this;
    }

    /**
     * When true, importers that check this flag (currently only
     * CategoryImporter — see issue #45) skip their actual write and only
     * record a StagedItem for later super-admin approval/apply. Importers
     * that never check the flag are unaffected, so their direct-apply
     * behavior stays exactly as it was before this flag existed.
     */
    public function stageOnly(bool $flag = true): static
    {
        $this->stageOnly = $flag;

        return $this;
    }

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

    /**
     * Record a read-only staged-review row alongside the (unmodified)
     * direct-apply write. No-op when this importer has no run context
     * (forRun() never called), so direct/CLI/dry-run/test usage is
     * unaffected.
     *
     * TODO(#44 follow-up/#45): compute 'conflict' (local edits diverge
     * from provider) and 'unchanged' (shallow-equal payload) once a real
     * field-level comparator exists — today only 'new'/'update'/'invalid'
     * are produced.
     */
    protected function stageItem(?string $externalId, array $incoming, ?Model $existing, string $classification, array $errors = []): void
    {
        if ($this->runId === null) {
            return;
        }

        $allowlist = array_flip(self::STAGED_SNAPSHOT_FIELDS);
        $safeErrors = array_map(
            fn (mixed $error): string => mb_substr(is_scalar($error) ? (string) $error : 'Invalid provider payload.', 0, 500),
            array_slice($errors, 0, 20),
        );

        StagedItem::create([
            'admin_import_run_id' => $this->runId,
            'entity' => $this->entity(),
            'external_id' => $externalId,
            'classification' => $classification,
            'incoming_snapshot' => array_intersect_key($incoming, $allowlist),
            'existing_snapshot' => $existing ? array_intersect_key($existing->getAttributes(), $allowlist) : null,
            // Captured so a later apply can detect the target row changed
            // between staging and approval and refuse to overwrite it.
            'existing_revision' => $existing?->updated_at?->toISOString(),
            'errors' => $safeErrors === [] ? null : $safeErrors,
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
