<?php

namespace App\Services\Import;

use App\Models\Topic;
use Illuminate\Support\Str;

class TagTopicImporter
{
    /**
     * Map a list of tags (from LexiLingo) to existing or new Topics.
     *
     * Idempotent: calling this with the same tag list a second time
     * will never create duplicate topics.
     *
     * @param  string[]  $tags  Flat array of tag strings.
     * @param  bool  $dryRun  When true, only compute what would be created without persisting.
     * @return array{created: int, existing: int, topic_ids: int[]}
     */
    public function syncTags(array $tags, bool $dryRun = false): array
    {
        $created = 0;
        $existing = 0;
        $topicIds = [];

        $tags = self::normalizeTags($tags);

        foreach ($tags as $tag) {
            $slug = Str::slug(trim($tag));
            $externalId = 'lexilingo-tag:'.md5($slug);

            $topic = Topic::query()->where('slug', $slug)->first();

            if ($topic !== null) {
                $existing++;
                if (! $dryRun && $topic->source_system === 'lexilingo' && $topic->external_id === $externalId) {
                    $topic = Topic::syncFromSource('topic', 'lexilingo', $externalId, [
                        'name' => trim($tag),
                        'slug' => $slug,
                    ])[0];
                }
            } elseif ($dryRun) {
                $created++;
            } else {
                $topic = Topic::syncFromSource('topic', 'lexilingo', $externalId, [
                    'name' => trim($tag),
                    'slug' => $slug,
                ])[0];
                $created++;
            }

            if ($topic !== null) {
                $topicIds[] = $topic->id;
            }
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'topic_ids' => $topicIds,
        ];
    }

    /** @return list<string> */
    public static function normalizeTags(array $tags): array
    {
        $normalized = [];

        foreach ($tags as $tag) {
            if (! is_string($tag) || trim($tag) === '') {
                continue;
            }

            $value = trim($tag);
            $slug = Str::slug($value);
            $normalized[$slug] ??= $value;
        }

        ksort($normalized);

        return array_values($normalized);
    }
}
