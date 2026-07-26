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

    public function test_reset_notification_points_to_frontend_with_encoded_parameters(): void
    {
        config(['app.frontend_url' => 'https://frontend.example']);
        Notification::fake();
        $user = User::factory()->create(['email' => 'learner+test@example.com']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return str_starts_with($url, 'https://frontend.example/reset-password?')
                && $query['token'] === $notification->token
                && $query['email'] === $user->email;
        });
    }
}
