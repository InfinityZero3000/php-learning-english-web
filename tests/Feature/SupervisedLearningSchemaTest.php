<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Lesson;
use App\Models\OperationsAudit;
use App\Models\QuotaPolicy;
use App\Models\SupervisionAlert;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupervisedLearningSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervised_learning_schema_is_expand_only_and_keeps_sensitive_payloads_out(): void
    {
        foreach ([
            'lexilingo_import_checkpoints', 'lexilingo_import_failures',
            'enrollments', 'learning_sessions', 'learning_events',
            'learning_assistance_results', 'teacher_assignments',
            'supervision_alerts', 'assignments', 'intervention_notes',
            'operations_audits', 'quota_policies', 'alert_rules',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('progress', [
            'status', 'best_score', 'started_at', 'completed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('user_vocabularies', [
            'last_reviewed_at', 'algorithm', 'algorithm_version', 'revision',
        ]));
        $this->assertTrue(Schema::hasColumns('vocabulary_reviews', [
            'request_id', 'algorithm', 'algorithm_version', 'base_revision',
            'resulting_revision', 'before_state', 'after_state',
            'before_due_at', 'after_due_at', 'before_stability',
            'after_stability', 'before_difficulty', 'after_difficulty',
            'before_scheduled_days', 'after_scheduled_days',
            'before_last_reviewed_at', 'after_last_reviewed_at',
        ]));

        foreach (['learning_events', 'learning_assistance_results'] as $table) {
            $columns = Schema::getColumnListing($table);
            $this->assertEmpty(array_intersect(
                ['raw_audio', 'raw_audio_path', 'audio', 'audio_path', 'prompt', 'trace'],
                $columns,
            ), "{$table} must not persist raw audio, prompts, or traces.");
        }
    }

    public function test_foreign_keys_unique_scopes_and_assignment_xor_are_enforced(): void
    {
        [$teacher, $learner, $course, $lesson, $vocabulary] = $this->catalog();

        Enrollment::create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        $this->expectQueryFailure(fn () => Enrollment::create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]));

        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'learner_id' => $learner->id,
            'assigned_at' => now(),
        ]);
        $this->expectQueryFailure(fn () => TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'learner_id' => $learner->id,
        ]));

        $base = [
            'teacher_id' => $teacher->id,
            'learner_id' => $learner->id,
            'status' => 'pending',
        ];
        $this->expectQueryFailure(fn () => Assignment::create($base));
        $this->expectQueryFailure(fn () => Assignment::create($base + [
            'lesson_id' => $lesson->id,
            'vocabulary_id' => $vocabulary->id,
        ]));

        Assignment::create($base + ['lesson_id' => $lesson->id]);
        $this->expectQueryFailure(fn () => Assignment::create($base + ['lesson_id' => $lesson->id]));

        $this->expectQueryFailure(fn () => DB::table('enrollments')->insert([
            'user_id' => 999999,
            'course_id' => $course->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function test_new_fsrs_state_is_nullable_revisioned_and_review_requests_are_unique(): void
    {
        [, $learner, , , $vocabulary] = $this->catalog();

        $card = UserVocabulary::create([
            'user_id' => $learner->id,
            'vocabulary_id' => $vocabulary->id,
        ]);

        $this->assertSame('learning', $card->state);
        $this->assertNull($card->stability);
        $this->assertNull($card->difficulty);
        $this->assertSame(0, $card->revision);
        $this->assertNull($card->last_reviewed_at);

        $review = $card->reviews()->create([
            'request_id' => '018f75d8-4ed6-7de2-a884-7a394888b081',
            'rating' => 3,
            'stability' => 1,
            'difficulty' => 5,
            'scheduled_days' => 1,
            'elapsed_days' => 0,
            'reviewed_at' => now(),
        ]);

        $this->expectQueryFailure(fn () => $card->reviews()->create([
            'request_id' => $review->request_id,
            'rating' => 4,
            'stability' => 2,
            'difficulty' => 4,
            'scheduled_days' => 2,
            'elapsed_days' => 1,
            'reviewed_at' => now(),
        ]));
    }

    public function test_append_only_evidence_audit_quota_and_alert_rule_models_are_connected(): void
    {
        [$teacher, $learner, $course, $lesson] = $this->catalog();
        $enrollment = Enrollment::create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        $session = LearningSession::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'status' => 'active',
        ]);
        $event = $session->events()->create([
            'user_id' => $learner->id,
            'event_type' => 'hint_used',
            'request_id' => '018f75d8-4ed6-7de2-a884-7a394888b082',
            'hint_level' => 1,
            'occurred_at' => now(),
        ]);
        $assistance = $event->assistanceResults()->create([
            'request_id' => '018f75d8-4ed6-7de2-a884-7a394888b083',
            'source' => 'local_fallback',
            'status' => 'degraded',
            'schema_version' => '1',
            'diagnosis' => ['codes' => ['timeout']],
            'hints' => ['Review the example.'],
        ]);

        $audit = OperationsAudit::create([
            'actor_id' => $teacher->id,
            'action' => 'quota_policy.updated',
            'target_type' => 'quota_policy',
            'target_id' => 'trace-cag',
            'context' => ['request_id' => 'req-1'],
            'occurred_at' => now(),
        ]);
        $quota = QuotaPolicy::create([
            'version' => 1,
            'limits' => ['trace_cag_per_minute' => 30],
            'created_by' => $teacher->id,
        ]);
        $rule = AlertRule::create([
            'rule_key' => 'repeated_concept_error',
            'version' => 1,
            'parameters' => ['count' => 3, 'window_days' => 7],
            'created_by' => $teacher->id,
        ]);
        AlertRule::create([
            'rule_key' => $rule->rule_key,
            'version' => 2,
            'parameters' => ['count' => 4, 'window_days' => 7],
            'created_by' => $teacher->id,
        ]);
        $this->expectQueryFailure(fn () => AlertRule::create([
            'rule_key' => $rule->rule_key,
            'version' => 1,
            'parameters' => [],
        ]));

        $this->assertInstanceOf(BelongsTo::class, $session->enrollment());
        $this->assertInstanceOf(HasMany::class, $session->events());
        $this->assertInstanceOf(BelongsTo::class, $event->session());
        $this->assertInstanceOf(HasMany::class, $event->assistanceResults());
        $this->assertInstanceOf(BelongsTo::class, $assistance->event());
        $this->assertInstanceOf(BelongsTo::class, (new SupervisionAlert)->learner());
        $this->assertSame(['codes' => ['timeout']], $assistance->diagnosis);
        $this->assertSame(['request_id' => 'req-1'], $audit->context);
        $this->assertSame(['trace_cag_per_minute' => 30], $quota->limits);
        $this->assertSame(['count' => 3, 'window_days' => 7], $rule->parameters);
    }

    private function catalog(): array
    {
        $teacher = User::factory()->create();
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'Course', 'slug' => fake()->unique()->slug(), 'status' => 'draft']);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Lesson',
            'slug' => fake()->unique()->slug(),
        ]);
        $vocabulary = Vocabulary::create([
            'lesson_id' => $lesson->id,
            'word' => fake()->unique()->word(),
            'meaning' => 'meaning',
        ]);

        return [$teacher, $learner, $course, $lesson, $vocabulary];
    }

    private function expectQueryFailure(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a database constraint violation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
