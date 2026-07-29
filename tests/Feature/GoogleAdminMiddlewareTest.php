<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class GoogleAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_authenticated_admin_without_google_marker_is_rejected_and_demoted(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'admin')->value('id'),
            'email' => 'admin@example.com',
        ]);
        config()->set('admin_access.admin_emails', ['admin@example.com']);
        config()->set('admin_access.super_admin_emails', []);

        Auth::login($user);
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();

        $this->assertSame('learner', $user->fresh()->role->slug);
        $this->assertGuest();
    }

    public function test_marker_is_rechecked_and_role_is_synchronized_on_every_request(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'admin')->value('id'),
            'email' => 'owner@example.com',
            'google_id' => 'google-owner',
            'auth_provider' => 'google',
        ]);
        config()->set('admin_access.admin_emails', []);
        config()->set('admin_access.super_admin_emails', ['owner@example.com']);

        Auth::login($user);
        $this->withSession(['google_admin' => [
            'user_id' => $user->id,
            'subject' => 'google-owner',
            'email' => 'owner@example.com',
        ]])->getJson('/api/v1/admin/users')->assertOk();

        $this->assertSame('super_admin', $user->fresh()->role->slug);
    }
}
