<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUnitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_reorder_and_replay_units(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $course = $this->course();
        $payload = [
            'course_id' => $course->id,
            'title' => 'Foundations',
            'description' => 'Start here',
            'sort_order' => 0,
            'icon_url' => 'book',
            'background_color' => '#E8F4F8',
        ];
        $requestId = (string) Str::uuid();

        $first = $this->actingAs($admin)->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/admin/catalog/units', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.source_system', 'local')
            ->assertJsonPath('data.catalog_revision', 1);
        $unitId = $first->json('data.id');

        $this->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/admin/catalog/units', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $unitId);
        $this->assertDatabaseCount('units', 1);

        $this->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/admin/catalog/units', [...$payload, 'title' => 'Different'])
            ->assertConflict();

        $secondId = $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/catalog/units', [...$payload, 'title' => 'Practice', 'sort_order' => 1])
            ->assertCreated()
            ->json('data.id');
        $otherCourse = $this->course('other');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->putJson("/api/v1/admin/catalog/units/{$unitId}", [...$payload, 'course_id' => $otherCourse->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_id');
        $updateId = (string) Str::uuid();
        $this->withHeader('X-Request-ID', $updateId)
            ->putJson("/api/v1/admin/catalog/units/{$unitId}", [...$payload, 'title' => 'Core foundations'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Core foundations');
        $this->withHeader('X-Request-ID', $updateId)
            ->putJson("/api/v1/admin/catalog/units/{$unitId}", [...$payload, 'title' => 'Core foundations'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Core foundations');

        $reorderId = (string) Str::uuid();
        $this->withHeader('X-Request-ID', $reorderId)
            ->putJson('/api/v1/admin/catalog/units/reorder', [
                'course_id' => $course->id,
                'unit_ids' => [$secondId, $unitId],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $secondId)
            ->assertJsonPath('data.1.id', $unitId);
        $this->withHeader('X-Request-ID', $reorderId)
            ->putJson('/api/v1/admin/catalog/units/reorder', [
                'course_id' => $course->id,
                'unit_ids' => [$secondId, $unitId],
            ])
            ->assertOk();

        $this->getJson("/api/v1/admin/catalog/units?course_id={$course->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $secondId);
        $this->assertNotNull(Unit::findOrFail($unitId)->local_override_at);
    }

    public function test_unit_lifecycle_and_delete_guards_are_enforced(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $course = $this->course();
        $unit = Unit::create(['course_id' => $course->id, 'title' => 'Unit', 'sort_order' => 0, 'status' => 'draft']);

        $this->actingAs($admin)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/catalog/units/{$unit->id}/publish")
            ->assertConflict();

        $course->update(['status' => 'published']);
        Lesson::create([
            'course_id' => $course->id,
            'unit_id' => $unit->id,
            'title' => 'Lesson',
            'slug' => 'lesson',
            'sort_order' => 0,
            'status' => 'published',
        ]);
        $publishId = (string) Str::uuid();
        $this->withHeader('X-Request-ID', $publishId)
            ->postJson("/api/v1/admin/catalog/units/{$unit->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
        $this->withHeader('X-Request-ID', $publishId)
            ->postJson("/api/v1/admin/catalog/units/{$unit->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->deleteJson("/api/v1/admin/catalog/units/{$unit->id}")
            ->assertConflict();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/admin/catalog/units/{$unit->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $empty = Unit::create(['course_id' => $course->id, 'title' => 'Empty', 'sort_order' => 1, 'status' => 'draft']);
        $deleteId = (string) Str::uuid();
        $this->withHeader('X-Request-ID', $deleteId)
            ->deleteJson("/api/v1/admin/catalog/units/{$empty->id}")
            ->assertNoContent();
        $this->withHeader('X-Request-ID', $deleteId)
            ->deleteJson("/api/v1/admin/catalog/units/{$empty->id}")
            ->assertNoContent();
    }

    public function test_lesson_unit_and_prerequisite_graph_are_validated_and_replaced(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $course = $this->course();
        $otherCourse = $this->course('other');
        $unit = Unit::create(['course_id' => $course->id, 'title' => 'Unit', 'sort_order' => 0, 'status' => 'draft']);
        $otherUnit = Unit::create(['course_id' => $otherCourse->id, 'title' => 'Other', 'sort_order' => 0, 'status' => 'draft']);
        $first = $this->lesson($course, 'first');
        $second = $this->lesson($course, 'second');
        $foreign = $this->lesson($otherCourse, 'foreign');

        $payload = [
            'course_id' => $course->id,
            'unit_id' => $unit->id,
            'title' => 'Third',
            'slug' => 'third',
            'content' => null,
            'sort_order' => 2,
            'estimated_minutes' => 10,
            'status' => 'draft',
            'prerequisite_ids' => [$first->id, $second->id],
        ];
        $thirdId = $this->actingAs($admin)->postJson('/api/v1/admin/catalog/lessons', $payload)
            ->assertCreated()
            ->assertJsonPath('data.unit_id', $unit->id)
            ->assertJsonPath('data.prerequisite_ids.0', $first->id)
            ->json('data.id');

        $this->putJson("/api/v1/admin/catalog/lessons/{$thirdId}", [...$payload, 'prerequisite_ids' => [$second->id]])
            ->assertOk()
            ->assertJsonPath('data.prerequisite_ids', [$second->id]);
        $this->assertDatabaseMissing('lesson_prerequisites', ['lesson_id' => $thirdId, 'prerequisite_lesson_id' => $first->id]);

        $this->putJson("/api/v1/admin/catalog/lessons/{$thirdId}", [...$payload, 'unit_id' => $otherUnit->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unit_id');
        $this->putJson("/api/v1/admin/catalog/lessons/{$thirdId}", [
            ...$payload,
            'course_id' => $otherCourse->id,
            'unit_id' => $otherUnit->id,
            'prerequisite_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->putJson("/api/v1/admin/catalog/lessons/{$thirdId}", [...$payload, 'prerequisite_ids' => [$foreign->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prerequisite_ids');
        $this->putJson("/api/v1/admin/catalog/lessons/{$thirdId}", [...$payload, 'prerequisite_ids' => [$thirdId]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prerequisite_ids');
        $this->putJson("/api/v1/admin/catalog/lessons/{$second->id}", [
            ...$payload,
            'title' => $second->title,
            'slug' => $second->slug,
            'sort_order' => $second->sort_order,
            'prerequisite_ids' => [$thirdId],
        ])->assertUnprocessable()->assertJsonValidationErrors('prerequisite_ids');
    }

    public function test_learner_cannot_access_unit_administration(): void
    {
        $this->seed();
        $learner = $this->user('learner');
        $course = $this->course();

        $this->actingAs($learner)->getJson("/api/v1/admin/catalog/units?course_id={$course->id}")->assertForbidden();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/admin/catalog/units', [
                'course_id' => $course->id,
                'title' => 'Denied',
                'sort_order' => 0,
            ])->assertForbidden();
    }

    public function test_compatibility_lesson_api_preserves_course_hierarchy(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $course = $this->course();
        $otherCourse = $this->course('other');
        $unit = Unit::create(['course_id' => $course->id, 'title' => 'Unit', 'sort_order' => 0, 'status' => 'draft']);
        $otherUnit = Unit::create(['course_id' => $otherCourse->id, 'title' => 'Other', 'sort_order' => 0, 'status' => 'draft']);

        $this->actingAs($admin)->postJson('/api/v1/admin/lessons', [
            'course_id' => $course->id,
            'unit_id' => $otherUnit->id,
            'title' => 'Wrong unit',
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_id');

        $lessonId = $this->postJson('/api/v1/admin/lessons', [
            'course_id' => $course->id,
            'unit_id' => $unit->id,
            'title' => 'Valid lesson',
        ])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/admin/lessons/{$lessonId}", [
            'course_id' => $otherCourse->id,
            'unit_id' => $otherUnit->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->putJson("/api/v1/admin/lessons/{$lessonId}", [
            'unit_id' => $otherUnit->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]);
    }

    private function course(string $slug = 'course'): Course
    {
        return Course::create(['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'draft', 'language' => 'en']);
    }

    private function lesson(Course $course, string $slug): Lesson
    {
        return Lesson::create([
            'course_id' => $course->id,
            'title' => ucfirst($slug),
            'slug' => $slug,
            'sort_order' => $course->lessons()->count(),
            'status' => 'draft',
        ]);
    }
}
