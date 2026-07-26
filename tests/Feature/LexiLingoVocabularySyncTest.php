<?php

namespace Tests\Feature;

use App\Services\LexiLingoVocabularySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LexiLingoVocabularySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_upstream_page_is_cached_and_vocabulary_is_persisted(): void
    {
        config()->set('services.lexilingo.backend_url', 'https://backend.lexilingo.test');
        config()->set('cache.stores.redis', ['driver' => 'array']);
        Cache::spy();
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
            'external_id' => 'lexi-word-1',
            'word' => 'hello',
            'meaning' => 'xin chào',
        ]);
        Http::assertSentCount(1);
        Cache::shouldHaveReceived('store')->with('redis');
    }
}
