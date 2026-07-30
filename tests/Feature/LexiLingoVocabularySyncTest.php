<?php

namespace Tests\Feature;

use App\Models\Vocabulary;
use App\Services\LexiLingoVocabularySync;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PDOException;
use Tests\TestCase;

class LexiLingoVocabularySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // The write-failure test below registers a model event listener to
        // inject a fault; make sure it never leaks into other tests running
        // in the same process.
        Vocabulary::flushEventListeners();

        parent::tearDown();
    }

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
            'external_id' => 'lexi-word-1',
            'word' => 'hello',
            'meaning' => 'xin chào',
        ]);
        Http::assertSentCount(1);
    }

    /**
     * A DB-level conflict writing one vocabulary item must not roll back
     * items already persisted earlier in the same page. The failure is
     * injected via a model event (rather than a real column-length/unique
     * violation) so the test is portable across the sqlite test driver and
     * the mysql production driver, which enforce constraints differently.
     */
    public function test_write_failure_on_one_item_does_not_roll_back_others(): void
    {
        config()->set('services.lexilingo.backend_url', 'https://backend.lexilingo.test');
        config()->set('services.lexilingo.partner_api_key', 'partner-secret');

        $itemFixture = fn (string $id, string $word) => [
            'id' => $id,
            'course_id' => null,
            'lesson_id' => null,
            'word' => $word,
            'definition' => 'a definition',
            'translation' => null,
            'pronunciation' => null,
            'audio_url' => null,
            'part_of_speech' => 'noun',
            'difficulty_level' => 'beginner',
            'tags' => null,
            'usage_frequency' => 1,
        ];

        Http::fake([
            'backend.lexilingo.test/*' => Http::response([
                $itemFixture('lexi-1', 'hello'),
                $itemFixture('lexi-2', 'conflict'),
                $itemFixture('lexi-3', 'world'),
            ]),
        ]);

        Vocabulary::saving(function (Vocabulary $vocabulary): void {
            if ($vocabulary->external_id === 'lexi-2') {
                throw new QueryException(
                    'sqlite',
                    'insert into "vocabularies" ("external_id", "word") values (?, ?)',
                    [],
                    new PDOException('UNIQUE constraint failed: vocabularies.external_id'),
                );
            }
        });

        $sync = $this->app->make(LexiLingoVocabularySync::class);
        $processed = $sync->syncPage();

        $this->assertSame(2, $processed);
        $this->assertDatabaseHas('vocabularies', ['external_id' => 'lexi-1', 'word' => 'hello']);
        $this->assertDatabaseMissing('vocabularies', ['external_id' => 'lexi-2']);
        $this->assertDatabaseHas('vocabularies', ['external_id' => 'lexi-3', 'word' => 'world']);

        $this->assertDatabaseHas('lexilingo_import_failures', [
            'entity' => 'vocabulary',
            'external_id' => 'lexi-2',
            'error_code' => 'write_failed',
        ]);
    }
}
