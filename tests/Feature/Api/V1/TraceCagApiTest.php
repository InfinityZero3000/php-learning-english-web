<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\LearningSession;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TraceCagApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('features.ai', true);
        config()->set('services.lexilingo.ai_url', 'https://ai.lexilingo.test');
        config()->set('services.lexilingo.trace_cag_service_token', 'service-secret');
        config()->set('services.lexilingo.subject_hmac_secret', str_repeat('s', 32));
    }

    public function test_trace_cag_success_is_redacted_and_persisted_idempotently(): void
    {
        [$user, $session, $word] = $this->context();
        Http::fake(['ai.lexilingo.test/*' => Http::response($this->upstream())]);
        $requestId = (string) Str::uuid();
        $payload = [
            'session_id' => $session->id,
            'activity_id' => "vocabulary:{$word->id}",
            'input_type' => 'answer',
            'text' => 'xin chào',
        ];

        $first = $this->actingAs($user)->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/ai/trace-cag', $payload)
            ->assertOk()
            ->assertJsonPath('data.assistance.degraded', false)
            ->json();
        $this->withHeader('X-Request-ID', $requestId)->postJson('/api/v1/ai/trace-cag', $payload)
            ->assertExactJson($first);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('X-LexiLingo-Service-Token', 'service-secret')
            && strlen($request->data()['subject']) >= 32
            && $request->data()['subject'] !== (string) $user->id
            && ! array_key_exists('user_id', $request->data()));
        $this->assertDatabaseCount('learning_assistance_results', 1);
        $this->assertDatabaseCount('learning_events', 1);
    }

    public function test_trace_cag_timeout_returns_deterministic_fallback_and_keeps_learning_flow(): void
    {
        [$user, $session, $word] = $this->context();
        Http::fake(['ai.lexilingo.test/*' => Http::response([], 503)]);

        $this->actingAs($user)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/ai/trace-cag', [
                'session_id' => $session->id,
                'activity_id' => "vocabulary:{$word->id}",
                'input_type' => 'answer',
                'text' => 'hello',
            ])
            ->assertOk()
            ->assertJsonPath('data.assistance.degraded', true)
            ->assertJsonPath('data.assistance.recommended_action', 'retry');

        $this->assertDatabaseHas('learning_assistance_results', ['source' => 'local_fallback', 'status' => 'degraded']);
    }

    public function test_trace_cag_rejects_other_users_session_and_disabled_feature(): void
    {
        [, $session] = $this->context();
        $request = [
            'session_id' => $session->id,
            'activity_id' => 'vocabulary:1',
            'input_type' => 'answer',
            'text' => 'hello',
        ];
        $this->actingAs(User::factory()->create())->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/ai/trace-cag', $request)->assertForbidden();

        config()->set('features.ai', false);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/ai/trace-cag', $request)
            ->assertStatus(503)->assertJsonPath('code', 'FEATURE_DISABLED');
    }

    /**
     * A missing/misconfigured credential must degrade the same as an
     * upstream failure, not crash the learning flow with a 500.
     */
    public function test_trace_cag_missing_credential_degrades_instead_of_crashing(): void
    {
        config()->set('services.lexilingo.subject_hmac_secret', null);
        [$user, $session, $word] = $this->context();

        $this->actingAs($user)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/ai/trace-cag', [
                'session_id' => $session->id,
                'activity_id' => "vocabulary:{$word->id}",
                'input_type' => 'answer',
                'text' => 'hello',
            ])
            ->assertOk()
            ->assertJsonPath('data.assistance.degraded', true);

        $this->assertDatabaseHas('learning_assistance_results', ['source' => 'local_fallback', 'status' => 'degraded']);
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $course = Course::create(['title' => 'English A1', 'slug' => 'english-'.Str::random(6), 'status' => 'published']);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'title' => 'Greetings', 'slug' => 'greetings-'.Str::random(6), 'status' => 'published',
        ]);
        $word = Vocabulary::create(['lesson_id' => $lesson->id, 'word' => 'hello', 'meaning' => 'xin chào']);
        $session = LearningSession::create([
            'user_id' => $user->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
            'status' => 'active', 'started_at' => now(),
        ]);

        return [$user, $session, $word];
    }

    private function upstream(): array
    {
        return [
            'schema_version' => '1.0',
            'request_id' => (string) Str::uuid(),
            'diagnosis' => ['codes' => [], 'summary' => 'Correct'],
            'hints' => [],
            'feedback' => 'Good answer.',
            'recommended_action' => 'continue',
            'message' => 'Correct.',
            'degraded' => false,
            'model' => ['provider' => 'lexilingo', 'model' => 'trace-cag', 'version' => '1', 'latency_ms' => 10],
        ];
    }
}
