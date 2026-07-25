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
            'import_key' => 'import-secret',
            'ai_service_secret' => 'ai-secret',
            'timeout' => 30,
        ]);
        Http::fake([
            'backend.lexilingo.test/*' => Http::response(['status' => 'ok']),
            'ai.lexilingo.test/*' => Http::response(['status' => 'ok']),
        ]);

        $client = app(LexiLingoClient::class);
        $client->backend()->get('/health')->throw();
        $client->protectedBackend()->get('/api/v1/admin/lessons/lesson-1')->throw();
        $client->ai()->get('/health')->throw();
        $client->internalAi()->get('/api/v1/internal/content-agent/sources')->throw();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://backend.lexilingo.test/health'
            && ! $request->hasHeader('X-Import-Key'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/admin/lessons/')
            && $request->hasHeader('X-Import-Key', 'import-secret'));
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
}
