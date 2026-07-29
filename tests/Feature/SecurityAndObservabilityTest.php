<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityAndObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_context_middleware_adds_request_id_to_response_headers(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $response->assertHeader('X-Request-ID');
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    public function test_log_context_middleware_replaces_invalid_request_id(): void
    {
        $response = $this->withHeader('X-Request-ID', 'forged-log')
            ->getJson('/health');

        $this->assertNotSame('forged-log', $response->headers->get('X-Request-ID'));
        $this->assertTrue(\Illuminate\Support\Str::isUuid($response->headers->get('X-Request-ID')));
    }

    public function test_log_context_middleware_includes_user_context_when_authenticated(): void
    {
        $user = User::factory()->create([
            'email' => 'testlog@example.com',
        ]);

        // Mock Log withContext to intercept the context values
        $capturedContext = null;
        Log::shouldReceive('withContext')
            ->once()
            ->with(\Mockery::on(function ($context) use (&$capturedContext, $user) {
                $capturedContext = $context;

                return isset($context['request_id'])
                    && $context['user_id'] === $user->id
                    && $context['method'] === 'GET'
                    && str_contains($context['url'], '/api/v1/auth/me');
            }))
            ->andReturnSelf();

        // Also mock standard log calls to prevent issues with other code logging during bootstrap/routing
        Log::shouldReceive('info', 'warning', 'error', 'debug', 'share', 'write')->byDefault();

        $this->actingAs($user)->getJson('/api/v1/auth/me');

        $this->assertNotNull($capturedContext);
        $this->assertSame($user->id, $capturedContext['user_id']);
    }

    public function test_auth_endpoints_rate_limiting(): void
    {
        // Hit registration endpoint multiple times to trigger rate limit (throttle:5,1)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', [
                'name' => 'Thư',
                'email' => "thu{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        // 6th attempt must return 429 Too Many Requests
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Thư Limit',
            'email' => 'thulimit@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    }
}
