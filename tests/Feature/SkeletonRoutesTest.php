<?php

namespace Tests\Feature;

use Tests\TestCase;

class SkeletonRoutesTest extends TestCase
{
    public function test_home_redirects_to_the_learner_frontend(): void
    {
        config(['app.frontend_url' => 'https://learner.test']);

        $this->get('/')->assertRedirect('https://learner.test/');
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_admin_requires_authentication(): void
    {
        config(['app.admin_frontend_url' => 'https://admin.test']);

        $this->get('/admin')->assertRedirect('https://admin.test/login');
    }

    public function test_api_status_endpoint_is_available(): void
    {
        $this->getJson('/api/status')->assertOk()->assertExactJson(['status' => 'ok', 'version' => 'v1']);
    }
}
