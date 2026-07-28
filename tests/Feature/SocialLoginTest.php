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
        $this->seed();
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(
            (new SocialUser)->map(['name' => 'Google Learner', 'email' => 'google@example.com']),
        );
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->withSession(['google_oauth_return' => '/progress'])
            ->get('/auth/google/callback')
            ->assertRedirect('/progress');

        $user = User::where('email', 'google@example.com')->firstOrFail();
        $this->assertSame(Role::where('slug', 'learner')->value('id'), $user->role_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_api_google_login_route_preserves_only_internal_return_paths(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->twice()->andReturn(redirect('https://accounts.google.test'));
        Socialite::shouldReceive('driver')->with('google')->twice()->andReturn($provider);

        $this->get('/api/v1/auth/oauth/google?next=%2Fprogress')
            ->assertRedirect('https://accounts.google.test')
            ->assertSessionHas('google_oauth_return', '/progress');

        $this->get('/api/v1/auth/oauth/google?next=https%3A%2F%2Fevil.test')
            ->assertRedirect('https://accounts.google.test')
            ->assertSessionHas('google_oauth_return', '/profile');
    }
}
