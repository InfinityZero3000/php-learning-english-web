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
        $this->get('/admin/users')->assertRedirect('/login');
    }
}
