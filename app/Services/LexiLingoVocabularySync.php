<?php

namespace App\Services;

use App\Models\Vocabulary;
use App\Services\Import\AbstractLexiLingoImporter;
use App\Services\Import\ImportErrorCode;
use App\Services\Import\ImportResult;
use Throwable;

class LexiLingoVocabularySync extends AbstractLexiLingoImporter
{
    private int $lastSkipped = 0;

    public function entity(): string
    {
        return 'vocabulary';
    }

    public function import(int $limit, bool $dryRun = false, bool $reset = false, ?int $cursor = null): ImportResult
    {
        $offset = $this->startingCursor($reset, $cursor);
        $processed = $this->syncPage($offset, $limit, $dryRun, $reset);
        $nextCursor = $dryRun ? $offset : $this->checkpoint()->cursor;

        return new ImportResult($processed, $this->lastSkipped, $nextCursor);
    }

    public function syncPage(int $offset = 0, int $limit = 100, bool $dryRun = false, bool $replaceCheckpoint = false): int
    {
        $limit = min(100, max(1, $limit));
        $payload = $this->client->partner()
            ->get('/api/v1/integrations/vocabulary/items', ['limit' => $limit, 'offset' => $offset])
            ->throw()
            ->json();

        $items = $this->items($payload);
        $count = 0;
        $this->lastSkipped = 0;

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['id']) || empty($item['word'])) {
                continue;
            }

            $errors = $this->validator->validate('VocabularyItem', $item);

            if ($errors !== []) {
                $this->lastSkipped++;
                $this->logWarning('Skipped invalid vocabulary payload', [
                    'external_id' => $item['id'],
                    'errors' => $errors,
                ]);

                if (! $dryRun) {
                    $this->archiveFailure((string) $item['id'], $item, $errors, ImportErrorCode::PayloadInvalid);
                }

                continue;
            }

            // Each vocabulary item is its own write boundary: a DB conflict
            // on this row must not roll back items already persisted
            // earlier in the same page.
            if (! $dryRun) {
                try {
                    Vocabulary::updateOrCreate(
                        ['external_id' => (string) $item['id']],
                        [
                            'word' => $item['word'],
                            'meaning' => data_get($item, 'translation.vi') ?: ($item['definition'] ?? $item['word']),
                            'definition' => $item['definition'] ?? null,
                            'translation' => $item['translation'] ?? null,
                            'pronunciation' => $item['pronunciation'] ?? null,
                            'part_of_speech' => $item['part_of_speech'] ?? null,
                            'difficulty_level' => $item['difficulty_level'] ?? null,
                            'tags' => $item['tags'] ?? null,
                            'external_audio_url' => $item['audio_url'] ?? null,
                        ],
                    );
                } catch (Throwable $e) {
                    $this->lastSkipped++;
                    $errorCode = $this->classifyImportError($e);
                    $this->logWarning('Vocabulary write failed, skipping this item', [
                        'external_id' => $item['id'],
                        'error_code' => $errorCode->value,
                        'error' => $e->getMessage(),
                    ]);
                    $this->archiveFailure((string) $item['id'], $item, [$e->getMessage()], $errorCode);

                    continue;
                }
            }

            $count++;
        }

        if (! $dryRun) {
            $this->advanceCheckpoint($offset + count($items), $replaceCheckpoint);
        }

        return $count;
    }
}
