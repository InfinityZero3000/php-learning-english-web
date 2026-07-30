<?php

namespace App\Services\Import;

use App\Models\CourseCategory;
use Throwable;

class CategoryImporter extends AbstractLexiLingoImporter
{
    public function entity(): string
    {
        return 'categories';
    }

    public function import(int $limit, bool $dryRun = false, bool $reset = false, ?int $cursor = null): ImportResult
    {
        $offset = $this->startingCursor($reset, $cursor);

        [$items, $nextCursor] = $this->fetchPageSlice(
            '/api/v1/integrations/categories',
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

            $errors = $this->validator->validate('Category', $item);

            if ($errors !== []) {
                $skipped++;
                $this->logWarning('Skipped invalid category payload', [
                    'external_id' => $item['id'] ?? null,
                    'errors' => $errors,
                ]);

                if (! $dryRun) {
                    $this->archiveFailure($item['id'] ?? null, $item, $errors, ImportErrorCode::PayloadInvalid);
                }

                continue;
            }

            // Each category is its own write boundary: a DB conflict on
            // this row must not roll back categories already persisted
            // earlier in the same page.
            if (! $dryRun) {
                try {
                    CourseCategory::updateOrCreate(
                        ['external_id' => (string) $item['id']],
                        [
                            'name' => $item['name'],
                            'slug' => $item['slug'],
                            'description' => $item['description'] ?? null,
                            'icon' => $item['icon'] ?? null,
                            'color' => $item['color'] ?? null,
                        ]
                    );
                } catch (Throwable $e) {
                    $skipped++;
                    $errorCode = $this->classifyImportError($e);
                    $this->logWarning('Category write failed, skipping this category', [
                        'external_id' => $item['id'] ?? null,
                        'error_code' => $errorCode->value,
                        'error' => $e->getMessage(),
                    ]);
                    $this->archiveFailure($item['id'] ?? null, $item, [$e->getMessage()], $errorCode);

                    continue;
                }
            }

            $processed++;
        }

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
