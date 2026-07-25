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

        $this->get('/auth/google/callback')->assertRedirect('/profile');

        $user = User::where('email', 'google@example.com')->firstOrFail();
        $this->assertSame(Role::where('slug', 'learner')->value('id'), $user->role_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }
}
