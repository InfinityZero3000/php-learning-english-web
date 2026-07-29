<?php

namespace Tests\Feature\Api\V1;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LessonQuizSeeder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $quiz1Id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(CatalogSeeder::class);
        $this->seed(LessonQuizSeeder::class);

        $this->user = User::factory()->create();
        $this->quiz1Id = Quiz::with('lesson')->whereHas('lesson', function ($q) {
            $q->where('slug', 'dong-vat-hoang-da');
        })->value('id');
    }

    public function test_guest_gets_401_on_quiz_routes(): void
    {
        $this->getJson("/api/v1/quizzes/{$this->quiz1Id}")->assertUnauthorized();
        $this->postJson("/api/v1/quizzes/{$this->quiz1Id}/submit")->assertUnauthorized();
        $this->getJson("/api/v1/quizzes/{$this->quiz1Id}/history")->assertUnauthorized();
    }

    public function test_user_can_view_quiz_with_questions(): void
    {
        $this->actingAs($this->user);

        $this->getJson("/api/v1/quizzes/{$this->quiz1Id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'passing_score',
                    'questions' => [
                        ['id', 'content', 'answers' => [
                            ['id', 'content'],
                        ]],
                    ],
                ],
            ]);
    }

    public function test_user_can_submit_quiz_and_get_score(): void
    {
        $this->actingAs($this->user);

        $question = Question::where('quiz_id', $this->quiz1Id)->first();
        $correctAnswer = Answer::where('question_id', $question->id)
            ->where('is_correct', true)
            ->first();

        $allQuestions = Question::where('quiz_id', $this->quiz1Id)->pluck('id');

        $answers = $allQuestions->map(function ($qId) {
            $answer = Answer::where('question_id', $qId)->where('is_correct', true)->first();

            return [
                'question_id' => $qId,
                'answer_id' => $answer->id,
            ];
        })->toArray();

        $this->postJson("/api/v1/quizzes/{$this->quiz1Id}/submit", ['answers' => $answers])
            ->assertCreated()
            ->assertJsonPath('data.score', 100);

        $this->assertDatabaseHas('attempts', [
            'user_id' => $this->user->id,
            'quiz_id' => $this->quiz1Id,
        ]);
    }

    public function test_retry_quiz_does_not_create_duplicate_attempt(): void
    {
        $this->actingAs($this->user);

        $allQuestions = Question::where('quiz_id', $this->quiz1Id)->pluck('id');
        $answers = $allQuestions->map(function ($qId) {
            $correct = Answer::where('question_id', $qId)->where('is_correct', true)->first();

            return [
                'question_id' => $qId,
                'answer_id' => $correct->id,
            ];
        })->toArray();

        $this->postJson("/api/v1/quizzes/{$this->quiz1Id}/submit", ['answers' => $answers])
            ->assertCreated();

        $wrongAnswers = $allQuestions->map(function ($qId) {
            $wrong = Answer::where('question_id', $qId)->where('is_correct', false)->first();

            return [
                'question_id' => $qId,
                'answer_id' => $wrong ? $wrong->id : Answer::where('question_id', $qId)->where('is_correct', true)->first()->id,
            ];
        })->toArray();

        $this->postJson("/api/v1/quizzes/{$this->quiz1Id}/submit", ['answers' => $wrongAnswers])
            ->assertOk()
            ->assertJsonPath('data.score', 100);

        $this->assertEquals(1, Attempt::where('user_id', $this->user->id)
            ->where('quiz_id', $this->quiz1Id)
            ->whereNotNull('completed_at')
            ->count());
    }

    public function test_submit_quiz_requires_answers(): void
    {
        $this->actingAs($this->user);

        $this->postJson("/api/v1/quizzes/{$this->quiz1Id}/submit", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers']);

        $this->postJson("/api/v1/quizzes/{$this->quiz1Id}/submit", ['answers' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_duplicate_question_cannot_inflate_score(): void
    {
        $this->actingAs($this->user);
        $question = Question::where('quiz_id', $this->quiz1Id)->firstOrFail();
        $answer = Answer::where('question_id', $question->id)->where('is_correct', true)->firstOrFail();

        $this->postJson("/api/v1/quizzes/{$this->quiz1Id}/submit", ['answers' => [
            ['question_id' => $question->id, 'answer_id' => $answer->id],
            ['question_id' => $question->id, 'answer_id' => $answer->id],
        ]])->assertUnprocessable()->assertJsonValidationErrors('answers.1.question_id');

        $this->assertDatabaseMissing('attempts', [
            'user_id' => $this->user->id,
            'quiz_id' => $this->quiz1Id,
        ]);
    }

    public function test_quiz_routes_return_404_for_nonexistent_quiz_id(): void
    {
        $this->actingAs($this->user);

        $this->getJson('/api/v1/quizzes/999999')->assertNotFound();
        $this->postJson('/api/v1/quizzes/999999/submit', ['answers' => []])->assertNotFound();
        $this->getJson('/api/v1/quizzes/999999/history')->assertNotFound();
    }

    public function test_submit_rejects_answer_not_belonging_to_question(): void
    {
        $this->actingAs($this->user);

        $questions = Question::where('quiz_id', $this->quiz1Id)->orderBy('sort_order')->pluck('id');
        $firstQuestionId = $questions[0];
        $secondQuestionAnswerId = Answer::where('question_id', $questions[1])->first()->id;

        $this->postJson("/api/v1/quizzes/{$this->quiz1Id}/submit", ['answers' => [
            ['question_id' => $firstQuestionId, 'answer_id' => $secondQuestionAnswerId],
        ]])->assertUnprocessable()->assertJsonValidationErrors('answers');

        $this->assertDatabaseMissing('attempts', [
            'user_id' => $this->user->id,
            'quiz_id' => $this->quiz1Id,
        ]);
    }

    public function test_draft_quiz_cannot_be_viewed_or_submitted(): void
    {
        $this->actingAs($this->user);
        Quiz::whereKey($this->quiz1Id)->update(['status' => 'draft']);

        $this->getJson("/api/v1/quizzes/{$this->quiz1Id}")->assertNotFound();
        $this->postJson("/api/v1/quizzes/{$this->quiz1Id}/submit", ['answers' => []])->assertNotFound();
        $this->getJson("/api/v1/quizzes/{$this->quiz1Id}/history")->assertNotFound();
    }

    public function test_user_can_view_quiz_history(): void
    {
        $this->actingAs($this->user);

        Attempt::create([
            'user_id' => $this->user->id,
            'quiz_id' => $this->quiz1Id,
            'score' => 80,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->getJson("/api/v1/quizzes/{$this->quiz1Id}/history")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.score', 80);
    }

    public function test_user_cannot_see_other_user_quiz_history(): void
    {
        $otherUser = User::factory()->create();
        $this->actingAs($this->user);

        Attempt::create([
            'user_id' => $otherUser->id,
            'quiz_id' => $this->quiz1Id,
            'score' => 80,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->getJson("/api/v1/quizzes/{$this->quiz1Id}/history")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
