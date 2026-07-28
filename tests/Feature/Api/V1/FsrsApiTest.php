<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Fsrs\FsrsConfig;
use App\Models\Course;
use App\Models\LearningSession;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FsrsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_is_atomic_and_idempotent(): void
    {
        [$user, $word, $session, $state] = $this->context();
        $requestId = (string) Str::uuid();
        $payload = [
            'request_id' => $requestId,
            'learning_session_id' => $session->id,
            'vocabulary_id' => $word->id,
            'rating' => 3,
            'base_revision' => 0,
            'response_time_ms' => 1200,
        ];

        $first = $this->actingAs($user)->postJson('/api/v1/fsrs/reviews', $payload)
            ->assertOk()
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.algorithm', FsrsConfig::VERSION)
            ->json('data');

        $this->assertDatabaseCount('vocabulary_reviews', 1);
        $this->assertDatabaseCount('learning_events', 1);
        $this->assertDatabaseHas('user_vocabularies', ['id' => $state->id, 'revision' => 1]);

        $this->actingAs($user)->postJson('/api/v1/fsrs/reviews', $payload)
            ->assertOk()
            ->assertExactJson(['data' => $first, 'meta' => []]);
        $this->assertDatabaseCount('vocabulary_reviews', 1);
        $this->assertDatabaseCount('learning_events', 1);
    }

    public function test_rejects_stale_revision_and_request_id_payload_conflict(): void
    {
        [$user, $word, $session] = $this->context();
        $requestId = (string) Str::uuid();
        $payload = [
            'request_id' => $requestId,
            'learning_session_id' => $session->id,
            'vocabulary_id' => $word->id,
            'rating' => 3,
            'base_revision' => 0,
        ];
        $this->actingAs($user)->postJson('/api/v1/fsrs/reviews', $payload)->assertOk();

        $this->actingAs($user)->postJson('/api/v1/fsrs/reviews', [...$payload, 'rating' => 4])
            ->assertConflict();
        $this->actingAs($user)->postJson('/api/v1/fsrs/reviews', [
            ...$payload,
            'request_id' => (string) Str::uuid(),
        ])->assertConflict();
    }

    public function test_due_and_stats_are_scoped_to_authenticated_user(): void
    {
        [$user] = $this->context();
        [$other] = $this->context('other');

        $this->actingAs($user)->getJson('/api/v1/fsrs/due')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson('/api/v1/fsrs/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
        $this->actingAs($other)->getJson('/api/v1/fsrs/stats')
            ->assertJsonPath('data.total', 1);
        auth()->logout();
        $this->getJson('/api/v1/fsrs/due')->assertUnauthorized();
    }

    public function test_cannot_review_inside_another_users_session(): void
    {
        [$owner, $word, $session] = $this->context();
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->postJson('/api/v1/fsrs/reviews', [
            'request_id' => (string) Str::uuid(),
            'learning_session_id' => $session->id,
            'vocabulary_id' => $word->id,
            'rating' => 3,
            'base_revision' => 0,
        ])->assertForbidden();
        $this->assertDatabaseCount('vocabulary_reviews', 0);
        $this->assertNotNull($owner);
    }

    private function context(string $suffix = ''): array
    {
        $user = User::factory()->create();
        $course = Course::create(['title' => "Course {$suffix}", 'slug' => "course-{$suffix}".Str::random(5)]);
        $word = Vocabulary::create(['word' => "hello{$suffix}".Str::random(3), 'meaning' => 'xin chào']);
        $session = LearningSession::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $state = UserVocabulary::create([
            'user_id' => $user->id,
            'vocabulary_id' => $word->id,
            'due_at' => now()->subMinute(),
        ]);

        return [$user, $word, $session, $state];
    }
}
