<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        $this->assertNull($user->fresh()->last_login_at);
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('data.role', 'learner');
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_locked_account_cannot_log_in(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'role_id' => Role::where('slug', 'learner')->value('id'),
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'locked_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertForbidden()->assertJsonPath('code', 'ACCOUNT_LOCKED');

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
