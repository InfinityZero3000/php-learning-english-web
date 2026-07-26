<?php

namespace App\Services;

use App\Models\Vocabulary;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VocabularyEnrichmentService
{
    public function enrich(Vocabulary $vocabulary): Vocabulary
    {
        if (! config('services.enrichment.enabled')) {
            throw new RuntimeException('Vocabulary enrichment is disabled.');
        }

        $word = $vocabulary->word;
        $dictionary = $this->dictionary($word);
        $translation = $this->translation($word);
        $image = $this->image($word);

        $vocabulary->forceFill([
            'definition' => $dictionary['definition'] ?? $vocabulary->definition,
            'pronunciation' => $dictionary['pronunciation'] ?? $vocabulary->pronunciation,
            'part_of_speech' => $dictionary['part_of_speech'] ?? $vocabulary->part_of_speech,
            'meaning' => $translation ?? $vocabulary->meaning,
            'image_path' => $image ?? $vocabulary->image_path,
        ])->save();

        return $vocabulary->refresh();
    }

    private function dictionary(string $word): array
    {
        $base = config('services.enrichment.wiktapi_url');
        if (! $base) {
            return [];
        }

        $payload = Http::timeout(10)->get(rtrim($base, '/').'/words/'.rawurlencode($word))->json();
        $entry = data_get($payload, 'data.0', data_get($payload, '0', $payload));

        return [
            'definition' => data_get($entry, 'definitions.0.definition', data_get($entry, 'definition')),
            'pronunciation' => data_get($entry, 'pronunciation', data_get($entry, 'phonetic')),
            'part_of_speech' => data_get($entry, 'partOfSpeech', data_get($entry, 'part_of_speech')),
        ];
    }

    private function translation(string $word): ?string
    {
        $base = config('services.enrichment.libretranslate_url');
        if (! $base) {
            return null;
        }

        return Http::timeout(10)->post(rtrim($base, '/').'/translate', [
            'q' => $word, 'source' => 'en', 'target' => 'vi', 'format' => 'text',
        ])->json('translatedText');
    }

    private function image(string $word): ?string
    {
        $key = config('services.enrichment.pixabay_key');
        if ($key) {
            $hit = Http::timeout(10)->get('https://pixabay.com/api/', [
                'key' => $key, 'q' => $word, 'image_type' => 'photo', 'per_page' => 3,
            ])->json('hits.0.webformatURL');
            if (is_string($hit) && filter_var($hit, FILTER_VALIDATE_URL)) {
                return $hit;
            }
        }

        $key = config('services.enrichment.pexels_key');
        if ($key) {
            $hit = Http::withHeaders(['Authorization' => $key])->timeout(10)
                ->get('https://api.pexels.com/v1/search', ['query' => $word, 'per_page' => 3])
                ->json('photos.0.src.medium');
            if (is_string($hit) && filter_var($hit, FILTER_VALIDATE_URL)) {
                return $hit;
            }
        }

        return null;
    }
}
