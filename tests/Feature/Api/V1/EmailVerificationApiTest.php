<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_verification_marks_user_verified_and_redirects_to_frontend(): void
    {
        config(['app.frontend_url' => 'https://frontend.example']);
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('api.verification.verify', now()->addMinute(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect('https://frontend.example/login?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_invalid_verification_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get("/api/v1/auth/email/verify/{$user->id}/".sha1($user->email).'?signature=bad')
            ->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_is_privacy_safe(): void
    {
        $this->postJson('/api/v1/auth/email/resend')->assertOk()
            ->assertJsonPath('data.message', 'Nếu tài khoản cần xác minh, email đã được gửi.');
    }
}
