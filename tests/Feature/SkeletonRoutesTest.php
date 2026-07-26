<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkeletonRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('English Learning')
            ->assertDontSee('LexiLingo');
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_admin_requires_an_authenticated_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_api_status_endpoint_is_available(): void
    {
        $this->getJson('/api/status')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'version' => 'v1']);
    }
}
