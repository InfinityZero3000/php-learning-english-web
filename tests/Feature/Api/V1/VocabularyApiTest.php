<?php

namespace Tests\Feature\Api\V1;

use App\Models\Bookmark;
use App\Models\Topic;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class VocabularyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_vocabulary_api_reads_local_persisted_data(): void
    {
        Vocabulary::create([
            'external_id' => 'lexi-word-1',
            'word' => 'hello',
            'meaning' => 'xin chào',
        ]);

        $this->getJson('/api/v1/vocabulary?search=hello')
            ->assertOk()
            ->assertJsonPath('data.0.external_id', 'lexi-word-1')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.progress')
            ->assertJsonMissingPath('data.0.bookmarked');
    }

    public function test_vocabulary_filters_saved_words_without_leaking_other_users_bookmarks(): void
    {
        $topic = Topic::create(['name' => 'Travel', 'slug' => 'travel']);
        $saved = Vocabulary::create(['word' => 'train', 'meaning' => 'tàu', 'topic_id' => $topic->id]);
        $other = Vocabulary::create(['word' => 'plane', 'meaning' => 'máy bay']);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Bookmark::create(['user_id' => $user->id, 'vocabulary_id' => $saved->id]);
        Bookmark::create(['user_id' => $otherUser->id, 'vocabulary_id' => $other->id]);

        $this->actingAs($user)->getJson("/api/v1/vocabulary?topic_id={$topic->id}&saved=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $saved->id)
            ->assertJsonPath('data.0.is_bookmarked', true)
            ->assertJsonPath('data.0.topic.name', 'Travel');

        $this->actingAs(User::factory()->create())->getJson('/api/v1/vocabulary')
            ->assertOk()
            ->assertJsonPath('data.0.is_bookmarked', false);
        Auth::logout();
        $this->getJson('/api/v1/vocabulary?saved=1')->assertUnauthorized();
    }

    public function test_bookmark_mutation_is_idempotent_and_validated(): void
    {
        $word = Vocabulary::create(['word' => 'hello', 'meaning' => 'xin chào']);
        $user = User::factory()->create();

        $this->putJson("/api/v1/vocabulary/{$word->id}/bookmark", ['bookmarked' => true])->assertUnauthorized();
        $this->actingAs($user)->putJson("/api/v1/vocabulary/{$word->id}/bookmark", ['bookmarked' => true])
            ->assertOk()->assertJsonPath('data.bookmarked', true);
        $this->actingAs($user)->putJson("/api/v1/vocabulary/{$word->id}/bookmark", ['bookmarked' => true])->assertOk();
        $this->assertDatabaseCount('bookmarks', 1);
        $this->actingAs($user)->putJson("/api/v1/vocabulary/{$word->id}/bookmark", ['bookmarked' => false])
            ->assertOk()->assertJsonPath('data.bookmarked', false);
        $this->actingAs($user)->putJson("/api/v1/vocabulary/{$word->id}/bookmark", ['bookmarked' => 'no'])
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->actingAs($user)->putJson('/api/v1/vocabulary/999999/bookmark', ['bookmarked' => true])->assertNotFound();
    }

    public function test_vocabulary_query_parameters_are_validated(): void
    {
        foreach (['topic_id=bad', 'saved=bad', 'page=0', 'per_page=101'] as $query) {
            $this->getJson("/api/v1/vocabulary?{$query}")
                ->assertUnprocessable()
                ->assertJsonPath('code', 'VALIDATION_ERROR');
        }
    }
}
