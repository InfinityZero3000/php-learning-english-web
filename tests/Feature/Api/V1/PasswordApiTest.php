<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_is_neutral_and_reset_updates_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
            ->assertOk();
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'missing@example.com'])
            ->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(password_verify('new-password', $user->fresh()->password));
    }

    public function test_reset_notification_targets_the_frontend_query_contract(): void
    {
        config(['app.frontend_url' => 'https://learner.test']);
        Notification::fake();
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertStringStartsWith('https://learner.test/reset-password?', $url);
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame($user->email, $query['email']);
            $this->assertNotEmpty($query['token']);

            return true;
        });
    }
}
