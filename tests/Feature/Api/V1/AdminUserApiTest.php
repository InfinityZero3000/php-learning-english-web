<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users_but_cannot_manage_roles_or_teacher_scope(): void
    {
        $this->seed();
        $admin = $this->user('admin');

        $this->actingAs($admin)->getJson('/api/v1/admin/users')->assertOk();
        $this->getJson('/api/v1/admin/roles')->assertForbidden();
        $this->getJson('/api/v1/admin/operations/teacher-assignments')->assertForbidden();
    }

    public function test_super_admin_can_change_role_and_assign_teacher_scope(): void
    {
        $this->seed();
        $superAdmin = $this->user('super_admin');
        $teacher = $this->user('learner');
        $learner = $this->user('learner');
        $scopeRequestId = (string) Str::uuid();

        $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$teacher->id}/role", [
                'role' => 'teacher', 'password' => 'password',
            ])->assertOk()->assertJsonPath('data.role', 'teacher');
        $this->withHeader('X-Request-ID', $scopeRequestId)
            ->postJson('/api/v1/admin/operations/teacher-assignments', [
                'teacher_id' => $teacher->id, 'learner_id' => $learner->id, 'password' => 'password',
            ])->assertCreated()->assertJsonPath('data.learner.id', $learner->id);
        $this->withHeader('X-Request-ID', $scopeRequestId)
            ->postJson('/api/v1/admin/operations/teacher-assignments', [
                'teacher_id' => $teacher->id, 'learner_id' => $learner->id, 'password' => 'password',
            ])->assertOk()->assertJsonPath('data.learner.id', $learner->id);
        $this->assertDatabaseHas('teacher_assignments', [
            'teacher_id' => $teacher->id, 'learner_id' => $learner->id,
        ]);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$learner->id}/role", [
                'role' => 'admin', 'password' => 'password',
            ])->assertOk();
        $this->assertDatabaseMissing('teacher_assignments', [
            'teacher_id' => $teacher->id, 'learner_id' => $learner->id,
        ]);
        $this->assertDatabaseCount('operations_audits', 3);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'password' => 'password',
            'role_id' => Role::where('slug', $role)->value('id'),
        ]);
    }
}
