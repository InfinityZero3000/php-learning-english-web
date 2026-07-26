<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use App\Support\SafeFrontendPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class OAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_frontend_paths_are_normalized(): void
    {
        foreach (['/', '/profile', '/profile?tab=words'] as $path) {
            $this->assertSame($path, SafeFrontendPath::normalize($path));
        }

        foreach ([null, '', 'https://evil.test', '//evil.test', '/login', '/login/help', '/auth/callback', '/profile\\bad', "/profile\n"] as $path) {
            $this->assertSame('/', SafeFrontendPath::normalize($path));
        }
    }

    public function test_entry_allows_known_providers_and_stores_safe_destination(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.example/authorize'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get('/api/v1/auth/oauth/google?next=%2Fprofile')
            ->assertRedirect('https://accounts.example/authorize')
            ->assertSessionHas('auth.oauth_next', '/profile');

        $this->get('/api/v1/auth/oauth/github')->assertNotFound();
    }

    public function test_callback_creates_verified_learner_and_consumes_destination(): void
    {
        $learner = Role::create(['name' => 'Learner', 'slug' => 'learner']);
        $this->mockSocialUser('google', 'new@example.com', 'New Learner');

        $response = $this->withSession(['auth.oauth_next' => '/profile?tab=words'])
            ->get('/api/v1/auth/oauth/google/callback');

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $response->assertRedirect('http://localhost:3000/auth/callback?next=%2Fprofile%3Ftab%3Dwords')
            ->assertSessionMissing('auth.oauth_next');
        $this->assertSame($learner->id, $user->role_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_logs_in_existing_learner(): void
    {
        $learner = Role::create(['name' => 'Learner', 'slug' => 'learner']);
        $user = User::factory()->create(['role_id' => $learner->id, 'email' => 'known@example.com']);
        $this->mockSocialUser('facebook', $user->email, 'Ignored Name');

        $this->get('/api/v1/auth/oauth/facebook/callback')->assertRedirect('http://localhost:3000/auth/callback?next=%2F');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('known@example.com', $user->fresh()->email);
    }

    public function test_callback_rejects_existing_non_learner_without_mutation(): void
    {
        $admin = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user = User::factory()->create(['role_id' => $admin->id, 'email' => 'admin@example.com', 'name' => 'Admin']);
        $this->mockSocialUser('google', $user->email, 'Changed');

        $this->withSession(['auth.oauth_next' => '/profile'])
            ->get('/api/v1/auth/oauth/google/callback')
            ->assertRedirect('http://localhost:3000/login?oauth_error=role_conflict')
            ->assertSessionMissing('auth.oauth_next');

        $this->assertGuest();
        $this->assertSame('Admin', $user->fresh()->name);
    }

    public function test_callback_maps_provider_failures(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->withSession(['auth.oauth_next' => '/profile'])
            ->get('/api/v1/auth/oauth/google/callback')
            ->assertRedirect('http://localhost:3000/login?oauth_error=invalid_state')
            ->assertSessionMissing('auth.oauth_next');
        $this->assertGuest();
    }

    public function test_callback_maps_cancellation_and_missing_email(): void
    {
        $this->get('/api/v1/auth/oauth/google/callback?error=access_denied')
            ->assertRedirect('http://localhost:3000/login?oauth_error=cancelled');

        $this->mockSocialUser('facebook', null, 'No Email');
        $this->get('/api/v1/auth/oauth/facebook/callback')
            ->assertRedirect('http://localhost:3000/login?oauth_error=email_missing');
        $this->assertGuest();
    }

    private function mockSocialUser(string $providerName, ?string $email, ?string $name): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(
            (new SocialUser)->map(['name' => $name, 'email' => $email]),
        );
        Socialite::shouldReceive('driver')->with($providerName)->once()->andReturn($provider);
    }
}
