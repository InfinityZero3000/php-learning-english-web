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

    public function test_production_ui_entries_redirect_to_the_learner_frontend(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.frontend_url' => 'https://linguist-nova.vercel.app']);

        $this->get('/')->assertRedirect('https://linguist-nova.vercel.app');
        $this->get('/login')->assertRedirect('https://linguist-nova.vercel.app/login');
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_admin_requires_an_authenticated_admin(): void
    {
        $this->get('/admin')->assertRedirect(config('app.admin_frontend_url').'/login');
    }

    public function test_api_status_endpoint_is_available(): void
    {
        $this->getJson('/api/status')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'version' => 'v1']);
    }
}
