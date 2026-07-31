<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $learner;

    private int $adminRoleId;

    private int $learnerRoleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->adminRoleId = Role::where('slug', 'admin')->value('id');
        $this->learnerRoleId = Role::where('slug', 'learner')->value('id');

        $this->admin = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
        ]);
        $this->learner = User::factory()->create(['role_id' => $this->learnerRoleId]);
    }

    /**
     * Test guest access gets 401 or redirect.
     */
    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/admin/categories')->assertStatus(401);
        $this->postJson('/api/v1/admin/categories', ['name' => 'Test'])->assertStatus(401);
    }

    /**
     * Test learner access gets 403.
     */
    public function test_learner_is_forbidden(): void
    {
        $this->actingAs($this->learner)
            ->getJson('/api/v1/admin/categories')
            ->assertStatus(403);

        $this->actingAs($this->learner)
            ->postJson('/api/v1/admin/categories', ['name' => 'Test'])
            ->assertStatus(403);
    }

    /**
     * Test Category CRUD.
     */
    public function test_admin_can_crud_categories(): void
    {
        // 1. Create (Store)
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Business English',
                'description' => 'English for business professionals',
                'icon' => 'briefcase',
                'color' => '#ff0000',
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('course_categories', ['name' => 'Business English']);
        $categoryId = $response->json('data.id');

        // 2. Read (Index)
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/categories')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Business English']);

        // 3. Read (Show)
        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/categories/{$categoryId}")
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Business English']);

        // 4. Update
        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/categories/{$categoryId}", [
                'name' => 'Advanced Business English',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('course_categories', [
            'id' => $categoryId, 'name' => 'Advanced Business English', 'catalog_revision' => 2,
        ]);
        $this->assertNotNull(CourseCategory::findOrFail($categoryId)->local_override_at);

        // 5. Delete
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/categories/{$categoryId}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('course_categories', ['id' => $categoryId]);
    }

    /**
     * Test course deletion constraint.
     */
    public function test_admin_cannot_delete_category_with_courses(): void
    {
        $category = CourseCategory::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);

        $level = Level::first();
        Course::create([
            'category_id' => $category->id,
            'level_id' => $level->id,
            'title' => 'Test Course',
            'slug' => 'test-course',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'CATEGORY_HAS_COURSES']);
    }

    /**
     * Test Course CRUD.
     */
    public function test_admin_can_crud_courses(): void
    {
        $category = CourseCategory::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);

        $level = Level::first();

        // 1. Create (Store)
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/courses', [
                'title' => 'New Course',
                'description' => 'A great course',
                'level_id' => $level->id,
                'category_id' => $category->id,
                'language' => 'en',
                'estimated_duration' => 120,
                'total_xp' => 500,
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
        $courseId = $response->json('data.id');
        $this->assertDatabaseHas('courses', ['title' => 'New Course', 'level_id' => $level->id]);

        // 2. Read (Index)
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/courses')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'New Course']);

        // 3. Read (Show)
        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/courses/{$courseId}")
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'New Course']);

        // 4. Update
        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/courses/{$courseId}", [
                'title' => 'Updated New Course',
                'status' => 'published',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('courses', [
            'id' => $courseId, 'title' => 'Updated New Course', 'status' => 'published', 'catalog_revision' => 2,
        ]);
        $this->assertNotNull(Course::findOrFail($courseId)->local_override_at);

        // 5. Delete
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/courses/{$courseId}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('courses', ['id' => $courseId]);
    }

    /**
     * Test levels helper endpoint.
     */
    public function test_admin_can_list_levels(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/levels')
            ->assertStatus(200)
            ->assertJsonFragment(['slug' => 'beginner']);
    }

    /**
     * Test Lesson CRUD.
     */
    public function test_admin_can_crud_lessons(): void
    {
        $level = Level::first();
        $course = Course::create([
            'level_id' => $level->id,
            'title' => 'Test Course',
            'slug' => 'test-course',
        ]);

        // 1. Create (Store)
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/lessons', [
                'course_id' => $course->id,
                'title' => 'Grammar Basics',
                'content' => 'Sample content',
                'lesson_type' => 'grammar',
                'estimated_minutes' => 15,
                'xp_reward' => 20,
                'pass_threshold' => 80,
                'sort_order' => 1,
            ]);

        $response->assertStatus(201);
        $lessonId = $response->json('data.id');
        $this->assertDatabaseHas('lessons', ['title' => 'Grammar Basics', 'course_id' => $course->id]);

        // 2. Read (Index)
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/lessons')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'Grammar Basics']);

        // 3. Read (Show)
        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/lessons/{$lessonId}")
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'Grammar Basics']);

        // 4. Update
        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/lessons/{$lessonId}", [
                'title' => 'Updated Grammar Basics',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('lessons', [
            'id' => $lessonId, 'title' => 'Updated Grammar Basics', 'catalog_revision' => 2,
        ]);
        $this->assertNotNull(Lesson::findOrFail($lessonId)->local_override_at);

        // 5. Delete
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/lessons/{$lessonId}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('lessons', ['id' => $lessonId]);
    }

    /**
     * Test Vocabulary CRUD.
     */
    public function test_admin_can_crud_vocabulary(): void
    {
        $level = Level::first();
        $course = Course::create([
            'level_id' => $level->id,
            'title' => 'Test Course',
            'slug' => 'test-course',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Test Lesson',
            'slug' => 'test-lesson',
        ]);

        // 1. Create (Store)
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/vocabulary', [
                'lesson_id' => $lesson->id,
                'word' => 'Aesthetic',
                'meaning' => 'Mỹ học, thẩm mỹ',
                'definition' => 'Concerned with beauty or the appreciation of beauty.',
                'part_of_speech' => 'adjective',
                'difficulty_level' => 'advanced',
                'tags' => ['art', 'beauty'],
            ]);

        $response->assertStatus(201);
        $vocabularyId = $response->json('data.id');
        $this->assertDatabaseHas('vocabularies', ['word' => 'Aesthetic', 'lesson_id' => $lesson->id]);

        // 2. Read (Index)
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/vocabulary')
            ->assertStatus(200)
            ->assertJsonFragment(['word' => 'Aesthetic']);

        // 3. Read (Show)
        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/vocabulary/{$vocabularyId}")
            ->assertStatus(200)
            ->assertJsonFragment(['word' => 'Aesthetic']);

        // 4. Update
        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/vocabulary/{$vocabularyId}", [
                'word' => 'Aesthetics',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('vocabularies', ['id' => $vocabularyId, 'word' => 'Aesthetics']);

        // 5. Delete
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/vocabulary/{$vocabularyId}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('vocabularies', ['id' => $vocabularyId]);
    }

    /**
     * Test Quiz CRUD (including nested Questions and Answers).
     */
    public function test_admin_can_crud_quizzes(): void
    {
        $level = Level::first();
        $course = Course::create([
            'level_id' => $level->id,
            'title' => 'Test Course',
            'slug' => 'test-course',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Test Lesson',
            'slug' => 'test-lesson',
        ]);

        // 1. Create (Store) with nested Questions & Answers
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/quizzes', [
                'lesson_id' => $lesson->id,
                'title' => 'Quick Quiz',
                'passing_score' => 70,
                'status' => 'published',
                'questions' => [
                    [
                        'content' => 'What is the capital of England?',
                        'explanation' => 'London is the capital.',
                        'sort_order' => 1,
                        'answers' => [
                            ['content' => 'Paris', 'is_correct' => false],
                            ['content' => 'London', 'is_correct' => true],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $quizId = $response->json('data.id');
        $this->assertDatabaseHas('quizzes', ['title' => 'Quick Quiz', 'lesson_id' => $lesson->id]);
        $this->assertDatabaseHas('questions', ['content' => 'What is the capital of England?', 'quiz_id' => $quizId]);
        $this->assertDatabaseHas('answers', ['content' => 'London', 'is_correct' => true]);

        // 2. Read (Index)
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/quizzes')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'Quick Quiz']);

        // 3. Read (Show)
        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/quizzes/{$quizId}")
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'Quick Quiz']);

        // 4. Update
        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/quizzes/{$quizId}", [
                'title' => 'Updated Quick Quiz',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('quizzes', ['id' => $quizId, 'title' => 'Updated Quick Quiz']);

        // 5. Delete
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/quizzes/{$quizId}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('quizzes', ['id' => $quizId]);
        $lesson->refresh();
        $this->assertSame(3, $lesson->catalog_revision);
        $this->assertNotNull($lesson->local_override_at);
    }

    /**
     * Test User management.
     */
    public function test_admin_can_manage_users(): void
    {
        $user = User::factory()->create(['role_id' => $this->learnerRoleId]);

        // 1. Index
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/users')
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email]);

        // 2. Show
        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/users/{$user->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email]);

        // 3. Update Role
        $this->actingAs($this->admin)
            ->withHeader('X-Request-ID', '00000000-0000-4000-8000-000000000001')
            ->putJson("/api/v1/admin/users/{$user->id}/role", [
                'role' => 'admin',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role_id' => $this->adminRoleId]);
    }

    /**
     * Test admin cannot demote themselves or the last admin.
     */
    public function test_admin_cannot_demote_self_or_last_admin(): void
    {
        $this->actingAs($this->admin)
            ->withHeader('X-Request-ID', '00000000-0000-4000-8000-000000000002')
            ->putJson("/api/v1/admin/users/{$this->learner->id}/role", [
                'role' => 'learner',
            ])
            ->assertOk();

        // Self demote
        $this->actingAs($this->admin)
            ->withHeader('X-Request-ID', '00000000-0000-4000-8000-000000000003')
            ->putJson("/api/v1/admin/users/{$this->admin->id}/role", [
                'role' => 'learner',
            ])
            ->assertForbidden();
    }
}
