<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLearningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_aggregates_require_google_admin_and_never_expose_learners(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $learner = $this->user('learner');
        $course = DB::table('courses')->first();
        $lesson = DB::table('lessons')->where('course_id', $course->id)->first();
        $quiz = DB::table('quizzes')->where('lesson_id', $lesson->id)->first();
        DB::table('enrollments')->insert([
            'user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed',
            'enrolled_at' => now()->subDays(2), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attempts')->insert([
            'user_id' => $learner->id, 'quiz_id' => $quiz->id, 'score' => 80,
            'started_at' => now()->subMinutes(2), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('progress')->insert([
            'user_id' => $learner->id, 'lesson_id' => $lesson->id, 'completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/learning/overview')->assertUnauthorized();
        $this->actingAs($learner)->getJson('/api/v1/admin/learning/overview')->assertForbidden();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/learning/overview')
            ->assertOk()->assertJsonPath('data.enrollments', 1)->assertJsonPath('data.completed_enrollments', 1);
        $payload = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($learner->email, $payload);
        foreach (['user_id', 'learner_id', 'email', 'name', 'response', 'feedback', 'metadata'] as $forbidden) {
            $this->assertStringNotContainsString('"'.$forbidden.'"', $payload);
        }

        auth()->logout();
        $this->actingAs($admin)->withSession([]);
        session()->forget('google_admin');
        $this->getJson('/api/v1/admin/learning/overview')->assertUnauthorized();
    }

    public function test_default_range_boundaries_empty_results_and_all_aggregate_endpoints(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $today = now('UTC')->toDateString();
        $from = now('UTC')->subDays(365)->toDateString();

        foreach (['overview', 'quizzes', 'fsrs', 'progress'] as $endpoint) {
            $this->actingAs($admin)->getJson("/api/v1/admin/learning/{$endpoint}")
                ->assertOk()->assertJsonPath('meta.to', $today)
                ->assertJsonPath('meta.from', now('UTC')->subDays(29)->toDateString());
        }
        $this->getJson("/api/v1/admin/learning/progress?from={$from}&to={$today}")->assertOk();
        $this->getJson('/api/v1/admin/learning/progress?from=2024-01-01&to=2025-01-01')
            ->assertUnprocessable();
        $this->getJson('/api/v1/admin/learning/progress?from=bad&to=2025-01-01')
            ->assertUnprocessable();
        $this->assertSame([], $this->getJson('/api/v1/admin/learning/quizzes')->json('data.by_course'));
    }

    public function test_report_is_real_csv_and_protects_formula_cells_after_whitespace_and_controls(): void
    {
        $this->seed();
        $admin = $this->user('super_admin');
        DB::table('courses')->insert([
            'title' => " \t=IMPORTXML(\"bad\")", 'slug' => 'unsafe-csv', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/api/v1/admin/learning/reports.csv')
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('period,course_id,course_title,enrollments,started,completed,completion_rate,average_score', $csv);
        $this->assertStringContainsString("'=IMPORTXML", $csv);
        $this->assertStringNotContainsString("\t=IMPORTXML", $csv);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]);
    }
}
