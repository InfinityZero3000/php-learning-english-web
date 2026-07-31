<?php

namespace App\Services\Import;

use App\Models\LexiLingoImportCheckpoint;
use App\Models\LexiLingoImportFailure;
use App\Models\StagedItem;
use App\Support\CatalogFingerprint;
use App\Support\LexiLingoClient;
use App\Support\LexiLingoSchemaValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
     * The one switch that decides an entity's write model for this run:
     * true  → write nothing; stage the row as 'staged' for later approval;
     * false → write directly via syncFromSource(); stage it as 'applied'.
     *
     * Both halves read this same flag (see stageChange()), so a row can never
     * be live in the catalog and sitting in the approval queue at the same
     * time. Which entities get which model is decided in AdminImportRunner.
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
        // The checkpoint records how far the catalog has actually been brought
        // up to date. A staged run has written nothing, so moving the fetch
        // window here would step past rows nobody has approved: cancel the run
        // and they are skipped for good, recoverable only with --reset. Apply
        // advances it instead, once the rows really land.
        if ($this->stageOnly) {
            return;
        }

        $checkpoint = $this->checkpoint();
        $checkpoint->cursor = $replace ? $cursor : max((int) $checkpoint->cursor, $cursor);
        $checkpoint->last_synced_at = Carbon::now();
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
     * Record one upstream row for the admin review UI.
     *
     * Staging serves two different purposes depending on this entity's write
     * model, and the row's `status` is what tells them apart:
     *   stageOnly  → nothing was written; status 'staged' means "awaiting approval"
     *   direct     → the row is already live; status 'applied' means "audit trail"
     * Without that distinction both look identical to a reviewer, and apply
     * (which selects on status 'staged') would keep offering rows it will
     * then refuse. Issue #45 deliberately keeps staging visible for
     * direct-write entities — this only stops it from reading as a queue.
     *
     * Classification comes from the ownership metadata written by
     * HasCatalogOwnership::syncFromSource(), which is what finally lets
     * 'conflict' and 'unchanged' be produced instead of merely declared:
     * a row carrying a local override must never be overwritten, and a row
     * whose canonical fingerprint is unchanged needs no write at all.
     *
     * @param  array  $attributes  the normalized attributes syncFromSource persists,
     *                             not the raw provider payload — the fingerprint has
     *                             to be computed over the same canonical shape.
     */
    protected function stageChange(?string $externalId, array $incoming, ?Model $existing, array $attributes): void
    {
        $this->stageItem($externalId, $incoming, $existing, match (true) {
            $existing === null => 'new',
            (bool) $existing->local_override_at => 'conflict',
            $existing->source_fingerprint === CatalogFingerprint::make($this->fingerprintEntity(), $attributes) => 'unchanged',
            default => 'update',
        }, status: $this->stageOnly ? 'staged' : 'applied');
    }

    /** Singular entity key used by CatalogFingerprint. */
    protected function fingerprintEntity(): string
    {
        return match ($this->entity()) {
            'categories' => 'category',
            'courses' => 'course',
            'lessons' => 'lesson',
            'vocabulary' => 'vocabulary',
            default => throw new RuntimeException("No fingerprint entity for [{$this->entity()}]."),
        };
    }

    /**
     * Persist one staged-review row. No-op when this importer has no run
     * context (forRun() never called), so CLI/dry-run/test usage is
     * unaffected. Callers pass 'invalid' directly; the reviewable
     * classifications come from stageChange().
     */
    protected function stageItem(?string $externalId, array $incoming, ?Model $existing, string $classification, array $errors = [], string $status = 'staged'): void
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
            // between staging and approval and refuse to overwrite it. Both
            // halves matter: catalog_revision counts writes, the fingerprint
            // catches a write that restored an earlier value.
            'base_revision' => $existing?->catalog_revision,
            'base_fingerprint' => $existing?->source_fingerprint,
            'errors' => $safeErrors === [] ? null : $safeErrors,
            'status' => $status,
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

    /**
     * For page-based APIs (categories, courses), return the fixed server page
     * size. This decouples the client-side limit from the server's pagination
     * so that changing the limit between runs never causes skipped/duplicated
     * items.
     */
    protected function serverPageSize(): int
    {
        return 100;
    }

    /**
     * Fetch one page from a page-based upstream API at the correct offset,
     * returning only the slice the caller should process.
     *
     * Returns: [slice of items to process, next absolute cursor]
     */
    protected function fetchPageSlice(string $endpoint, int $offset, int $limit, array $query = []): array
    {
        $pageSize = $this->serverPageSize();
        $page = intdiv($offset, $pageSize) + 1;

        $payload = $this->client->partner()
            ->get($endpoint, array_merge($query, [
                'page' => $page,
                'page_size' => $pageSize,
            ]))
            ->throw()
            ->json();

        $allItems = $this->items($payload);

        $skipInPage = $offset % $pageSize;

        // Slice the page to only the items we have not yet processed,
        // capped at the requested limit.
        $slice = array_slice($allItems, $skipInPage, $limit);

        $nextCursor = $offset + count($slice);

        return [$slice, $nextCursor];
    }
}
