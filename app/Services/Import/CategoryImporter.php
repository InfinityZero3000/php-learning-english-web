<?php

namespace App\Services\Import;

use App\Models\AdminImportRun;
use App\Models\CourseCategory;
use Illuminate\Support\Facades\DB;

class CategoryImporter extends AbstractLexiLingoImporter
{
    public function entity(): string
    {
        return 'categories';
    }

    public function import(int $limit, bool $dryRun = false, bool $reset = false, ?int $cursor = null): ImportResult
    {
        $this->requireApplyEnabled($dryRun);
        $offset = $this->startingCursor($reset, $cursor);

        $payload = $this->client->partner()
            ->get('/api/v1/integrations/categories', [
                'page' => intdiv($offset, $limit) + 1,
                'page_size' => $limit,
            ])
            ->throw()
            ->json();

        $items = $this->stagedItems($payload);

        $processed = 0;
        $skipped = 0;

        DB::transaction(function () use ($items, $dryRun, &$processed, &$skipped) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    $skipped++;

                    continue;
                }

                $errors = $this->validator->validate('Category', $item);

                if ($errors !== []) {
                    $skipped++;
                    $this->logWarning('Skipped invalid category payload', [
                        'external_id' => $item['id'] ?? null,
                        'errors' => $errors,
                    ]);

                    if (! $dryRun) {
                        $this->archiveFailure($item['id'] ?? null, $item, $errors);
                    }

                    continue;
                }

                if (! $dryRun) {
                    CourseCategory::syncFromSource('category', 'lexilingo', (string) $item['id'], [
                        'name' => $item['name'],
                        'slug' => $item['slug'],
                        'description' => $item['description'] ?? null,
                        'icon' => $item['icon'] ?? null,
                        'color' => $item['color'] ?? null,
                    ]);
                }

                $processed++;
            }
        });

        $nextCursor = $offset + count($items);

        if (! $dryRun) {
            $this->advanceCheckpoint($nextCursor, $reset);
        }

        $this->logInfo('Category import page complete', [
            'offset' => $offset,
            'processed' => $processed,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        return new ImportResult($processed, $skipped, $nextCursor);
    }

    public function stage(AdminImportRun $run, StagedImportClassifier $classifier): ImportResult
    {
        $offset = (int) $run->starting_cursor;
        $payload = $this->client->partner()
            ->get('/api/v1/integrations/categories', [
                'page' => intdiv($offset, $run->requested_limit) + 1,
                'page_size' => $run->requested_limit,
            ])
            ->throw()
            ->json();
        $items = $this->stagedItems($payload, min(100, (int) $run->requested_limit));
        $this->requireValidSchema('CategoryListResponse', [...$payload, 'data' => []]);
        $invalid = 0;

        foreach ($items as $item) {
            $item = is_array($item) ? $item : [];
            $externalId = isset($item['id']) ? (string) $item['id'] : null;
            $candidate = array_intersect_key($item, array_flip([
                'name', 'slug', 'description', 'icon', 'color',
            ]));
            $errors = $this->validator->validate('Category', $item);
            $existing = $externalId
                ? CourseCategory::query()->where('source_system', 'lexilingo')->where('external_id', $externalId)->first()
                : null;
            $naturalMatches = isset($candidate['slug'])
                ? CourseCategory::query()->where('slug', $candidate['slug'])->get()->map->only([
                    'id', 'catalog_revision', 'source_fingerprint', 'source_snapshot', 'local_override_at',
                ])->all()
                : [];
            $classification = $classifier->classify(
                'category',
                $candidate,
                $existing?->only(['id', 'catalog_revision', 'source_fingerprint', 'source_snapshot', 'local_override_at']),
                $errors,
                $naturalMatches,
            );
            $invalid += $classification['classification'] === 'invalid' ? 1 : 0;

            $this->stageItem(
                $run,
                $classifier,
                'category',
                $externalId,
                $candidate['slug'] ?? null,
                $candidate,
                $existing,
                $naturalMatches,
                $errors,
            );
        }

        return new ImportResult(count($items) - $invalid, $invalid, $offset + count($items));
    }
}
