<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_for_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'a@example.com']);

        $response = $this->post(route('password.email'), ['email' => 'a@example.com']);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('sentEmail', 'a@example.com');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_request_fails_for_unknown_email(): void
    {
        $response = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        $response->assertSessionHasErrors('email');
    }

    public function test_reset_form_can_be_displayed(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        $token = Password::createToken($user);

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => 'a@example.com']));

        $response->assertOk();
        $response->assertSee('a@example.com', false);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => Hash::make('old-password')]);
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'a@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => Hash::make('old-password')]);

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'a@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
