<?php

namespace App\Services;

use App\Models\Vocabulary;
use App\Support\LexiLingoClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LexiLingoVocabularySync
{
    public function __construct(private readonly LexiLingoClient $client) {}

    public function syncPage(int $offset = 0, int $limit = 100): int
    {
        $limit = min(100, max(1, $limit));
        $payload = Cache::store('redis')->remember(
            "lexilingo:vocabulary:{$limit}:{$offset}",
            now()->addMinutes(5),
            fn () => $this->client->backend()
                ->get('/api/v1/vocabulary/items', ['limit' => $limit, 'offset' => $offset])
                ->throw()
                ->json(),
        );

        $items = is_array($payload) ? $payload : ($payload['data'] ?? []);
        $count = 0;

        DB::transaction(function () use ($items, &$count): void {
            foreach ($items as $item) {
                if (! is_array($item) || empty($item['id']) || empty($item['word'])) {
                    continue;
                }

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
                $count++;
            }
        });

        return $count;
    }
}
