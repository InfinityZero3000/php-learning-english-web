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
                'role' => 'teacher',
            ])->assertOk()->assertJsonPath('data.role', 'teacher');
        $this->withHeader('X-Request-ID', $scopeRequestId)
            ->postJson('/api/v1/admin/operations/teacher-assignments', [
                'teacher_id' => $teacher->id, 'learner_id' => $learner->id,
            ])->assertCreated()->assertJsonPath('data.learner.id', $learner->id);
        $this->withHeader('X-Request-ID', $scopeRequestId)
            ->postJson('/api/v1/admin/operations/teacher-assignments', [
                'teacher_id' => $teacher->id, 'learner_id' => $learner->id,
            ])->assertOk()->assertJsonPath('data.learner.id', $learner->id);
        $this->assertDatabaseHas('teacher_assignments', [
            'teacher_id' => $teacher->id, 'learner_id' => $learner->id,
        ]);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$learner->id}/role", [
                'role' => 'admin',
            ])->assertOk();
        $this->assertDatabaseMissing('teacher_assignments', [
            'teacher_id' => $teacher->id, 'learner_id' => $learner->id,
        ]);
        $this->assertDatabaseCount('operations_audits', 3);
    }

    public function test_role_and_scope_mutations_authorize_before_requiring_fresh_google_verification(): void
    {
        $this->seed();
        $target = $this->user('learner');
        $teacher = $this->user('teacher');

        $this->actingAs($this->user('admin'));
        session()->forget('google_admin_reauthenticated');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$target->id}/role", ['role' => 'teacher'])
            ->assertForbidden();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/operations/teacher-assignments', [
                'teacher_id' => $teacher->id, 'learner_id' => $target->id,
            ])->assertForbidden();

        $super = $this->user('super_admin');
        $this->actingAs($super);
        session()->put('google_admin_reauthenticated.at', now()->subMinutes(16)->timestamp);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$target->id}/role", ['role' => 'teacher'])
            ->assertStatus(428)->assertJsonPath('message', 'Recent Google verification is required.');

        $this->actingAs($super);
        session()->put('google_admin_reauthenticated.subject', 'another-google-subject');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/operations/teacher-assignments', [
                'teacher_id' => $teacher->id, 'learner_id' => $target->id,
            ])->assertStatus(428);

        $this->actingAs($super)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$target->id}/role", ['role' => 'teacher'])
            ->assertOk()->assertJsonPath('data.role', 'teacher');
        $this->assertDatabaseMissing('teacher_assignments', ['learner_id' => $target->id]);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', $role)->value('id'),
        ]);
    }
}
