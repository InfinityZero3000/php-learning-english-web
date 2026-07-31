<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Issue #45's stageOnly() split is scoped to 'categories' only. These tests
 * guard that courses/vocabulary/lessons keep writing directly through the
 * real HTTP import endpoint, exactly as they did before this issue.
 */
class AdminImportStageOnlyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_courses_run_still_writes_immediately_via_http(): void
    {
        $this->seed();
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
        Http::fake([
            'http://localhost/api/v1/integrations/courses/course-1' => Http::response(['data' => [
                'id' => 'course-1', 'units' => [],
            ]]),
            'http://localhost/api/v1/integrations/courses*' => Http::response([[
                'id' => 'course-1', 'title' => 'English Basics', 'description' => 'Intro course',
                'language' => 'en', 'level' => 'beginner', 'tags' => [], 'thumbnail_url' => null,
                'total_lessons' => 0, 'total_xp' => 0, 'estimated_duration' => 30,
            ]]),
        ]);
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'courses', 'limit' => 50])
            ->assertStatus(202);

        $this->assertDatabaseHas('admin_import_runs', ['id' => $response->json('data.id'), 'status' => 'review-ready']);
        $this->assertDatabaseHas('courses', ['external_id' => 'course-1', 'title' => 'English Basics']);
    }

    public function test_vocabulary_run_still_writes_immediately_via_http(): void
    {
        $this->seed();
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
        Http::fake([
            'http://localhost/api/v1/integrations/vocabulary/items*' => Http::response(['data' => [[
                'id' => 'vocab-1', 'course_id' => null, 'lesson_id' => null, 'word' => 'hello',
                'definition' => 'a greeting', 'translation' => ['vi' => 'xin chào'], 'pronunciation' => null,
                'audio_url' => null, 'part_of_speech' => 'interjection', 'difficulty_level' => 'easy',
                'tags' => [], 'usage_frequency' => 10,
            ]]]),
        ]);
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'vocabulary', 'limit' => 50])
            ->assertStatus(202);

        $this->assertDatabaseHas('admin_import_runs', ['id' => $response->json('data.id'), 'status' => 'review-ready']);
        $this->assertDatabaseHas('vocabularies', ['external_id' => 'vocab-1', 'word' => 'hello']);
    }

    public function test_lessons_run_stages_visibility_without_disabling_its_direct_write(): void
    {
        $this->seed();
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.import_key', 'import-secret');
        $course = Course::create(['external_id' => 'course-ext-1', 'title' => 'Course', 'slug' => 'course-'.Str::random(6), 'language' => 'en']);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'external_id' => 'lesson-ext-1',
            'title' => 'Lesson', 'slug' => 'lesson-'.Str::random(6), 'sort_order' => 1,
        ]);
        Http::fake([
            'http://localhost/api/v1/admin/lessons/lesson-ext-1' => Http::response(['data' => [
                'description' => null, 'prerequisites' => [], 'estimated_minutes' => 10,
                'pass_threshold' => 60, 'content' => ['text' => 'Hello world'],
            ]]),
        ]);
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'lessons', 'limit' => 50])
            ->assertStatus(202);
        $runId = $response->json('data.id');

        $this->assertDatabaseHas('admin_import_runs', ['id' => $runId, 'status' => 'review-ready']);
        // Direct write unchanged: content landed on the lesson row itself.
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'estimated_minutes' => 10, 'pass_threshold' => 60]);
        // Staging is new visibility on top, not a gate: always classified
        // 'update' since the lesson already existed locally.
        $this->assertDatabaseHas('staged_items', [
            'admin_import_run_id' => $runId, 'external_id' => 'lesson-ext-1', 'classification' => 'update',
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('slug', $role)->value('id')]);
    }
}
