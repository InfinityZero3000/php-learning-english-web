<?php

namespace Tests\Feature\Api\V1;

use App\Models\MediaListeningSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaListeningApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'features.youtube_listening' => true,
            'services.lexilingo.backend_url' => 'https://partner.lexilingo.test',
            'services.lexilingo.partner_api_key' => 'server-secret',
        ]);
    }

    public function test_feature_can_be_disabled(): void
    {
        config(['features.youtube_listening' => false]);

        $this->actingAs(User::factory()->create())->getJson('/api/v1/media/youtube/channels')
            ->assertStatus(503)->assertJsonPath('code', 'FEATURE_DISABLED');
        Http::assertNothingSent();
    }

    public function test_search_forwards_only_allowlisted_query_and_caps_page_size(): void
    {
        Http::fake(['partner.lexilingo.test/*' => Http::response(['items' => [], 'next_page_token' => 'next'])]);

        $this->actingAs(User::factory()->create())->getJson('/api/v1/media/youtube/search?q=english&per_page=999&secret=leak')
            ->assertOk()->assertJsonPath('data.next_page_token', 'next');

        Http::assertSent(fn ($request) => $request->hasHeader('X-LexiLingo-API-Key', 'server-secret')
            && $request['q'] === 'english' && (int) $request['per_page'] === 50 && ! isset($request['secret']));
    }

    public function test_progress_is_owned_idempotent_and_requires_watch_and_shadowing(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $payload = ['external_id' => 'video-1', 'title' => 'Listening one', 'duration_seconds' => 100];

        $first = $this->actingAs($owner)->postJson('/api/v1/media/listening-sessions', $payload)->assertOk()->json('data');
        $second = $this->actingAs($owner)->postJson('/api/v1/media/listening-sessions', $payload)->assertOk()->json('data');
        $this->assertSame($first['id'], $second['id']);

        $this->actingAs($other)->putJson("/api/v1/media/listening-sessions/{$first['id']}", ['position_seconds' => 80, 'duration_seconds' => 100])->assertForbidden();
        $this->actingAs($owner)->putJson("/api/v1/media/listening-sessions/{$first['id']}", ['position_seconds' => 80, 'duration_seconds' => 100])->assertOk()->assertJsonPath('data.watched_percentage', 80);
        $this->actingAs($owner)->postJson("/api/v1/media/listening-sessions/{$first['id']}/complete")->assertUnprocessable();

        MediaListeningSession::findOrFail($first['id'])->update(['shadowing_attempts' => 1]);
        $this->actingAs($owner)->postJson("/api/v1/media/listening-sessions/{$first['id']}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->actingAs($owner)->postJson("/api/v1/media/listening-sessions/{$first['id']}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_empty_captions_rate_limit_and_timeout_are_safe(): void
    {
        $user = User::factory()->create();
        Http::fake(['partner.lexilingo.test/*' => Http::sequence()
            ->push(['items' => []])
            ->push(['detail' => 'quota and secret internals'], 429, ['Retry-After' => '30'])
            ->pushFailedConnection('timeout')]);
        $this->actingAs($user)->getJson('/api/v1/media/youtube/videos/no-captions/captions')
            ->assertOk()->assertJsonCount(0, 'data.items');
        $this->actingAs($user)->getJson('/api/v1/media/youtube/channels')
            ->assertStatus(429)->assertHeader('Retry-After', '30')->assertJsonMissing(['detail' => 'quota and secret internals']);
        $this->actingAs($user)->getJson('/api/v1/media/youtube/channels')
            ->assertStatus(504)->assertJsonPath('code', 'UPSTREAM_TIMEOUT');
    }

    public function test_degraded_pronunciation_does_not_count_shadowing(): void
    {
        Http::fake(['ai.lexilingo.test/*' => Http::response(['detail' => 'model internals'], 503)]);
        config(['services.lexilingo.ai_url' => 'https://ai.lexilingo.test', 'services.lexilingo.ai_service_secret' => 'voice-secret']);
        $user = User::factory()->create();
        $session = MediaListeningSession::create(['user_id' => $user->id, 'source' => 'youtube', 'external_id' => 'video', 'title' => 'Video']);

        $this->actingAs($user)->post("/api/v1/media/listening-sessions/{$session->id}/pronunciation", [
            'audio' => UploadedFile::fake()->create('voice.wav', 10, 'audio/wav'),
            'reference_text' => 'Hello',
        ])->assertStatus(502)->assertJsonMissing(['detail' => 'model internals']);

        $this->assertSame(0, $session->fresh()->shadowing_attempts);
    }
}
