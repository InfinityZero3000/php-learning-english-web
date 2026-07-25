<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $learner;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->create(['slug' => 'admin']);
        $learnerRole = Role::factory()->create(['slug' => 'learner']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->learner = User::factory()->create(['role_id' => $learnerRole->id]);
        $this->user = User::factory()->create(['role_id' => $learnerRole->id]);
    }

    /** @test */
    public function admin_can_access_admin_routes(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    /** @test */
    public function learner_cannot_access_admin_routes(): void
    {
        $this->actingAs($this->learner)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    /** @test */
    public function guest_cannot_access_admin_routes(): void
    {
        $this->get('/admin/dashboard')
            ->assertRedirect('/login');
    }

    /** @test */
    public function admin_can_create_course(): void
    {
        $level = Level::factory()->create();

        $this->actingAs($this->admin)
            ->post('/admin/courses', [
                'title' => 'English 101',
                'slug' => 'english-101',
                'description' => 'A beginner course',
                'level_id' => $level->id,
                'status' => 'published',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('courses', ['title' => 'English 101']);
    }

    /** @test */
    public function learner_cannot_create_course(): void
    {
        $level = Level::factory()->create();

        $this->actingAs($this->learner)
            ->post('/admin/courses', [
                'title' => 'English 101',
                'slug' => 'english-101',
                'description' => 'A beginner course',
                'level_id' => $level->id,
                'status' => 'published',
            ])
            ->assertForbidden();
    }

    /** @test */
    public function api_auth_required_for_protected_endpoints(): void
    {
        $response = $this->getJson('/api/v1/profile');
        $response->assertStatus(401);
    }

    /** @test */
    public function api_auth_works_with_token(): void
    {
        $token = $this->learner->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/profile')
            ->assertOk();
    }

    /** @test */
    public function admin_can_update_course(): void
    {
        $level = Level::factory()->create();
        $course = Course::factory()->create(['level_id' => $level->id]);

        $this->actingAs($this->admin)
            ->put("/admin/courses/{$course->id}", [
                'title' => 'Updated English',
                'slug' => $course->slug,
                'description' => 'Updated description',
                'level_id' => $level->id,
                'status' => 'published',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('courses', ['title' => 'Updated English']);
    }

    /** @test */
    public function admin_can_delete_course(): void
    {
        $level = Level::factory()->create();
        $course = Course::factory()->create(['level_id' => $level->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/courses/{$course->id}")
            ->assertRedirect();

        $this->assertModelMissing($course);
    }

    /** @test */
    public function learner_can_access_profile(): void
    {
        $this->actingAs($this->learner)
            ->get('/profile')
            ->assertOk();
    }

    /** @test */
    public function learner_can_update_profile(): void
    {
        $this->actingAs($this->learner)
            ->put('/profile', [
                'name' => 'Updated Name',
                'email' => $this->learner->email,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['name' => 'Updated Name']);
    }

    /** @test */
    public function learner_can_access_content_pages(): void
    {
        $this->actingAs($this->learner)
            ->get('/content')
            ->assertOk();
    }

    /** @test */
    public function learner_can_view_quiz_page(): void
    {
        $level = Level::factory()->create();
        $course = Course::factory()->create(['level_id' => $level->id]);
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        $quiz = Quiz::factory()->create(['lesson_id' => $lesson->id]);

        $this->actingAs($this->learner)
            ->get("/content/lessons/{$lesson->id}/quizzes/{$quiz->id}")
            ->assertOk();
    }
}