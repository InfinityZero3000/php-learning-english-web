<?php

namespace Tests\Feature\Api\V1;

use App\Models\AlertRule;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\SupervisionAlert;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_only_sees_assigned_learners_and_can_resolve_their_alert(): void
    {
        $this->seed();
        $teacher = $this->user('teacher');
        $learner = $this->user('learner');
        $other = $this->user('learner');
        TeacherAssignment::create(['teacher_id' => $teacher->id, 'learner_id' => $learner->id, 'assigned_at' => now()]);
        $rule = AlertRule::create(['rule_key' => 'inactivity', 'version' => 1, 'enabled' => true, 'parameters' => []]);
        $alert = SupervisionAlert::create([
            'learner_id' => $learner->id, 'alert_rule_id' => $rule->id, 'rule_key' => 'inactivity',
            'rule_version' => 1, 'fingerprint' => 'one', 'active_fingerprint' => 'one',
            'severity' => 'warning', 'evidence' => [['days' => 7]], 'state' => 'open', 'detected_at' => now(),
        ]);

        $this->actingAs($teacher)->getJson('/api/v1/teacher/learners')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $learner->id);
        $this->getJson("/api/v1/teacher/learners/{$other->id}/evidence")->assertForbidden();
        $this->postJson("/api/v1/teacher/alerts/{$alert->id}/resolve", [
            'resolution_code' => 'teacher_reviewed',
        ])->assertOk()->assertJsonPath('data.state', 'resolved');
        $this->assertNull($alert->fresh()->active_fingerprint);
    }

    public function test_learner_cannot_use_teacher_api(): void
    {
        $this->seed();
        $this->actingAs($this->user('learner'))->getJson('/api/v1/teacher/learners')->assertForbidden();
    }

    public function test_assignment_requires_exactly_one_target(): void
    {
        $this->seed();
        $teacher = $this->user('teacher');
        $learner = $this->user('learner');
        TeacherAssignment::create(['teacher_id' => $teacher->id, 'learner_id' => $learner->id, 'assigned_at' => now()]);
        $course = Course::create(['title' => 'Course', 'slug' => 'teacher-course', 'status' => 'published']);
        $lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Lesson', 'slug' => 'teacher-lesson']);
        $word = Vocabulary::create(['lesson_id' => $lesson->id, 'word' => 'hello', 'meaning' => 'xin chào']);

        $this->actingAs($teacher)->postJson('/api/v1/teacher/assignments', [
            'learner_id' => $learner->id,
            'lesson_id' => $lesson->id,
            'vocabulary_id' => $word->id,
        ])->assertUnprocessable();
    }

    public function test_teacher_cannot_attach_a_note_to_another_learners_alert(): void
    {
        $this->seed();
        $teacher = $this->user('teacher');
        $learner = $this->user('learner');
        $other = $this->user('learner');
        TeacherAssignment::create(['teacher_id' => $teacher->id, 'learner_id' => $learner->id, 'assigned_at' => now()]);
        $rule = AlertRule::create(['rule_key' => 'inactivity', 'version' => 1, 'enabled' => true, 'parameters' => []]);
        $alert = SupervisionAlert::create([
            'learner_id' => $other->id, 'alert_rule_id' => $rule->id, 'rule_key' => 'inactivity',
            'rule_version' => 1, 'fingerprint' => 'other', 'active_fingerprint' => 'other',
            'severity' => 'warning', 'evidence' => [], 'state' => 'open', 'detected_at' => now(),
        ]);

        $this->actingAs($teacher)->postJson('/api/v1/teacher/intervention-notes', [
            'learner_id' => $learner->id,
            'supervision_alert_id' => $alert->id,
            'note' => 'Cross-scope note',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('intervention_notes', 0);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]);
    }
}
