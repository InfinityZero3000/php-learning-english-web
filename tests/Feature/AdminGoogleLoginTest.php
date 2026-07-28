<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class AdminGoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_whitelisted_google_email_gets_identity_bound_super_admin_session(): void
    {
        $this->seed();
        config()->set('admin_access.admin_emails', []);
        config()->set('admin_access.super_admin_emails', ['owner@example.com']);
        config()->set('app.admin_frontend_url', 'http://admin.test');
        $this->mockGoogleUser('google-subject-1', 'owner@example.com', true);
        $challenge = $this->challenge();

        $callback = $this->withSession([
            'google_admin_oauth_mode' => 'login',
            'google_admin_challenge' => $challenge,
        ])->get('/auth/google/callback');
        $callback->assertRedirectContains('http://admin.test/login?handoff=');
        parse_str((string) parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/v1/auth/oauth/google/admin/handoff', [
            'handoff' => $query['handoff'],
        ])->assertOk()
            ->assertJsonPath('data.return', '/dashboard')
            ->assertJsonPath('data.user.role', 'super_admin')
            ->assertSessionHas('google_admin.email', 'owner@example.com');

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame('google-subject-1', $user->google_id);
        $this->assertSame('google', $user->auth_provider);
        $this->assertSame(Role::query()->where('slug', 'super_admin')->value('id'), $user->role_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);

        $this->postJson('/api/v1/auth/oauth/google/admin/handoff', [
            'handoff' => $query['handoff'],
        ])->assertUnauthorized();
    }

    public function test_unlisted_or_unverified_email_is_denied_without_session(): void
    {
        $this->seed();
        config()->set('admin_access.admin_emails', ['admin@example.com']);
        config()->set('admin_access.super_admin_emails', []);
        config()->set('app.admin_frontend_url', 'http://admin.test');
        $this->mockGoogleUser('google-subject-2', 'other@example.com', true);
        $challenge = $this->challenge();

        $this->withSession([
            'google_admin_oauth_mode' => 'login',
            'google_admin_challenge' => $challenge,
        ])->get('/auth/google/callback')
            ->assertRedirect('http://admin.test/login?error=denied')
            ->assertSessionMissing('google_admin');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }

    private function challenge(): string
    {
        $challenge = (string) Str::uuid();
        Cache::put("admin-google-challenge:{$challenge}", [
            'return' => '/dashboard',
            'subject' => null,
        ], now()->addMinutes(5));

        return $challenge;
    }

    private function mockGoogleUser(string $id, string $email, bool $verified): void
    {
        $socialUser = (new SocialUser)->map([
            'id' => $id,
            'name' => 'Google Admin',
            'email' => $email,
        ]);
        $socialUser->user = ['email_verified' => $verified];
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
}
