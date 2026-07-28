<?php

namespace App\Services;

use App\Models\Vocabulary;
use App\Services\Import\AbstractLexiLingoImporter;
use App\Services\Import\ImportResult;
use Illuminate\Support\Facades\DB;

class LexiLingoVocabularySync extends AbstractLexiLingoImporter
{
    private int $lastSkipped = 0;

    public function entity(): string
    {
        return 'vocabulary';
    }

    public function import(int $limit, bool $dryRun = false, bool $reset = false): ImportResult
    {
        $offset = $this->startingCursor($reset);
        $processed = $this->syncPage($offset, $limit, $dryRun);
        $nextCursor = $dryRun ? $offset : $this->checkpoint()->cursor;

        return new ImportResult($processed, $this->lastSkipped, $nextCursor);
    }

    public function syncPage(int $offset = 0, int $limit = 100, bool $dryRun = false): int
    {
        $limit = min(100, max(1, $limit));
        $payload = $this->client->partner()
            ->get('/api/v1/integrations/vocabulary/items', ['limit' => $limit, 'offset' => $offset])
            ->throw()
            ->json();

        $items = $this->items($payload);
        $count = 0;
        $this->lastSkipped = 0;

        DB::transaction(function () use ($items, $dryRun, &$count): void {
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
                        $this->archiveFailure((string) $item['id'], $item, $errors);
                    }

                    continue;
                }

                if (! $dryRun) {
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
                }

                $count++;
            }
        });

        if (! $dryRun) {
            $this->advanceCheckpoint($offset + count($items));
        }

        return $count;
    }
}
