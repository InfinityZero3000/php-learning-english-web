<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class ProductionFrontendRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'https://frontend.example']);
        $this->app->detectEnvironment(fn () => 'production');
    }

    public function test_learner_pages_redirect_to_frontend(): void
    {
        $this->get('/')->assertRedirect('https://frontend.example');
        $this->get('/login')->assertRedirect('https://frontend.example/login');
        $this->get('/register')->assertRedirect('https://frontend.example/register');
        $this->get('/forgot-password')->assertRedirect('https://frontend.example/forgot-password');
        $this->get('/reset-password/token?email=a%2Bb%40example.com')
            ->assertRedirect('https://frontend.example/reset-password?token=token&email=a%2Bb%40example.com');
        $this->get('/verify-email')->assertRedirect('https://frontend.example/verify-email');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertRedirect('https://frontend.example/profile');
        $this->actingAs($user)->get('/progress')->assertRedirect('https://frontend.example/progress');
        $this->actingAs($user)->get('/words')->assertRedirect('https://frontend.example/words');
    }

    public function test_api_and_admin_boundaries_remain_on_laravel(): void
    {
        $this->get('/api/v1/health')->assertOk();
        $this->get('/admin')->assertRedirect('/login');

        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('api.verification.verify', now()->addMinute(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $this->get($url)->assertRedirect('https://frontend.example/login?verified=1');
    }

    public function test_oauth_callback_reaches_api_controller(): void
    {
        Role::create(['name' => 'Learner', 'slug' => 'learner']);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(
            (new SocialUser)->map(['name' => 'Learner', 'email' => 'oauth@example.com']),
        );
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get('/api/v1/auth/oauth/google/callback')
            ->assertRedirect('https://frontend.example/auth/callback?next=%2F');
    }
}
