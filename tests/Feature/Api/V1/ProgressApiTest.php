<?php

namespace Tests\Feature\Api\V1;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Progress;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LessonQuizSeeder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $lesson1Id;
    private int $course1Id;
    private int $course2Id;
    private int $quiz1Id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(CatalogSeeder::class);
        $this->seed(LessonQuizSeeder::class);

        $this->user = User::where('email', 'user@example.com')->first();
        $this->lesson1Id = Lesson::where('slug', 'dong-vat-hoang-da')->value('id');
        $this->course1Id = Course::where('slug', 'tieng-anh-co-ban')->value('id');
        $this->course2Id = Course::where('slug', 'tieng-anh-giao-tiep')->value('id');
        $this->quiz1Id = Quiz::with('lesson')->whereHas('lesson', function ($q) {
            $q->where('slug', 'dong-vat-hoang-da');
        })->value('id');
    }

    public function test_guest_gets_401_on_progress_routes(): void
    {
        $this->getJson('/api/v1/progress')->assertUnauthorized();
        $this->getJson('/api/v1/progress/dashboard')->assertUnauthorized();
        $this->getJson("/api/v1/progress/course/{$this->course1Id}")->assertUnauthorized();
        $this->postJson("/api/v1/progress/lesson/{$this->lesson1Id}/complete")->assertUnauthorized();
    }

    public function test_user_can_mark_lesson_completed(): void
    {
        $this->actingAs($this->user);

        $this->postJson("/api/v1/progress/lesson/{$this->lesson1Id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('progress', [
            'user_id' => $this->user->id,
            'lesson_id' => $this->lesson1Id,
        ]);
    }

    public function test_mark_completed_is_idempotent(): void
    {
        $this->actingAs($this->user);

        $this->postJson("/api/v1/progress/lesson/{$this->lesson1Id}/complete")->assertOk();
        $this->postJson("/api/v1/progress/lesson/{$this->lesson1Id}/complete")->assertOk();

        $this->assertEquals(1, Progress::where('user_id', $this->user->id)
            ->where('lesson_id', $this->lesson1Id)
            ->count());
    }

    public function test_user_can_view_my_progress(): void
    {
        $this->actingAs($this->user);

        Progress::create([
            'user_id' => $this->user->id,
            'lesson_id' => $this->lesson1Id,
            'completed_at' => now(),
        ]);

        $this->getJson('/api/v1/progress')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_user_cannot_see_other_user_progress(): void
    {
        $otherUser = User::factory()->create();
        $this->actingAs($this->user);

        Progress::create([
            'user_id' => $otherUser->id,
            'lesson_id' => $this->lesson1Id,
            'completed_at' => now(),
        ]);

        $this->getJson('/api/v1/progress')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_course_progress_returns_percentage(): void
    {
        $this->actingAs($this->user);

        Progress::create([
            'user_id' => $this->user->id,
            'lesson_id' => $this->lesson1Id,
            'completed_at' => now(),
        ]);

        $this->getJson("/api/v1/progress/course/{$this->course1Id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['course', 'progress_percent', 'completed_lessons', 'total_lessons'],
            ])
            ->assertJsonPath('data.completed_lessons', 1)
            ->assertJsonPath('data.total_lessons', 2)
            ->assertJsonPath('data.progress_percent', 50);
    }

    public function test_dashboard_returns_overview(): void
    {
        $this->actingAs($this->user);

        Progress::create([
            'user_id' => $this->user->id,
            'lesson_id' => $this->lesson1Id,
            'completed_at' => now(),
        ]);

        Attempt::create([
            'user_id' => $this->user->id,
            'quiz_id' => $this->quiz1Id,
            'score' => 80,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->getJson('/api/v1/progress/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'overview' => ['completed_lessons', 'quiz_attempts', 'average_score'],
                    'recent_activity',
                    'recent_attempts',
                ],
            ])
            ->assertJsonPath('data.overview.completed_lessons', 1)
            ->assertJsonPath('data.overview.quiz_attempts', 1)
            ->assertJsonPath('data.overview.average_score', 80);
    }

    public function test_my_progress_can_filter_by_course(): void
    {
        $this->actingAs($this->user);

        Progress::create([
            'user_id' => $this->user->id,
            'lesson_id' => $this->lesson1Id,
            'completed_at' => now(),
        ]);

        $this->getJson("/api/v1/progress?course_id={$this->course2Id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson("/api/v1/progress?course_id={$this->course1Id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }
}