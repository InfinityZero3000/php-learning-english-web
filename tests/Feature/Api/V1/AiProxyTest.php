<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\LearningSession;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.lexilingo', [
            'backend_url' => 'https://backend.lexilingo.test',
            'ai_url' => 'https://ai.lexilingo.test',
            'import_key' => 'import-secret',
            'ai_service_secret' => 'ai-secret',
            'timeout' => 30,
            'ai_retry_times' => 2,
            'ai_retry_delay_ms' => 0,
            'max_audio_kb' => 10240,
        ]);
        config()->set('features.ai', true);
        config()->set('features.voice', true);
    }

    private function learner(): User
    {
        return User::factory()->create();
    }

    public function test_translate_success_passes_through_upstream_response(): void
    {
        Http::fake([
            'ai.lexilingo.test/*' => Http::response([
                'translated_text' => 'Xin chào',
                'source_lang' => 'en',
                'target_lang' => 'vi',
            ]),
        ]);

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/translate', [
                'text' => 'Hello',
                'target_lang' => 'vi',
            ])
            ->assertOk()
            ->assertJsonPath('data.translated_text', 'Xin chào');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://ai.lexilingo.test/api/v1/ai/translate'
            && $request->data()['text'] === 'Hello'
            && $request->hasHeader('X-AI-Service-Secret', 'ai-secret'));
    }

    public function test_translate_validates_required_fields(): void
    {
        Http::fake();

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/translate', ['text' => 'Hello'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_lang']);

        Http::assertNothingSent();
    }

    public function test_translate_upstream_timeout_returns_504(): void
    {
        Http::fake([
            'ai.lexilingo.test/*' => fn () => throw new ConnectionException('Connection timed out.'),
        ]);

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/translate', ['text' => 'Hello', 'target_lang' => 'vi'])
            ->assertStatus(504)
            ->assertJsonPath('code', 'UPSTREAM_TIMEOUT')
            ->assertJsonMissing(['message' => 'Connection timed out.']);
    }

    public function test_translate_upstream_4xx_returns_422_without_leaking_upstream_body(): void
    {
        Http::fake([
            'ai.lexilingo.test/*' => Http::response(['detail' => 'unsupported language pair'], 400),
        ]);

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/translate', ['text' => 'Hello', 'target_lang' => 'vi'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'UPSTREAM_REJECTED')
            ->assertJsonMissing(['detail' => 'unsupported language pair']);
    }

    public function test_translate_upstream_5xx_returns_502_without_leaking_upstream_body(): void
    {
        Http::fake([
            'ai.lexilingo.test/*' => Http::response(['detail' => 'stack trace leaked here'], 500),
        ]);

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/translate', ['text' => 'Hello', 'target_lang' => 'vi'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'UPSTREAM_ERROR')
            ->assertJsonMissing(['detail' => 'stack trace leaked here']);
    }

    public function test_translate_preserves_upstream_rate_limit(): void
    {
        Http::fake([
            'ai.lexilingo.test/*' => Http::response([], 429, ['Retry-After' => '30']),
        ]);

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/translate', ['text' => 'Hello', 'target_lang' => 'vi'])
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('code', 'UPSTREAM_RATE_LIMITED');
    }

    public function test_translate_retries_transient_failure_then_succeeds(): void
    {
        Http::fake([
            'ai.lexilingo.test/*' => Http::sequence()
                ->push(['detail' => 'temporary'], 500)
                ->push(['translated_text' => 'Xin chào'], 200),
        ]);

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/translate', ['text' => 'Hello', 'target_lang' => 'vi'])
            ->assertOk()
            ->assertJsonPath('data.translated_text', 'Xin chào');

        Http::assertSentCount(2);
    }

    public function test_ai_routes_are_rate_limited(): void
    {
        Http::fake(['ai.lexilingo.test/*' => Http::response(['translated_text' => 'ok'])]);

        $user = $this->learner();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)
                ->postJson('/api/v1/ai/translate', ['text' => 'Hello', 'target_lang' => 'vi'])
                ->assertOk();
        }

        $this->actingAs($user)
            ->postJson('/api/v1/ai/translate', ['text' => 'Hello', 'target_lang' => 'vi'])
            ->assertStatus(429);
    }

    public function test_pronunciation_rejects_oversized_or_wrong_mime_audio(): void
    {
        Http::fake();

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/pronunciation', [
                'audio' => UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
                'reference_text' => 'hello',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/pronunciation', [
                'audio' => UploadedFile::fake()->create('word.mp3', 20000, 'audio/mpeg'),
                'reference_text' => 'hello',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);

        Http::assertNothingSent();
    }

    public function test_pronunciation_for_an_active_session_persists_learning_evidence(): void
    {
        Http::fake([
            'ai.lexilingo.test/*' => Http::response(['pronunciation_score' => 82]),
        ]);
        $user = $this->learner();
        $course = Course::create(['title' => 'Pronunciation course', 'slug' => 'pronunciation-course']);
        $lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Pronunciation lesson', 'slug' => 'pronunciation-lesson']);
        $vocabulary = Vocabulary::create(['lesson_id' => $lesson->id, 'word' => 'hello', 'meaning' => 'xin chào']);
        $session = LearningSession::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->withHeader('X-Request-ID', 'd91fd697-283d-473d-ab63-32e72b4ba1ac')
            ->post('/api/v1/ai/pronunciation', [
                'audio' => UploadedFile::fake()->create('word.wav', 100, 'audio/wav'),
                'reference_text' => 'hello',
                'session_id' => $session->id,
                'activity_id' => "vocabulary:{$vocabulary->id}",
            ])
            ->assertOk()
            ->assertJsonPath('data.pronunciation_score', 82);

        $this->assertDatabaseHas('learning_events', [
            'learning_session_id' => $session->id,
            'user_id' => $user->id,
            'event_type' => 'pronunciation',
            'pronunciation_score' => 82,
            'request_id' => 'd91fd697-283d-473d-ab63-32e72b4ba1ac',
        ]);
    }

    public function test_speech_to_text_success_passes_through_upstream_response(): void
    {
        Http::fake([
            'ai.lexilingo.test/*' => Http::response(['text' => 'hello world', 'confidence' => 0.97]),
        ]);

        $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/speech-to-text', [
                'audio' => UploadedFile::fake()->create('word.wav', 100, 'audio/wav'),
            ])
            ->assertOk()
            ->assertJsonPath('data.text', 'hello world');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://ai.lexilingo.test/api/v1/stt/transcribe'
            && $request->hasFile('audio', filename: 'word.wav')
            && str_contains($request->body(), 'Content-Type: audio/wav'));
    }

    public function test_text_to_speech_returns_binary_audio_with_content_type(): void
    {
        Http::fake([
            'ai.lexilingo.test/*' => Http::response('FAKE_AUDIO_BYTES', 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $response = $this->actingAs($this->learner())
            ->postJson('/api/v1/ai/text-to-speech', ['text' => 'Hello world']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertSame('FAKE_AUDIO_BYTES', $response->getContent());
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        Http::fake();

        $this->postJson('/api/v1/ai/translate', ['text' => 'Hello', 'target_lang' => 'vi'])
            ->assertUnauthorized();

        Http::assertNothingSent();
    }
}
