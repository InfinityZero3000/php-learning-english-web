<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuizImportAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_questions_from_csv(): void
    {
        Role::create(['name' => 'Admin', 'slug' => 'admin']);
        Role::create(['name' => 'Learner', 'slug' => 'learner']);

        $course = Course::create(['title' => 'Grammar', 'slug' => 'grammar', 'description' => 'Test']);
        $lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Tenses', 'slug' => 'tenses', 'content' => 'Test', 'sort_order' => 1, 'status' => 'published']);
        $quiz = Quiz::create(['lesson_id' => $lesson->id, 'title' => 'Quiz 1', 'passing_score' => 60, 'status' => 'draft']);

        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);

        $csv = "What is 2+2?,4,3,2,1,2,Because 2+2 equals 4\n";
        $file = new UploadedFile(
            tempnam(sys_get_temp_dir(), 'csv') . '.csv',
            'questions.csv',
            'text/csv',
            null,
            true
        );

        file_put_contents($file->getRealPath(), $csv);

        $response = $this->actingAs($admin)->post(route('content.quiz.import', ['lesson' => $lesson, 'quiz' => $quiz]), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $freshQuiz = $quiz->fresh();

        $this->assertEquals(1, $freshQuiz->questions()->count());

        $question = Question::where('quiz_id', $freshQuiz->id)->with('answers')->first();
        $this->assertNotNull($question);
        $this->assertEquals(4, $question->answers()->count());
        $this->assertTrue($question->answers()->where('is_correct', true)->exists());
    }
}
