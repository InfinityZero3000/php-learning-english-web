<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerToolsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_flashcard_recommendations_save_to_the_authenticated_users_fsrs_queue(): void
    {
        $user = User::factory()->create();
        $word = Vocabulary::create(['word' => 'restore', 'meaning' => 'khôi phục']);

        $this->actingAs($user)->getJson('/api/v1/flashcards/recommendations?search=restore')
            ->assertOk()->assertJsonPath('data.0.id', $word->id);
        $this->actingAs($user)->postJson("/api/v1/flashcards/recommendations/{$word->id}/save")
            ->assertCreated();
        $this->assertDatabaseHas('user_vocabularies', ['user_id' => $user->id, 'vocabulary_id' => $word->id]);
    }

    public function test_import_is_validated_transactional_and_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $payload = ['sourceType' => 'PASTE', 'targetSet' => ['name' => 'Daily'], 'rows' => [
            ['word' => 'backend', 'translation' => 'phía máy chủ', 'difficulty' => 'INTERMEDIATE', 'category' => 'noun', 'cefrLevel' => 'B1'],
        ]];

        $this->actingAs($user)->postJson('/api/v1/learner-imports', $payload)
            ->assertCreated()->assertJsonPath('data.createdCount', 1);
        $this->actingAs($user)->getJson('/api/v1/learner-imports')
            ->assertOk()->assertJsonPath('data.0.targetSetName', 'Daily');
        $this->assertDatabaseHas('user_vocabularies', ['user_id' => $user->id]);
        $this->assertDatabaseHas('vocabularies', ['word' => 'backend', 'part_of_speech' => 'noun']);
        $this->assertSame(['B1'], Vocabulary::query()->where('word', 'backend')->firstOrFail()->tags);

        $this->actingAs($user)->postJson('/api/v1/learner-imports', [
            'sourceType' => 'PASTE', 'rows' => [['word' => 'backend', 'translation' => 'hệ thống phía sau']],
        ])->assertCreated()->assertJsonPath('data.createdCount', 1);
        $this->assertSame(2, Vocabulary::query()->where('word', 'backend')->count());

        $this->actingAs($user)->postJson('/api/v1/learner-imports', $payload)
            ->assertCreated()->assertJsonPath('data.createdCount', 0);
        $this->assertSame(2, Vocabulary::query()->where('word', 'backend')->count());

        $corrected = $payload;
        $corrected['rows'][0]['pronunciation'] = '/ˈbæk.end/';
        $this->actingAs($user)->postJson('/api/v1/learner-imports', $corrected)
            ->assertCreated()->assertJsonPath('data.createdCount', 1);
        $this->assertSame(3, Vocabulary::query()->where('word', 'backend')->count());

        $this->actingAs($user)->postJson('/api/v1/learner-imports', ['sourceType' => 'PASTE', 'rows' => []])
            ->assertUnprocessable();
    }

    public function test_vocabulary_quiz_is_server_graded_idempotent_and_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        foreach (range(1, 5) as $i) {
            Vocabulary::create(['word' => "word{$i}", 'meaning' => "nghia{$i}"]);
        }

        $started = $this->actingAs($owner)->postJson('/api/v1/vocabulary-quizzes', ['count' => 5, 'type' => 'en-vi'])
            ->assertCreated()->json('data');
        $this->assertArrayNotHasKey('words', $started);
        $question = collect($started['questions'])->first();
        $expected = str_replace('word', 'nghia', $question['prompt']);
        $url = "/api/v1/vocabulary-quizzes/{$started['sessionId']}/answers/{$question['id']}";

        $this->actingAs($owner)->putJson($url, ['answer' => $expected])
            ->assertOk()->assertJsonPath('data.isCorrect', true);
        $this->actingAs($owner)->putJson($url, ['answer' => 'wrong'])
            ->assertOk()->assertJsonPath('data.isCorrect', true);
        $this->actingAs($other)->postJson("/api/v1/vocabulary-quizzes/{$started['sessionId']}/complete")
            ->assertNotFound();
        $this->actingAs($owner)->postJson("/api/v1/vocabulary-quizzes/{$started['sessionId']}/complete")
            ->assertConflict();
        foreach (collect($started['questions'])->skip(1) as $remaining) {
            $this->actingAs($owner)->putJson(
                "/api/v1/vocabulary-quizzes/{$started['sessionId']}/answers/{$remaining['id']}",
                ['answer' => str_replace('word', 'nghia', $remaining['prompt'])],
            )->assertOk();
        }
        $this->actingAs($owner)->postJson("/api/v1/vocabulary-quizzes/{$started['sessionId']}/complete")
            ->assertOk()->assertJsonPath('data.score', 100);
        $this->actingAs($owner)->putJson($url, ['answer' => 'wrong'])
            ->assertOk()->assertJsonPath('data.isCorrect', true);
    }

    public function test_notifications_derive_from_due_state_and_remember_read_state(): void
    {
        $user = User::factory()->create();
        $word = Vocabulary::create(['word' => 'notify', 'meaning' => 'thông báo']);
        UserVocabulary::create(['user_id' => $user->id, 'vocabulary_id' => $word->id, 'due_at' => now()->subMinute()]);

        $this->actingAs($user)->getJson('/api/v1/notifications')
            ->assertOk()->assertJsonPath('data.0.isRead', false);
        $key = 'fsrs-due:'.now('UTC')->toDateString();
        $this->actingAs($user)->postJson("/api/v1/notifications/{$key}/read")->assertOk();
        $this->actingAs($user)->getJson('/api/v1/notifications')
            ->assertJsonPath('data.0.isRead', true);
        $this->travel(1)->days();
        $this->actingAs($user)->getJson('/api/v1/notifications')
            ->assertJsonPath('data.0.isRead', false);
    }
}
