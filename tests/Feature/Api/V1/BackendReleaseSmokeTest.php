<?php

namespace Tests\Feature\Api\V1;

use App\Models\AdminImportRun;
use App\Models\Course;
use App\Models\Level;
use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackendReleaseSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_guests_receive_401_on_all_protected_endpoints(): void
    {
        $this->seed();

        $protectedRoutes = [
            ['GET', '/api/v1/admin/imports'],
            ['GET', '/api/v1/admin/imports/runs'],
            ['GET', '/api/v1/admin/users'],
            ['GET', '/api/v1/admin/roles'],
            ['GET', '/api/v1/admin/catalog/courses'],
            ['GET', '/api/v1/admin/catalog/lessons'],
            ['GET', '/api/v1/admin/catalog/quizzes'],
            ['GET', '/api/v1/admin/operations'],
            ['GET', '/api/v1/teacher/learners'],
            ['GET', '/api/v1/teacher/alerts'],
            ['GET', '/api/v1/teacher/assignments'],
            ['GET', '/api/v1/auth/me'],
            ['GET', '/api/v1/progress'],
            ['GET', '/api/v1/learner-imports'],
        ];

        foreach ($protectedRoutes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertUnauthorized();
        }
    }

    public function test_learner_role_access_boundaries(): void
    {
        $this->seed();
        $learner = $this->user('learner');

        // Allowed for learner
        $this->actingAs($learner)->getJson('/api/v1/auth/me')->assertOk();
        $this->actingAs($learner)->getJson('/api/v1/catalog/topics')->assertOk();
        $this->actingAs($learner)->getJson('/api/v1/vocabulary')->assertOk();
        $this->actingAs($learner)->getJson('/api/v1/learner-imports')->assertOk();

        // Forbidden for learner
        $this->actingAs($learner)->getJson('/api/v1/teacher/learners')->assertForbidden();
        $this->actingAs($learner)->getJson('/api/v1/admin/catalog/courses')->assertForbidden();
        $this->actingAs($learner)->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAs($learner)->getJson('/api/v1/admin/imports')->assertForbidden();
        $this->actingAs($learner)->getJson('/api/v1/admin/roles')->assertForbidden();
    }

    public function test_teacher_role_access_boundaries(): void
    {
        $this->seed();
        $teacher = $this->user('teacher');

        // Allowed for teacher
        $this->actingAs($teacher)->getJson('/api/v1/teacher/learners')->assertOk();
        $this->actingAs($teacher)->getJson('/api/v1/teacher/alerts')->assertOk();
        $this->actingAs($teacher)->getJson('/api/v1/teacher/assignments')->assertOk();

        // Forbidden for teacher
        $this->actingAs($teacher)->getJson('/api/v1/admin/catalog/courses')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/v1/admin/imports')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/v1/admin/roles')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/v1/admin/operations')->assertForbidden();
    }

    public function test_admin_role_access_boundaries(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $course = $this->createCourse();

        // Allowed for admin (manage-content, manage-users)
        $this->actingAs($admin)->getJson('/api/v1/admin/catalog/courses')->assertOk();
        $this->actingAs($admin)->getJson("/api/v1/admin/catalog/units?course_id={$course->id}")->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/admin/catalog/lessons')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/admin/catalog/quizzes')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/admin/users')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/admin/imports')->assertOk();

        // Forbidden for admin (super_admin only actions like apply import, role management, teacher assignment)
        $run = $this->makeRun($admin, 'categories');
        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", [])
            ->assertForbidden();

        $targetUser = $this->user('learner');
        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$targetUser->id}/role", ['role' => 'learner'])
            ->assertForbidden();

        $this->actingAs($admin)->getJson('/api/v1/admin/operations/teacher-assignments')->assertForbidden();
    }

    public function test_super_admin_role_full_capabilities(): void
    {
        $this->seed();
        $superAdmin = $this->user('super_admin');
        $targetUser = $this->user('learner');

        $this->actingAs($superAdmin)->getJson('/api/v1/admin/users')->assertOk();
        $this->actingAs($superAdmin)->getJson('/api/v1/admin/roles')->assertOk();
        $this->actingAs($superAdmin)->getJson('/api/v1/admin/operations/teacher-assignments')->assertOk();

        $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$targetUser->id}/role", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_401_403_404_does_not_leak_resource_existence_to_unauthorized_users(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $existingRun = $this->makeRun($admin, 'categories');
        $nonExistentRunId = 999999;

        // Guest check: always 401 for both existing and non-existent resource
        $this->getJson("/api/v1/admin/imports/runs/{$existingRun->id}")->assertUnauthorized();
        $this->getJson("/api/v1/admin/imports/runs/{$nonExistentRunId}")->assertUnauthorized();

        // Unauthorized user check (Learner): receives 403 on protected admin endpoints
        $learner = $this->user('learner');
        $this->actingAs($learner)->getJson('/api/v1/admin/imports')->assertForbidden();
        $this->actingAs($learner)->getJson('/api/v1/admin/imports/runs')->assertForbidden();

        // Authorized user check (Admin): 200 for existing, 404 with standard envelope for non-existent
        $this->actingAs($admin)->getJson("/api/v1/admin/imports/runs/{$existingRun->id}")->assertOk();
        $this->actingAs($admin)->getJson("/api/v1/admin/imports/runs/{$nonExistentRunId}")
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_403_does_not_leak_user_existence_on_role_updates(): void
    {
        $this->seed();
        $learner = $this->user('learner');
        $existingUser = $this->user('teacher');
        $nonExistentUserId = 888888;

        // Unauthorized role (Learner) attempting to view or change roles gets 403
        $this->actingAs($learner)->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAs($learner)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$existingUser->id}/role", ['role' => 'admin'])->assertForbidden();

        // Authorized Super Admin gets 404 for non-existent user
        $superAdmin = $this->user('super_admin');
        $this->actingAs($superAdmin)->getJson("/api/v1/admin/users/{$nonExistentUserId}")
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_import_workflow_smoke_for_all_roles(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $superAdmin = $this->user('super_admin');
        $learner = $this->user('learner');
        $teacher = $this->user('teacher');

        // Admin and Super Admin can access import checkpoints & runs
        $this->actingAs($admin)->getJson('/api/v1/admin/imports')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/admin/imports/runs')->assertOk();

        $this->actingAs($superAdmin)->getJson('/api/v1/admin/imports')->assertOk();
        $this->actingAs($superAdmin)->getJson('/api/v1/admin/imports/runs')->assertOk();

        // Learner and Teacher cannot access admin imports
        $this->actingAs($learner)->getJson('/api/v1/admin/imports')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/v1/admin/imports')->assertForbidden();

        // Learner can access learner imports endpoint
        $this->actingAs($learner)->getJson('/api/v1/learner-imports')->assertOk();
    }

    public function test_role_management_workflow_smoke(): void
    {
        $this->seed();
        $superAdmin = $this->user('super_admin');
        $admin = $this->user('admin');
        $targetUser = $this->user('learner');

        // Super Admin lists roles and changes user role
        $this->actingAs($superAdmin)->getJson('/api/v1/admin/roles')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$targetUser->id}/role", [
                'role' => 'teacher',
            ])->assertOk()->assertJsonPath('data.role', 'teacher');

        // Admin cannot assign roles
        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/users/{$targetUser->id}/role", [
                'role' => 'teacher',
            ])->assertForbidden();
    }

    public function test_course_lesson_quiz_workflow_smoke(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $learner = $this->user('learner');

        $level = Level::first();
        $topic = Topic::first();

        // Admin creates a course via catalog API
        $courseData = [
            'title' => 'Smoke Test Course',
            'slug' => 'smoke-test-course',
            'level_id' => $level->id,
            'topic_id' => $topic->id,
            'description' => 'Course description for smoke test',
            'status' => 'draft',
        ];

        $res = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/catalog/courses', $courseData)
            ->assertCreated();

        $courseId = $res->json('data.id');
        $this->assertNotNull($courseId);

        // Learner can view published courses in public catalog
        $course = Course::find($courseId);
        $course->update(['status' => 'published']);

        $this->actingAs($learner)->getJson("/api/v1/catalog/courses/{$course->id}")->assertOk();
    }

    public function test_feature_off_workflow_behavior_and_authorization_precedence(): void
    {
        $this->seed();
        $learner = $this->user('learner');
        $admin = $this->user('admin');
        $superAdmin = $this->user('super_admin');

        // Disable features
        config([
            'features.ai' => false,
            'features.voice' => false,
            'features.lexilingo_import_apply' => false,
        ]);

        // Authenticated learner calling AI translate when feature is off gets 503
        $this->actingAs($learner)->postJson('/api/v1/ai/translate', [
            'text' => 'Hello',
            'target_lang' => 'vi',
        ])->assertStatus(503)->assertJsonPath('code', 'FEATURE_DISABLED');

        // Authorization check takes precedence over feature-off check for apply:
        // Admin gets 403 (authorization failed) rather than 503 (feature off) so feature flag is not leaked
        $run = $this->makeRun($admin, 'categories');

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", [])
            ->assertForbidden();

        // Super Admin (authorized role) gets 503 Service Unavailable when feature is off
        $this->actingAs($superAdmin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/imports/runs/{$run->id}/apply", [])
            ->assertStatus(503);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('slug', $role)->value('id')]);
    }

    private function createCourse(): Course
    {
        return Course::create([
            'title' => 'Test Course '.Str::random(5),
            'slug' => 'test-course-'.Str::random(5),
            'level_id' => Level::first()->id,
            'topic_id' => Topic::first()->id,
            'status' => 'published',
        ]);
    }

    private function makeRun(User $admin, string $entity): AdminImportRun
    {
        return AdminImportRun::create([
            'request_id' => (string) Str::uuid(),
            'entity' => $entity,
            'payload_fingerprint' => hash('sha256', Str::uuid()),
            'actor_id' => $admin->id,
            'status' => 'review-ready',
            'requested_limit' => 50,
            'reset' => false,
            'starting_cursor' => 0,
            'processed' => 1,
            'skipped' => 0,
            'result_cursor' => 1,
        ]);
    }
}
