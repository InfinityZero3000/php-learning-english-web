<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_gets_learner_role_and_verification_email(): void
    {
        $this->seed();
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Nguyen Van A',
            'email' => 'a@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('verify_email', 'a@example.com');

        $user = User::where('email', 'a@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertSame(Role::query()->where('slug', 'learner')->value('id'), $user->role_id);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verify_notice_page_shows_registered_email(): void
    {
        $response = $this->withSession(['verify_email' => 'a@example.com'])
            ->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('a@example.com');
    }

    public function test_verify_notice_redirects_to_register_without_pending_email(): void
    {
        $response = $this->get(route('verification.notice'));

        $response->assertRedirect(route('register'));
    }

    public function test_user_can_resend_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);

        $response = $this->withSession(['verify_email' => 'a@example.com'])
            ->post(route('verification.send'));

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('success');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verification_email_resend_is_rate_limited(): void
    {
        Notification::fake();
        User::factory()->unverified()->create(['email' => 'a@example.com']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->withSession(['verify_email' => 'a@example.com'])
                ->post(route('verification.send'))
                ->assertRedirect(route('verification.notice'));
        }

        $this->withSession(['verify_email' => 'a@example.com'])
            ->post(route('verification.send'))
            ->assertTooManyRequests();
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertSame(0, User::count());
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post(route('register.store'), [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_fails_when_learner_role_is_missing(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Nguyen Van A',
            'email' => 'a@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'a@example.com']);
        Notification::assertNothingSent();
    }

    public function test_verification_link_marks_email_as_verified_without_login(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Guest, chưa đăng nhập — vì user chưa xác minh thì chưa thể đăng nhập được.
        $response = $this->get($url);

        $response->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertGuest();
        Event::assertDispatched(Verified::class, fn (Verified $event) => $event->user->is($user));
    }

    public function test_verification_link_does_not_emit_duplicate_event(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->get($url)->assertRedirect(route('login'));

        Event::assertNotDispatched(Verified::class);
    }

    public function test_verification_link_rejects_wrong_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('someone-else@example.com')]
        );

        $response = $this->get($url);

        $response->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
