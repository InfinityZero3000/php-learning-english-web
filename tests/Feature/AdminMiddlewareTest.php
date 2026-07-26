<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_can_open_user_management(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Quản lý người dùng');
    }

    public function test_learner_cannot_open_user_management(): void
    {
        $this->seed();
        $learner = User::factory()->create(['role_id' => Role::where('slug', 'learner')->value('id')]);

        $this->actingAs($learner)->get('/admin/users')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect(config('app.admin_frontend_url').'/login');
    }

    public function test_admin_can_update_a_users_role(): void
    {
        $this->seed();
        $adminRole = Role::where('slug', 'admin')->value('id');
        $learnerRole = Role::where('slug', 'learner')->value('id');
        $admin = User::factory()->create(['role_id' => $adminRole]);
        $user = User::factory()->create(['role_id' => $learnerRole]);

        $this->actingAs($admin)
            ->patch("/admin/users/{$user->id}/role", ['role_id' => $adminRole])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role_id' => $adminRole]);
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $this->seed();
        $adminRole = Role::where('slug', 'admin')->value('id');
        $learnerRole = Role::where('slug', 'learner')->value('id');
        $admin = User::factory()->create(['role_id' => $adminRole]);

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/role", ['role_id' => $learnerRole])
            ->assertSessionHasErrors('role_id');
    }
}
