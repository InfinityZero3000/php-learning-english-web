<?php

namespace Tests\Feature\Api\V1;

use App\Models\Topic;
use App\Services\Import\TagTopicImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTopicIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private TagTopicImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = $this->app->make(TagTopicImporter::class);
    }

    public function test_sync_tags_creates_topics_from_lexilingo_tags(): void
    {
        $tags = ['Business English', 'Travel Tips'];

        $result = $this->importer->syncTags($tags);

        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['existing']);
        $this->assertCount(2, $result['topic_ids']);

        $this->assertDatabaseHas('topics', ['external_id' => 'lexilingo-tag:'.md5('business-english')]);
        $this->assertDatabaseHas('topics', ['external_id' => 'lexilingo-tag:'.md5('travel-tips')]);
    }

    public function test_sync_tags_is_idempotent(): void
    {
        $tags = ['Business English', 'Travel Tips', 'Grammar'];

        // First run
        $result1 = $this->importer->syncTags($tags);
        $this->assertEquals(3, $result1['created']);
        $this->assertEquals(0, $result1['existing']);

        // Second run with same tags – should find all existing, create none
        $result2 = $this->importer->syncTags($tags);
        $this->assertEquals(0, $result2['created']);
        $this->assertEquals(3, $result2['existing']);

        // Database should still have exactly 3 topics
        $this->assertDatabaseCount('topics', 3);
    }

    public function test_sync_tags_handles_partial_overlap_idempotently(): void
    {
        $firstBatch = ['Business English', 'Travel Tips'];
        $secondBatch = ['Travel Tips', 'Grammar', 'Pronunciation'];

        $result1 = $this->importer->syncTags($firstBatch);
        $this->assertEquals(2, $result1['created']);

        $result2 = $this->importer->syncTags($secondBatch);
        $this->assertEquals(2, $result2['created']); // Grammar + Pronunciation
        $this->assertEquals(1, $result2['existing']); // Travel Tips

        $this->assertDatabaseCount('topics', 4);
    }

    public function test_sync_tags_dry_run_creates_nothing(): void
    {
        $tags = ['Business English', 'Travel Tips'];

        $result = $this->importer->syncTags($tags, dryRun: true);

        $this->assertEquals(2, $result['created']); // reports what WOULD be created
        $this->assertDatabaseCount('topics', 0);    // actually persisted nothing
    }

    public function test_sync_tags_normalizes_duplicate_tags(): void
    {
        $tags = ['Business English', 'business-english', 'Business English'];

        $result = $this->importer->syncTags($tags);

        $this->assertEquals(1, $result['created']);
        $this->assertDatabaseCount('topics', 1);
    }

    public function test_sync_tags_slug_collisions_are_handled(): void
    {
        // "Business English" and "business-english" produce the same slug
        $tags = ['Business English', 'business-english'];

        $result = $this->importer->syncTags($tags);

        $this->assertEquals(1, $result['created']);
        $this->assertDatabaseCount('topics', 1);
    }

    public function test_sync_tags_handles_empty_array(): void
    {
        $result = $this->importer->syncTags([]);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['existing']);
        $this->assertEmpty($result['topic_ids']);
        $this->assertDatabaseCount('topics', 0);
    }

    public function test_sync_tags_filters_null_and_whitespace_tags(): void
    {
        $tags = ['Valid Tag', '', null, '   ', 'Another Tag'];

        $result = $this->importer->syncTags($tags);

        $this->assertEquals(2, $result['created']);
        $this->assertDatabaseCount('topics', 2);
    }

    public function test_sync_tags_backfills_external_id_for_existing_topic(): void
    {
        // Create a topic without external_id (simulating pre-migration state)
        $topic = Topic::create([
            'name' => 'Business English',
            'slug' => 'business-english',
            'external_id' => null,
        ]);

        $this->assertNull($topic->fresh()->external_id);

        $result = $this->importer->syncTags(['Business English']);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['existing']);

        $this->assertNotNull($topic->fresh()->external_id);
        $this->assertEquals('lexilingo-tag:'.md5('business-english'), $topic->fresh()->external_id);
    }

    public function test_idempotency_across_multiple_runs_with_same_tags(): void
    {
        $tags = ['Topic A', 'Topic B', 'Topic C'];
        $expectedSlugs = ['topic-a', 'topic-b', 'topic-c'];

        // Run 5 times
        for ($i = 0; $i < 5; $i++) {
            $result = $this->importer->syncTags($tags);

            if ($i === 0) {
                $this->assertEquals(3, $result['created']);
                $this->assertEquals(0, $result['existing']);
            } else {
                $this->assertEquals(0, $result['created']);
                $this->assertEquals(3, $result['existing']);
            }
        }

        $this->assertDatabaseCount('topics', 3);

        foreach ($expectedSlugs as $slug) {
            $this->assertDatabaseHas('topics', ['slug' => $slug]);
        }
    }

    public function test_topic_slugs_are_consistent(): void
    {
        $tags = ['  Business  English  ', 'Café & Restaurant'];

        $result = $this->importer->syncTags($tags);
        $this->assertEquals(2, $result['created']);

        $this->assertDatabaseHas('topics', ['slug' => 'business-english']);
        $this->assertDatabaseHas('topics', ['slug' => 'cafe-restaurant']);
    }
}
