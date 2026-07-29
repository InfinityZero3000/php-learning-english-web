<?php

namespace Tests\Feature\Api\V1;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LessonQuizSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonQuizApiTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
        $this->seed(LessonQuizSeeder::class);
        $this->learner = User::factory()->create(['role_id' => Role::where('slug', 'learner')->value('id')]);
        $this->quiz = Quiz::query()->with('lesson.course')->firstOrFail();
        Enrollment::create(['user_id' => $this->learner->id, 'course_id' => $this->quiz->lesson->course_id, 'status' => 'active']);
    }

    public function test_discovery_requires_an_eligible_learner_and_never_exposes_answer_correctness(): void
    {
        $this->getJson("/api/v1/lesson-quizzes?lesson_id={$this->quiz->lesson_id}")->assertUnauthorized();
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $this->actingAs($admin)->getJson("/api/v1/lesson-quizzes?lesson_id={$this->quiz->lesson_id}")->assertForbidden();

        $this->actingAs($this->learner)->getJson("/api/v1/lesson-quizzes?lesson_id={$this->quiz->lesson_id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->quiz->id)
            ->assertJsonMissing(['is_correct' => true]);
        $this->actingAs($this->learner)->getJson('/api/v1/lesson-quizzes')->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_start_answer_reload_complete_and_replays_are_owner_scoped(): void
    {
        $started = $this->actingAs($this->learner)->postJson("/api/v1/lesson-quizzes/{$this->quiz->id}/attempts")
            ->assertCreated()
            ->assertJsonMissing(['is_correct' => true]);
        $attemptId = $started->json('data.id');
        $question = $this->quiz->questions()->with('answers')->firstOrFail();
        $answer = $question->answers->firstWhere('is_correct', true);

        $this->actingAs($this->learner)->putJson("/api/v1/lesson-quiz-attempts/{$attemptId}/answers/{$question->id}", ['answer_id' => $answer->id])
            ->assertOk()->assertJsonPath('data.is_correct', true);
        $this->actingAs($this->learner)->getJson("/api/v1/lesson-quiz-attempts/{$attemptId}")
            ->assertOk()->assertJsonPath('data.submitted_answers.0.answer_id', $answer->id);
        $this->actingAs($this->learner)->putJson("/api/v1/lesson-quiz-attempts/{$attemptId}/answers/{$question->id}", ['answer_id' => $answer->id])->assertOk();
        $changed = $question->answers->firstWhere('id', '!=', $answer->id);
        $this->actingAs($this->learner)->putJson("/api/v1/lesson-quiz-attempts/{$attemptId}/answers/{$question->id}", ['answer_id' => $changed->id])
            ->assertConflict()->assertJsonPath('code', 'ANSWER_LOCKED');
        $this->actingAs($this->learner)->postJson("/api/v1/lesson-quiz-attempts/{$attemptId}/complete")
            ->assertConflict()->assertJsonPath('code', 'ATTEMPT_INCOMPLETE');

        foreach ($this->quiz->questions()->with('answers')->get()->skip(1) as $remainingQuestion) {
            $this->actingAs($this->learner)->putJson("/api/v1/lesson-quiz-attempts/{$attemptId}/answers/{$remainingQuestion->id}", [
                'answer_id' => $remainingQuestion->answers->firstWhere('is_correct', true)->id,
            ])->assertOk();
        }
        $this->actingAs($this->learner)->postJson("/api/v1/lesson-quiz-attempts/{$attemptId}/complete")
            ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.score', 100);
        $this->actingAs($this->learner)->postJson("/api/v1/lesson-quiz-attempts/{$attemptId}/complete")->assertOk();

        $other = User::factory()->create(['role_id' => Role::where('slug', 'learner')->value('id')]);
        $this->actingAs($other)->getJson("/api/v1/lesson-quiz-attempts/{$attemptId}")->assertForbidden();
    }

    public function test_start_reuses_one_active_attempt_and_validates_answer_relationships(): void
    {
        $this->actingAs($this->learner)->postJson("/api/v1/lesson-quizzes/{$this->quiz->id}/attempts")->assertCreated();
        $response = $this->actingAs($this->learner)->postJson("/api/v1/lesson-quizzes/{$this->quiz->id}/attempts")->assertOk();
        $this->assertDatabaseCount('attempts', 1);
        $attempt = Attempt::firstOrFail();
        $question = $this->quiz->questions()->firstOrFail();
        $wrongQuestion = Question::query()->where('quiz_id', '!=', $this->quiz->id)->firstOrFail();
        $wrongAnswer = Answer::query()->where('question_id', $wrongQuestion->id)->firstOrFail();
        $this->actingAs($this->learner)->putJson("/api/v1/lesson-quiz-attempts/{$attempt->id}/answers/{$question->id}", ['answer_id' => $wrongAnswer->id])
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertSame($attempt->id, $response->json('data.id'));
    }

    public function test_missing_quiz_entities_use_the_stable_error_envelope(): void
    {
        $this->actingAs($this->learner)->postJson('/api/v1/lesson-quizzes/999999/attempts')
            ->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->actingAs($this->learner)->getJson('/api/v1/lesson-quiz-attempts/999999')
            ->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    }
}
