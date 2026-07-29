<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_verified_login_use_session_auth(): void
    {
        $this->seed();
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Learner',
            'email' => 'learner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('data.message', 'Vui lòng kiểm tra email để xác minh tài khoản.');

        $user = User::where('email', 'learner@example.com')->firstOrFail();
        $this->assertSame(Role::where('slug', 'learner')->value('id'), $user->role_id);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertForbidden()->assertJsonPath('code', 'EMAIL_UNVERIFIED');

        $user->markEmailAsVerified();
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('data.role', 'learner');

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_signed_email_verification_accepts_only_valid_unexpired_links(): void
    {
        config(['app.frontend_url' => 'https://learner.test']);
        $user = User::factory()->unverified()->create();
        $parameters = ['id' => $user->id, 'hash' => sha1($user->email)];
        $valid = URL::temporarySignedRoute('api.verification.verify', now()->addMinute(), $parameters);
        $this->get($valid)->assertRedirect('https://learner.test/login?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);

        $expiredUser = User::factory()->unverified()->create();
        $expired = URL::temporarySignedRoute('api.verification.verify', now()->subMinute(), [
            'id' => $expiredUser->id, 'hash' => sha1($expiredUser->email),
        ]);
        $this->get($expired)->assertForbidden();
        $this->assertNull($expiredUser->fresh()->email_verified_at);
    }
}
