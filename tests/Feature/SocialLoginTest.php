<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_a_verified_learner_session(): void
    {
        config(['app.frontend_url' => 'https://learner.test']);
        $this->seed();
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(
            (new SocialUser)->map(['name' => 'Google Learner', 'email' => 'google@example.com']),
        );
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->withSession(['google_oauth_return' => '/progress'])
            ->get('/auth/google/callback')
            ->assertRedirect('https://learner.test/progress');

        $user = User::where('email', 'google@example.com')->firstOrFail();
        $this->assertSame(Role::where('slug', 'learner')->value('id'), $user->role_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_api_google_login_route_preserves_only_internal_return_paths(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->times(3)->andReturn(redirect('https://accounts.google.test'));
        Socialite::shouldReceive('driver')->with('google')->times(3)->andReturn($provider);

        $this->get('/api/v1/auth/oauth/google?next=%2Fprogress')
            ->assertRedirect('https://accounts.google.test')
            ->assertSessionHas('google_oauth_return', '/progress');

        $this->get('/api/v1/auth/oauth/google?next=https%3A%2F%2Fevil.test')
            ->assertRedirect('https://accounts.google.test')
            ->assertSessionHas('google_oauth_return', '/profile');

        $this->get('/api/v1/auth/oauth/google?next=%2F%2Fevil.test')
            ->assertRedirect('https://accounts.google.test')
            ->assertSessionHas('google_oauth_return', '/profile');
    }

    public function test_facebook_callback_uses_frontend_destination_and_safe_error_code(): void
    {
        config(['app.frontend_url' => 'https://learner.test']);
        $this->seed();
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(
            (new SocialUser)->map(['name' => 'Facebook Learner', 'email' => 'facebook@example.com']),
        );
        Socialite::shouldReceive('driver')->with('facebook')->once()->andReturn($provider);
        $this->get('/auth/facebook/callback')->assertRedirect('https://learner.test/profile');

        $failing = Mockery::mock();
        $failing->shouldReceive('user')->once()->andThrow(new \RuntimeException('provider secret text'));
        Socialite::shouldReceive('driver')->with('facebook')->once()->andReturn($failing);
        $this->get('/auth/facebook/callback')->assertRedirect('https://learner.test/login?error=oauth_failed');
    }
}
