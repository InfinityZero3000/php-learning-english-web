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

        $tags = array_unique(array_filter($tags, fn ($t) => is_string($t) && trim($t) !== ''));

        foreach ($tags as $tag) {
            $slug = Str::slug(trim($tag));
            $externalId = 'lexilingo-tag:'.md5($slug);

            $topic = Topic::query()
                ->where('external_id', $externalId)
                ->orWhere('slug', $slug)
                ->first();

            if ($topic !== null) {
                $existing++;
                // Ensure external_id is backfilled
                if (empty($topic->external_id) && ! $dryRun) {
                    $topic->update(['external_id' => $externalId]);
                }
            } elseif ($dryRun) {
                $created++;
            } else {
                $topic = Topic::create([
                    'external_id' => $externalId,
                    'name' => trim($tag),
                    'slug' => $slug,
                ]);
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
}
