<?php

namespace Tests\Feature\Api\V1;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_can_enroll_start_advance_and_complete_a_course_lesson(): void
    {
        [$user, $course, $lesson, $word] = $this->context();

        $enrollmentId = $this->actingAs($user)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/enrollments', ['course_id' => $course->id])
            ->assertCreated()
            ->assertJsonPath('data.type', 'enrollment')
            ->json('data.id');

        $sessionId = $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', ['enrollment_id' => $enrollmentId])
            ->assertCreated()
            ->assertJsonPath('data.lesson_id', $lesson->id)
            ->json('data.id');

        $this->getJson("/api/v1/learning/sessions/{$sessionId}/next")
            ->assertOk()
            ->assertJsonPath('data.activity.vocabulary_id', $word->id)
            ->assertJsonPath('data.activity.practice_only', false);

        LearningEvent::create([
            'learning_session_id' => $sessionId, 'user_id' => $user->id,
            'event_type' => 'answer', 'occurred_at' => now(), 'is_correct' => true,
            'metadata' => ['vocabulary_id' => $word->id],
        ]);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/learning/sessions/{$sessionId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('progress', ['user_id' => $user->id, 'lesson_id' => $lesson->id]);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollmentId, 'status' => 'completed']);
    }

    public function test_today_plan_prioritizes_teacher_lesson_then_fsrs_then_course(): void
    {
        [$user, $course, $lesson, $word] = $this->context();
        $teacher = User::factory()->create();
        $enrollment = Enrollment::create([
            'user_id' => $user->id, 'course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now(),
        ]);
        $assignment = Assignment::create([
            'teacher_id' => $teacher->id, 'learner_id' => $user->id, 'lesson_id' => $lesson->id,
            'status' => 'pending', 'due_at' => now()->subMinute(),
        ]);
        $card = UserVocabulary::create([
            'user_id' => $user->id, 'vocabulary_id' => $word->id, 'due_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/learning/plan')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $assignment->id)
            ->assertJsonPath('data.items.0.type', 'teacher_lesson')
            ->assertJsonPath('data.items.1.id', $card->id)
            ->assertJsonPath('data.items.1.type', 'fsrs_review')
            ->assertJsonPath('data.items.2.id', $enrollment->id)
            ->assertJsonPath('data.items.2.type', 'course_activity');
    }

    public function test_learner_only_lists_their_own_assignments(): void
    {
        [$learner, , $lesson] = $this->context();
        $teacher = User::factory()->create();
        $owned = Assignment::create([
            'teacher_id' => $teacher->id, 'learner_id' => $learner->id,
            'lesson_id' => $lesson->id, 'status' => 'pending',
        ]);
        Assignment::create([
            'teacher_id' => $teacher->id, 'learner_id' => User::factory()->create()->id,
            'lesson_id' => $lesson->id, 'status' => 'pending',
        ]);

        $this->actingAs($learner)->getJson('/api/v1/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $owned->id)
            ->assertJsonPath('data.0.lesson.title', $lesson->title);
    }

    public function test_session_ownership_is_enforced(): void
    {
        [$owner, $course, $lesson] = $this->context();
        $session = LearningSession::create([
            'user_id' => $owner->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
            'status' => 'active', 'started_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/learning/sessions/{$session->id}/next")
            ->assertForbidden();
    }

    public function test_session_request_id_is_bound_to_its_payload(): void
    {
        [$user, $course] = $this->context();
        $enrollment = Enrollment::create([
            'user_id' => $user->id, 'course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now(),
        ]);
        $requestId = (string) Str::uuid();

        $this->actingAs($user)->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/learning/sessions', ['enrollment_id' => $enrollment->id])
            ->assertCreated();
        $this->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/learning/sessions', [
                'enrollment_id' => $enrollment->id,
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])->assertConflict();
    }

    public function test_course_lesson_requires_every_vocabulary_activity_before_completion(): void
    {
        [$user, $course, $lesson, $first] = $this->context();
        $second = Vocabulary::create([
            'lesson_id' => $lesson->id, 'word' => 'world'.Str::random(4), 'meaning' => 'thế giới',
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $user->id, 'course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now(),
        ]);
        $sessionId = $this->actingAs($user)->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', ['enrollment_id' => $enrollment->id])->json('data.id');

        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/learning/sessions/{$sessionId}/attempts", [
                'activity_id' => "vocabulary:{$first->id}",
                'answer' => $first->meaning,
                'duration_ms' => 1000,
                'hint_count' => 0,
            ])->assertOk();
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/learning/sessions/{$sessionId}/complete")->assertConflict();
        $this->getJson("/api/v1/learning/sessions/{$sessionId}/next")
            ->assertJsonPath('data.activity.vocabulary_id', $second->id)
            ->assertJsonPath('data.progress.completed', 1)
            ->assertJsonPath('data.progress.total', 2);
    }

    public function test_teacher_vocabulary_assignment_is_practice_only_and_does_not_create_fsrs_state(): void
    {
        [$learner, , , $word] = $this->context();
        $assignment = Assignment::create([
            'teacher_id' => User::factory()->create()->id,
            'learner_id' => $learner->id,
            'vocabulary_id' => $word->id,
            'status' => 'pending',
        ]);

        $sessionId = $this->actingAs($learner)
            ->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', ['assignment_id' => $assignment->id])
            ->assertCreated()
            ->json('data.id');
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson('/api/v1/learning/sessions', ['assignment_id' => $assignment->id])
            ->assertCreated()
            ->assertJsonPath('data.id', $sessionId);
        $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'status' => 'in_progress']);
        $this->assertDatabaseCount('learning_sessions', 1);

        $this->getJson("/api/v1/learning/sessions/{$sessionId}/next")
            ->assertOk()
            ->assertJsonPath('data.activity.practice_only', true);
        LearningEvent::create([
            'learning_session_id' => $sessionId, 'user_id' => $learner->id,
            'event_type' => 'answer', 'occurred_at' => now(), 'is_correct' => true,
            'metadata' => ['vocabulary_id' => $word->id],
        ]);
        $this->withHeader('X-Request-ID', (string) Str::uuid())
            ->postJson("/api/v1/learning/sessions/{$sessionId}/complete")->assertOk();
        $this->assertDatabaseCount('user_vocabularies', 0);
        $this->assertDatabaseCount('progress', 0);
        $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'status' => 'completed']);
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $course = Course::create([
            'title' => 'Course '.Str::random(6), 'slug' => 'course-'.Str::random(8), 'status' => 'published',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'title' => 'Lesson', 'slug' => 'lesson-'.Str::random(8),
            'status' => 'published', 'sort_order' => 1,
        ]);
        $word = Vocabulary::create([
            'lesson_id' => $lesson->id, 'word' => 'hello'.Str::random(4), 'meaning' => 'xin chào',
        ]);

        return [$user, $course, $lesson, $word];
    }
}
