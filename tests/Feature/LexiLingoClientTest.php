<?php

namespace Tests\Feature;

use App\Support\LexiLingoClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LexiLingoClientTest extends TestCase
{
    public function test_backend_and_ai_clients_use_server_side_urls_and_credentials(): void
    {
        config()->set('services.lexilingo', [
            'backend_url' => 'https://backend.lexilingo.test/',
            'ai_url' => 'https://ai.lexilingo.test/',
            'partner_api_key' => 'partner-secret',
            'ai_service_secret' => 'ai-secret',
            'timeout' => 30,
        ]);
        Http::fake([
            'backend.lexilingo.test/*' => Http::response(['status' => 'ok']),
            'ai.lexilingo.test/*' => Http::response(['status' => 'ok']),
        ]);

        $client = app(LexiLingoClient::class);
        $client->backend()->get('/health')->throw();
        $client->partner()->get('/api/v1/integrations/courses')->throw();
        $client->ai()->get('/health')->throw();
        $client->internalAi()->get('/api/v1/internal/content-agent/sources')->throw();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://backend.lexilingo.test/health'
            && ! $request->hasHeader('X-Import-Key'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/integrations/courses')
            && $request->hasHeader('X-LexiLingo-API-Key', 'partner-secret'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://ai.lexilingo.test/health'
            && ! $request->hasHeader('X-AI-Service-Secret'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/internal/content-agent/')
            && $request->hasHeader('X-AI-Service-Secret', 'ai-secret'));
    }

    public function test_missing_url_fails_before_any_request_is_sent(): void
    {
        config()->set('services.lexilingo.backend_url');
        Http::fake();

        try {
            app(LexiLingoClient::class)->backend();
            $this->fail('Missing LexiLingo URL was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('LexiLingo backend_url is not configured.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_insecure_remote_url_is_rejected(): void
    {
        config()->set('services.lexilingo.backend_url', 'http://lexilingo.example');

        $this->expectException(RuntimeException::class);
        app(LexiLingoClient::class)->backend();
    }

    public function test_partner_retries_rate_limits_and_server_errors(): void
    {
        config()->set('services.lexilingo.backend_url', 'https://backend.lexilingo.test');
        config()->set('services.lexilingo.partner_api_key', 'partner-secret');
        config()->set('services.lexilingo.import_delay_ms', 0);
        config()->set('services.lexilingo.import_max_backoff_ms', 0);
        config()->set('services.lexilingo.import_max_retries', 2);

        Http::fakeSequence()
            ->push([], 429, ['Retry-After' => '0'])
            ->push([], 503)
            ->push(['data' => [['id' => 'ok']]], 200);

        $response = app(LexiLingoClient::class)->partner()->get('/api/v1/integrations/categories');

        $response->throw();
        $this->assertSame('ok', $response->json('data.0.id'));
        Http::assertSentCount(3);
    }
}
