<?php

namespace App\Services\Import;

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
        $offset = $this->startingCursor($reset, $cursor);

        $payload = $this->client->partner()
            ->get('/api/v1/integrations/categories', [
                'page' => intdiv($offset, $limit) + 1,
                'page_size' => $limit,
            ])
            ->throw()
            ->json();

        $items = $this->items($payload);

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
}
