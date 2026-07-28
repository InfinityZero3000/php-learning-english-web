<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_capabilities_are_role_and_scope_specific(): void
    {
        $this->seed();

        $learner = $this->user('learner');
        $otherLearner = $this->user('learner');
        $teacher = $this->user('teacher');
        $admin = $this->user('admin');
        $superAdmin = $this->user('super_admin');

        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'learner_id' => $learner->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($teacher->hasRole('teacher'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-content'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-content'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($admin)->allows('manage-roles'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-roles'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('assign-teachers'));
        $this->assertFalse(Gate::forUser($admin)->allows('manage-operations'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-operations'));
        $this->assertTrue(Gate::forUser($teacher)->allows('view-learning-evidence', $learner));
        $this->assertFalse(Gate::forUser($teacher)->allows('view-learning-evidence', $otherLearner));
        $this->assertFalse(Gate::forUser($admin)->allows('view-learning-evidence', $learner));
    }

    public function test_admin_cannot_grant_privileged_roles_but_recently_google_verified_super_admin_can(): void
    {
        $this->seed();

        $admin = $this->user('admin');
        $superAdmin = $this->user('super_admin');
        $learner = $this->user('learner');
        $teacherRole = Role::where('slug', 'teacher')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.users.updateRole', $learner), [
                'role_id' => $teacherRole->id,
                'password' => 'password',
            ])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->patch(route('admin.users.updateRole', $learner), [
                'role_id' => $teacherRole->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($learner->fresh()->hasRole('teacher'));

        $secondLearner = $this->user('learner');
        $this->patch(route('admin.users.updateRole', $secondLearner), [
            'role_id' => $teacherRole->id,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue($secondLearner->fresh()->hasRole('teacher'));
        $this->assertSame(900, config('auth.password_timeout'));

    }

    public function test_super_admin_cannot_demote_self_or_final_super_admin(): void
    {
        $this->seed();

        $superAdmin = $this->user('super_admin');
        $learnerRole = Role::where('slug', 'learner')->firstOrFail();

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->patch(route('admin.users.updateRole', $superAdmin), [
                'role_id' => $learnerRole->id,
            ])
            ->assertForbidden();

        $this->assertTrue($superAdmin->fresh()->hasRole('super_admin'));
    }

    public function test_whitelist_role_is_resynchronized_before_authorization(): void
    {
        $this->seed();

        $superAdmin = $this->user('super_admin')->load('role');
        $target = $this->user('learner');
        $teacherRole = Role::where('slug', 'teacher')->firstOrFail();
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $this->actingAs($superAdmin)->withSession([
            'auth.password_confirmed_at' => now()->timestamp,
        ]);

        DB::table('users')->where('id', $superAdmin->id)->update(['role_id' => $adminRole->id]);

        $this->patch(route('admin.users.updateRole', $target), [
            'role_id' => $teacherRole->id,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue($target->fresh()->hasRole('teacher'));
        $this->assertTrue($superAdmin->fresh()->hasRole('super_admin'));
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', $role)->value('id'),
        ]);
    }
}
