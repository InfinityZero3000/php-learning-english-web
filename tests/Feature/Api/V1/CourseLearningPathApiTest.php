<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\Lesson;
use App\Models\Progress;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseLearningPathApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_path_returns_ordered_tree_completion_prerequisites_and_next_action(): void
    {
        [$user, $course, $enrollment, $first, $second, $third] = $this->context();
        Progress::create(['user_id' => $user->id, 'lesson_id' => $first->id]);

        $path = $this->actingAs($user)->getJson("/api/v1/catalog/courses/{$course->id}/path")
            ->assertOk()
            ->assertJsonPath('data.course.id', $course->id)
            ->assertJsonPath('data.enrollment.id', $enrollment->id)
            ->assertJsonCount(2, 'data.units')
            ->assertJsonPath('data.units.0.lessons.0.id', $first->id)
            ->assertJsonPath('data.units.0.lessons.0.status', 'available')
            ->assertJsonPath('data.units.0.lessons.1.id', $second->id)
            ->assertJsonPath('data.units.0.lessons.1.status', 'locked')
            ->assertJsonPath('data.units.0.lessons.1.prerequisites.0.completed', false)
            ->assertJsonPath('data.progress.completed_lessons', 0)
            ->assertJsonPath('data.next_action.lesson_id', $first->id);

        Progress::where('user_id', $user->id)->where('lesson_id', $first->id)
            ->update(['completed_at' => now()]);
        $this->getJson("/api/v1/catalog/courses/{$course->id}/path")
            ->assertOk()
            ->assertJsonPath('data.units.0.lessons.0.status', 'completed')
            ->assertJsonPath('data.units.0.lessons.1.status', 'available')
            ->assertJsonPath('data.next_action.type', 'start_lesson')
            ->assertJsonPath('data.next_action.lesson_id', $second->id)
            ->assertJsonPath('data.progress.completed_lessons', 1);

        $older = LearningSession::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $second->id,
            'status' => 'active',
            'started_at' => now()->subMinute(),
        ]);
        $latest = LearningSession::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $third->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $this->getJson("/api/v1/catalog/courses/{$course->id}/path")
            ->assertJsonPath('data.next_action.type', 'resume_session')
            ->assertJsonPath('data.next_action.session_id', $latest->id)
            ->assertJsonMissing(['session_id' => $older->id]);
    }

    public function test_session_start_uses_server_eligibility_and_binds_every_request_id(): void
    {
        [$user, $course, $enrollment, $first, $second] = $this->context();
        $otherUser = User::factory()->create();
        $otherEnrollment = Enrollment::create([
            'user_id' => $otherUser->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $second->id,
            ])->assertConflict();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $otherEnrollment->id,
                'lesson_id' => $first->id,
            ])->assertForbidden();

        Progress::create(['user_id' => $user->id, 'lesson_id' => $first->id, 'completed_at' => now()]);
        $firstRequest = (string) Str::uuid();
        $sessionId = $this->withHeader('X-Request-ID', $firstRequest)
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $second->id,
            ])->assertCreated()->assertJsonPath('data.lesson_id', $second->id)->json('data.id');
        $this->withHeader('X-Request-ID', $firstRequest)
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $second->id,
            ])->assertCreated()->assertJsonPath('data.id', $sessionId);

        $secondRequest = (string) Str::uuid();
        $this->withHeader('X-Request-ID', $secondRequest)
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $second->id,
            ])->assertCreated()->assertJsonPath('data.id', $sessionId);
        $this->assertDatabaseCount('learning_sessions', 1);
        $this->assertSame(2, LearningEvent::whereIn('request_id', [$firstRequest, $secondRequest])->count());

        $this->withHeader('X-Request-ID', $secondRequest)
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
            ])->assertConflict();
    }

    public function test_path_requires_auth_and_a_published_course(): void
    {
        [, $course] = $this->context();
        $this->getJson("/api/v1/catalog/courses/{$course->id}/path")->assertUnauthorized();

        $course->update(['status' => 'draft']);
        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/catalog/courses/{$course->id}/path")
            ->assertNotFound();
    }

    public function test_legacy_unassigned_lessons_are_visible_in_the_path(): void
    {
        $user = User::factory()->create();
        $course = Course::create([
            'title' => 'Legacy',
            'slug' => 'legacy-'.Str::random(8),
            'status' => 'published',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Legacy lesson',
            'slug' => 'legacy-lesson-'.Str::random(6),
            'sort_order' => 0,
            'status' => 'published',
        ]);
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user)->getJson("/api/v1/catalog/courses/{$course->id}/path")
            ->assertOk()
            ->assertJsonCount(1, 'data.units')
            ->assertJsonPath('data.units.0.id', null)
            ->assertJsonPath('data.units.0.title', 'General')
            ->assertJsonPath('data.units.0.lessons.0.id', $lesson->id)
            ->assertJsonPath('data.progress.total_lessons', 1)
            ->assertJsonPath('data.next_action.lesson_id', $lesson->id);
    }

    public function test_lifecycle_blocks_new_course_sessions_but_keeps_an_active_archived_unit_session(): void
    {
        [$user, $course, $enrollment, $first] = $this->context();
        $sessionId = $this->actingAs($user)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $first->id,
            ])->assertCreated()->json('data.id');

        $first->unit->update(['status' => 'archived']);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $first->id,
            ])->assertCreated()->assertJsonPath('data.id', $sessionId);

        $course->update(['status' => 'archived']);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $first->id,
            ])->assertConflict();
    }

    public function test_completion_uses_only_lessons_from_published_units(): void
    {
        [$user, , $enrollment, $first, $second, $third] = $this->context();
        Progress::create(['user_id' => $user->id, 'lesson_id' => $first->id, 'completed_at' => now()]);
        Progress::create(['user_id' => $user->id, 'lesson_id' => $second->id, 'completed_at' => now()]);

        $sessionId = $this->actingAs($user)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $third->id,
            ])->assertCreated()->json('data.id');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/learning/sessions/{$sessionId}/complete")
            ->assertOk();

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'completed']);
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $course = Course::create([
            'title' => 'Path '.Str::random(6),
            'slug' => 'path-'.Str::random(8),
            'status' => 'published',
        ]);
        $firstUnit = Unit::create([
            'course_id' => $course->id,
            'title' => 'Foundations',
            'sort_order' => 0,
            'status' => 'published',
        ]);
        $secondUnit = Unit::create([
            'course_id' => $course->id,
            'title' => 'Practice',
            'sort_order' => 1,
            'status' => 'published',
        ]);
        $hiddenUnit = Unit::create([
            'course_id' => $course->id,
            'title' => 'Draft',
            'sort_order' => 2,
            'status' => 'draft',
        ]);
        $first = $this->lesson($course, $firstUnit, 'First', 0);
        $second = $this->lesson($course, $firstUnit, 'Second', 1);
        $third = $this->lesson($course, $secondUnit, 'Third', 0);
        $this->lesson($course, $hiddenUnit, 'Hidden', 0);
        $second->prerequisites()->attach($first);
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        return [$user, $course, $enrollment, $first, $second, $third];
    }

    private function lesson(Course $course, Unit $unit, string $title, int $order): Lesson
    {
        return Lesson::create([
            'course_id' => $course->id,
            'unit_id' => $unit->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'sort_order' => $order,
            'status' => 'published',
        ]);
    }
}
