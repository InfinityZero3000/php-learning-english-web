<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Fsrs\FsrsConfig;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class FsrsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_is_atomic_and_idempotent(): void
    {
        [$user, , , $state] = $this->context();
        $requestId = (string) Str::uuid();
        $payload = [
            'user_vocabulary_id' => $state->id,
            'rating' => 'good',
            'base_revision' => 0,
        ];

        $first = $this->actingAs($user)
            ->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/fsrs/review', $payload)
            ->assertOk()
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.algorithm', FsrsConfig::VERSION)
            ->json('data');

        $this->assertDatabaseCount('vocabulary_reviews', 1);
        $this->assertDatabaseCount('learning_events', 1);
        $this->assertDatabaseHas('user_vocabularies', ['id' => $state->id, 'revision' => 1]);

        $this->actingAs($user)
            ->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/fsrs/review', $payload)
            ->assertOk()
            ->assertExactJson(['data' => $first, 'meta' => []]);
        $this->assertDatabaseCount('vocabulary_reviews', 1);
        $this->assertDatabaseCount('learning_events', 1);
    }

    public function test_rejects_stale_revision_and_request_id_payload_conflict(): void
    {
        [$user, , , $state] = $this->context();
        $requestId = (string) Str::uuid();
        $payload = [
            'user_vocabulary_id' => $state->id,
            'rating' => 'good',
            'base_revision' => 0,
        ];
        $this->actingAs($user)->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/fsrs/review', $payload)->assertOk();

        $this->actingAs($user)->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/fsrs/review', [...$payload, 'rating' => 'easy'])
            ->assertConflict();
        $this->actingAs($user)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/fsrs/review', $payload)->assertConflict();
    }

    public function test_due_and_stats_are_scoped_to_authenticated_user(): void
    {
        [$user] = $this->context();
        [$other] = $this->context('other');

        $this->actingAs($user)->getJson('/api/v1/fsrs/due')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'fsrs_card')
            ->assertJsonPath('meta.total', 1);
        $this->actingAs($user)->getJson('/api/v1/fsrs/stats')
            ->assertOk()
            ->assertJsonPath('data.type', 'fsrs_stats')
            ->assertJsonPath('data.due_count', 1);
        $this->actingAs($other)->getJson('/api/v1/fsrs/stats')
            ->assertJsonPath('data.due_count', 1);
        auth()->logout();
        $this->getJson('/api/v1/fsrs/due')->assertUnauthorized();
    }

    public function test_cannot_review_another_users_vocabulary(): void
    {
        [$owner, , , $state] = $this->context();
        $attacker = User::factory()->create();

        $this->actingAs($attacker)
            ->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/fsrs/review', [
                'user_vocabulary_id' => $state->id,
                'rating' => 'good',
                'base_revision' => 0,
            ])->assertForbidden();
        $this->assertDatabaseCount('vocabulary_reviews', 0);
        $this->assertNotNull($owner);
    }

    public function test_not_due_review_is_rejected_without_mutating_state(): void
    {
        [$user, , , $state] = $this->context();
        $state->update(['due_at' => now('UTC')->addHour()]);

        $this->actingAs($user)
            ->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/fsrs/review', [
                'user_vocabulary_id' => $state->id,
                'rating' => 'good',
                'base_revision' => 0,
            ])->assertConflict();

        $this->assertDatabaseHas('user_vocabularies', ['id' => $state->id, 'revision' => 0]);
        $this->assertDatabaseCount('vocabulary_reviews', 0);
    }

    public function test_event_failure_rolls_back_review_and_fsrs_state(): void
    {
        [$user, , , $state] = $this->context();
        $event = 'eloquent.creating: '.LearningEvent::class;
        Event::listen($event, static fn () => throw new \RuntimeException('forced'));

        try {
            $response = $this->actingAs($user)
                ->withHeader('X-Request-ID', (string) Str::uuid())
                ->postJson('/api/v1/fsrs/review', [
                    'user_vocabulary_id' => $state->id,
                    'rating' => 'good',
                    'base_revision' => 0,
                ]);
        } finally {
            Event::forget($event);
        }

        $response->assertServerError();

        $this->assertDatabaseHas('user_vocabularies', ['id' => $state->id, 'revision' => 0]);
        $this->assertDatabaseCount('vocabulary_reviews', 0);
        $this->assertDatabaseCount('learning_events', 0);
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
