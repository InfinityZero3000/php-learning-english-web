<?php

namespace Tests\Feature;

use App\Models\Vocabulary;
use App\Services\LexiLingoVocabularySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LexiLingoVocabularySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_upstream_page_is_imported_and_vocabulary_is_persisted(): void
    {
        config()->set('services.lexilingo.backend_url', 'https://backend.lexilingo.test');
        config()->set('services.lexilingo.partner_api_key', 'partner-secret');
        Http::fake([
            'backend.lexilingo.test/*' => Http::response([[
                'id' => 'lexi-word-1',
                'course_id' => null,
                'lesson_id' => null,
                'word' => 'hello',
                'definition' => 'a greeting',
                'translation' => ['vi' => 'xin chào'],
                'pronunciation' => null,
                'audio_url' => null,
                'part_of_speech' => 'interjection',
                'difficulty_level' => 'beginner',
                'tags' => ['greeting'],
                'usage_frequency' => 10,
            ]]),
        ]);

        $this->app->make(LexiLingoVocabularySync::class)->syncPage();

        $this->assertDatabaseHas('vocabularies', [
            'source_system' => 'lexilingo',
            'external_id' => 'lexi-word-1',
            'word' => 'hello',
            'meaning' => 'xin chào',
            'catalog_revision' => 1,
        ]);
        $this->assertDatabaseHas('topics', [
            'source_system' => 'lexilingo',
            'name' => 'greeting',
        ]);
        $this->assertNotNull(Vocabulary::query()->firstOrFail()->topic_id);
        Http::assertSentCount(1);
    }

    public function test_reordered_tags_keep_the_same_deterministic_primary_topic(): void
    {
        config()->set('services.lexilingo.backend_url', 'https://backend.lexilingo.test');
        config()->set('services.lexilingo.partner_api_key', 'partner-secret');
        $payload = fn (array $tags): array => [[
            'id' => 'word-tags', 'course_id' => null, 'lesson_id' => null, 'word' => 'trip',
            'definition' => 'journey', 'translation' => ['vi' => 'chuyến đi'], 'pronunciation' => null,
            'audio_url' => null, 'part_of_speech' => 'noun', 'difficulty_level' => 'beginner',
            'tags' => $tags, 'usage_frequency' => 1,
        ]];
        Http::fake(['backend.lexilingo.test/*' => Http::sequence()
            ->push($payload(['Travel', 'Business']))
            ->push($payload(['Business', 'Travel']))]);
        $sync = $this->app->make(LexiLingoVocabularySync::class);

        $sync->syncPage();
        $sync->syncPage();

        $word = Vocabulary::query()->with('topic')->where('external_id', 'word-tags')->firstOrFail();
        $this->assertSame('Business', $word->topic->name);
        $this->assertSame(['Business', 'Travel'], $word->tags);
        $this->assertSame(1, $word->catalog_revision);
    }
}
