<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\CourseCategory;
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
 * real HTTP import endpoint, exactly as they did before this issue — and that
 * the two write models stay distinguishable once both record staged items:
 * 'staged' is a pending approval, 'applied' is history for an already-live row.
 */
class AdminImportStageOnlyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_courses_run_still_writes_immediately_via_http(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
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
        config()->set('features.lexilingo_import', true);
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
        config()->set('features.lexilingo_import', true);
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-secret');
        // Provider-owned rows: the content importer only touches lessons whose
        // source_system is 'lexilingo', so a locally authored lesson carrying the
        // same external id is never overwritten.
        $course = Course::create(['source_system' => 'lexilingo', 'external_id' => 'course-ext-1', 'title' => 'Course', 'slug' => 'course-'.Str::random(6), 'language' => 'en']);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'source_system' => 'lexilingo', 'external_id' => 'lesson-ext-1',
            'title' => 'Lesson', 'slug' => 'lesson-'.Str::random(6), 'sort_order' => 1,
        ]);
        Http::fake([
            'http://localhost/api/v1/integrations/lessons/lesson-ext-1/content' => Http::response(['data' => [
                'id' => 'lesson-ext-1', 'title' => 'Lesson', 'description' => 'Hello world',
                'lesson_type' => 'vocabulary', 'order_index' => 1, 'xp_reward' => 10,
                'estimated_minutes' => 10, 'pass_threshold' => 60,
                'total_exercises' => 0, 'exercises' => [],
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

    /**
     * A staged row and a directly-written row must not look the same to a
     * reviewer. Only the staged one is pending approval; the other is already
     * live and is kept purely as an audit trail.
     */
    public function test_staged_and_direct_write_entities_are_distinguishable_by_status(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
        Http::fake([
            'http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [[
                'id' => 'cat-1', 'name' => 'Everyday', 'slug' => 'everyday',
                'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
            ]]]),
            'http://localhost/api/v1/integrations/courses/course-1' => Http::response(['data' => ['id' => 'course-1', 'units' => []]]),
            'http://localhost/api/v1/integrations/courses*' => Http::response([[
                'id' => 'course-1', 'title' => 'English Basics', 'description' => null,
                'language' => 'en', 'level' => 'beginner', 'tags' => [], 'thumbnail_url' => null,
                'total_lessons' => 0, 'total_xp' => 0, 'estimated_duration' => 30,
            ]]),
        ]);
        $admin = $this->user('admin');
        $this->actingAs($admin);

        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])->assertStatus(202);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'courses', 'limit' => 50])->assertStatus(202);

        // Staged: awaiting approval, nothing written.
        $this->assertDatabaseHas('staged_items', ['external_id' => 'cat-1', 'status' => 'staged']);
        $this->assertDatabaseMissing('course_categories', ['external_id' => 'cat-1']);
        // Direct: already live, recorded only as history.
        $this->assertDatabaseHas('staged_items', ['external_id' => 'course-1', 'status' => 'applied']);
        $this->assertDatabaseHas('courses', ['external_id' => 'course-1', 'title' => 'English Basics']);
    }

    /**
     * `conflict` and `unchanged` were declared in the staged-item vocabulary
     * but never produced. Catalog ownership metadata is what makes them real.
     */
    public function test_local_override_stages_as_conflict_and_identical_payload_as_unchanged(): void
    {
        $this->seed();
        config()->set('features.lexilingo_import', true);
        config()->set('services.lexilingo.backend_url', 'http://localhost');
        config()->set('services.lexilingo.partner_api_key', 'partner-test');
        $payload = [
            'id' => 'cat-1', 'name' => 'Upstream name', 'slug' => 'upstream-slug',
            'description' => null, 'icon' => null, 'color' => null, 'course_count' => 0,
        ];
        Http::fake(['http://localhost/api/v1/integrations/categories*' => Http::response(['data' => [$payload]])]);
        $attributes = [
            'name' => $payload['name'], 'slug' => $payload['slug'],
            'description' => null, 'icon' => null, 'color' => null,
        ];
        [$category] = CourseCategory::syncFromSource('category', 'lexilingo', 'cat-1', $attributes);
        $admin = $this->user('admin');
        $this->actingAs($admin);

        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50])->assertStatus(202);
        $this->assertDatabaseHas('staged_items', ['external_id' => 'cat-1', 'classification' => 'unchanged']);

        $category->applyLocalEdit(['name' => 'Locally renamed']);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/imports', ['entity' => 'categories', 'limit' => 50, 'reset' => true])
            ->assertStatus(202);

        $this->assertDatabaseHas('staged_items', ['external_id' => 'cat-1', 'classification' => 'conflict']);
        $this->assertSame('Locally renamed', $category->fresh()->name);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('slug', $role)->value('id')]);
    }
}
