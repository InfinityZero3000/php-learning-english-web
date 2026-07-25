<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthApiTest extends TestCase
{
    public function test_versioned_health_endpoint_responds(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'version' => 'v1']);
    }
}
