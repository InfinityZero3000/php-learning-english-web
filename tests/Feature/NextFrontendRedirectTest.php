<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NextFrontendRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'https://learner.test',
            'app.admin_frontend_url' => 'https://admin.test',
        ]);
    }

    public function test_public_pages_redirect_to_the_learner_frontend_without_untrusted_query_keys(): void
    {
        $this->get('/?next=https://evil.test')->assertRedirect('https://learner.test/');
        $this->get('/register?secret=x')->assertRedirect('https://learner.test/register');
        $this->get('/forgot-password?secret=x')->assertRedirect('https://learner.test/forgot-password');
        $this->get('/reset-password/a%20b?email=learner%40example.com&next=https://evil.test')
            ->assertRedirect('https://learner.test/reset-password?token=a%20b&email=learner%40example.com');
    }

    public function test_authenticated_learner_routes_forward_only_supported_state(): void
    {
        $user = User::factory()->create();
        $word = Vocabulary::query()->create(['word' => 'hello', 'meaning' => 'xin chào']);

        $this->actingAs($user)->get('/words?search=hello&topic_id=2&page=3&secret=x')
            ->assertRedirect('https://learner.test/vocabulary?search=hello&topic_id=2&page=3');
        $this->actingAs($user)->get("/words/{$word->id}?secret=x")
            ->assertRedirect("https://learner.test/vocabulary?word={$word->id}");
        $this->actingAs($user)->get('/bookmarks')->assertRedirect('https://learner.test/vocabulary?view=saved');
        $this->actingAs($user)->get('/progress')->assertRedirect('https://learner.test/progress');
    }

    public function test_quiz_result_redirect_requires_an_owned_attempt(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create(['title' => 'Starter', 'slug' => 'starter', 'status' => 'published']);
        $lesson = Lesson::query()->create(['course_id' => $course->id, 'title' => 'Hello', 'slug' => 'hello', 'status' => 'published']);
        $quiz = Quiz::query()->create(['lesson_id' => $lesson->id, 'title' => 'Hello quiz', 'status' => 'published']);
        $attempt = Attempt::query()->create(['user_id' => $user->id, 'quiz_id' => $quiz->id, 'score' => 80]);

        $this->actingAs($user)->get("/quizzes/{$quiz->id}/result?attempt_id={$attempt->id}&secret=x")
            ->assertRedirect("https://learner.test/quiz?attempt={$attempt->id}");

        $other = User::factory()->create();
        $this->actingAs($other)->get("/quizzes/{$quiz->id}/result?attempt_id={$attempt->id}")->assertNotFound();
    }

    public function test_admin_routes_preserve_only_supported_list_and_entity_state(): void
    {
        $this->withoutMiddleware();

        $this->get('/admin/users?search=ann&role=admin&page=2&secret=x')
            ->assertRedirect('https://admin.test/users?search=ann&role=admin&page=2');
        $this->get('/admin/courses/7/edit?secret=x')
            ->assertRedirect('https://admin.test/courses?course=7&mode=edit');
        $this->get('/admin/lessons/create?course_id=4&secret=x')
            ->assertRedirect('https://admin.test/lessons?mode=create&course_id=4');
        $this->get('/admin/quizzes/8')->assertRedirect('https://admin.test/quiz-management?quiz=8&mode=view');
        $this->get('/admin/vocabularies/9')->assertRedirect('https://admin.test/flashcards?word=9&mode=view');
    }
}
