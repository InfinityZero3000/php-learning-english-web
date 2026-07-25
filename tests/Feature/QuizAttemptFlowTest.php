<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAttemptFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_answer_and_finish_a_quiz(): void
    {
        $user = User::factory()->create();
        $level = Level::create(['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1]);
        $course = Course::create(['level_id' => $level->id, 'title' => 'Course', 'slug' => 'course', 'status' => 'published']);
        $lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Lesson', 'slug' => 'lesson', 'level' => 'beginner', 'cefr' => 'A1']);
        $quiz = Quiz::create(['lesson_id' => $lesson->id, 'title' => 'Quiz', 'pass_score' => 60]);
        $correctQuestion = Question::create(['quiz_id' => $quiz->id, 'content' => 'Correct?', 'type' => 'multiple_choice']);
        $correct = Answer::create(['question_id' => $correctQuestion->id, 'content' => 'Yes', 'is_correct' => true]);
        Answer::create(['question_id' => $correctQuestion->id, 'content' => 'No', 'is_correct' => false]);
        $wrongQuestion = Question::create(['quiz_id' => $quiz->id, 'content' => 'Wrong?', 'type' => 'multiple_choice']);
        Answer::create(['question_id' => $wrongQuestion->id, 'content' => 'Yes', 'is_correct' => true]);
        $wrong = Answer::create(['question_id' => $wrongQuestion->id, 'content' => 'No', 'is_correct' => false]);

        $start = $this->actingAs($user)->postJson("/api/quizzes/{$quiz->id}/start");
        $start->assertCreated()->assertJsonMissingPath('questions.0.answers.0.is_correct');
        $attemptId = $start->json('attempt.id');

        $this->postJson("/api/attempts/{$attemptId}/answer", ['question_id' => $correctQuestion->id, 'answer_id' => $correct->id])->assertOk()->assertJsonPath('is_correct', true);
        $this->postJson("/api/attempts/{$attemptId}/answer", ['question_id' => $wrongQuestion->id, 'answer_id' => $wrong->id])->assertOk()->assertJsonPath('is_correct', false);
        $this->postJson("/api/attempts/{$attemptId}/finish")->assertOk()->assertJsonPath('data.score', 50)->assertJsonPath('data.correct_answers', 1)->assertJsonPath('data.status', 'completed');
    }
}
