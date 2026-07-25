<?php

namespace Tests\Feature\Api\V1;

use App\Support\HealthCheck;
use RuntimeException;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    public function test_health_endpoints_and_csrf_cookie_respond(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'version' => 'v1']);

        $this->get('/api/v1/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_health_reports_database_failure(): void
    {
        $this->app->instance(HealthCheck::class, $this->failingHealth(database: true));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'error', 'checks' => ['database' => 'down']]);
    }

    public function test_versioned_health_reports_redis_failure(): void
    {
        config()->set('cache.default', 'redis');
        $this->app->instance(HealthCheck::class, $this->failingHealth(redis: true));

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'error',
                'version' => 'v1',
                'checks' => ['redis' => 'down'],
            ]);
    }

    public function test_health_reports_combined_failure(): void
    {
        config()->set('queue.default', 'redis');
        $this->app->instance(HealthCheck::class, $this->failingHealth(database: true, redis: true));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'error',
                'checks' => ['database' => 'down', 'redis' => 'down'],
            ]);
    }

    private function failingHealth(bool $database = false, bool $redis = false): HealthCheck
    {
        return new class($database, $redis) extends HealthCheck
        {
            public function __construct(
                private readonly bool $database,
                private readonly bool $redis,
            ) {}

            protected function checkDatabase(): void
            {
                if ($this->database) {
                    throw new RuntimeException('database unavailable');
                }
            }

            protected function checkRedis(): void
            {
                if ($this->redis) {
                    throw new RuntimeException('redis unavailable');
                }
            }
        };
    }
}
