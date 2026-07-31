<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminQuizApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_admin_can_author_and_replace_nested_quiz(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $course = Course::create(['title' => 'English', 'slug' => 'english', 'status' => 'draft', 'language' => 'en']);
        $lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Greetings', 'slug' => 'greetings', 'sort_order' => 0, 'status' => 'draft']);
        $payload = ['lesson_id' => $lesson->id, 'title' => 'Checkpoint', 'passing_score' => 60, 'status' => 'draft', 'questions' => [[
            'content' => 'Hello?', 'answers' => [['content' => 'Hi', 'is_correct' => true], ['content' => 'Bye', 'is_correct' => false]],
        ]]];

        $id = $this->actingAs($admin)->postJson('/api/v1/admin/catalog/quizzes', $payload)->assertCreated()
            ->assertJsonPath('data.questions.0.answers.0.is_correct', true)->json('data.id');
        $payload['title'] = 'Updated checkpoint';
        $this->putJson("/api/v1/admin/catalog/quizzes/{$id}", $payload)->assertOk()->assertJsonPath('data.title', 'Updated checkpoint');
        $this->getJson("/api/v1/admin/catalog/quizzes?lesson_id={$lesson->id}&status=draft")
            ->assertOk()->assertJsonPath('data.0.questions_count', 1);
        $this->deleteJson("/api/v1/admin/catalog/quizzes/{$id}")->assertNoContent();
        $lesson->refresh();
        $this->assertSame(3, $lesson->catalog_revision);
        $this->assertNotNull($lesson->local_override_at);
    }

    public function test_quiz_nested_validation_and_capability_are_enforced(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $learner = User::factory()->create(['role_id' => Role::where('slug', 'learner')->value('id')]);
        $this->actingAs($learner)->getJson('/api/v1/admin/catalog/quizzes')->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/admin/catalog/quizzes', ['questions' => []])
            ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
