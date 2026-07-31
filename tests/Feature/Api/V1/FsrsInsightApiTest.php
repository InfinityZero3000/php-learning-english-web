<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Fsrs\FsrsCard;
use App\Domain\Fsrs\FsrsScheduler;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Lesson;
use App\Models\MediaListeningSession;
use App\Models\Progress;
use App\Models\Quiz;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FsrsInsightApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-07-29T12:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_uses_reviewed_cards_and_returns_the_learning_breakdown(): void
    {
        [$user, $course, $unit, $first, $second] = $this->learningContext();
        $review = $this->card($user, [
            'state' => 'review',
            'stability' => 10,
            'difficulty' => 4,
            'last_reviewed_at' => '2026-07-24T12:00:00Z',
            'due_at' => '2026-07-29T10:00:00Z',
        ]);
        $this->card($user, [
            'state' => 'relearning',
            'stability' => 5,
            'difficulty' => 6,
            'last_reviewed_at' => '2026-07-28T12:00:00Z',
            'due_at' => '2026-07-31T09:00:00Z',
        ]);
        $this->card($user, ['state' => 'learning', 'due_at' => null]);
        $this->card(User::factory()->create(), [
            'state' => 'review',
            'stability' => 100,
            'difficulty' => 1,
            'last_reviewed_at' => '2026-07-28T12:00:00Z',
            'due_at' => '2026-07-29T09:00:00Z',
        ]);

        Progress::create(['user_id' => $user->id, 'lesson_id' => $first->id, 'completed_at' => now()]);
        Progress::create(['user_id' => $user->id, 'lesson_id' => $second->id]);
        LearningSession::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $second->id,
            'status' => 'active',
            'started_at' => now()->subMinutes(5),
        ]);
        $quiz = Quiz::create(['lesson_id' => $first->id, 'title' => 'Checkpoint', 'status' => 'published']);
        Attempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'status' => 'completed',
            'score' => 80,
            'correct_answers' => 4,
            'total_questions' => 5,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(8),
        ]);
        MediaListeningSession::create([
            'user_id' => $user->id,
            'external_id' => 'video-1',
            'title' => 'Listening drill',
            'duration_seconds' => 600,
            'position_seconds' => 600,
            'watched_percentage' => 100,
            'shadowing_attempts' => 3,
            'best_pronunciation_score' => 88.5,
            'status' => 'completed',
            'completed_at' => now()->subHour(),
        ]);

        $expectedRetention = app(FsrsScheduler::class)->retrievability(
            new FsrsCard(
                state: 'review',
                stability: 10,
                difficulty: 4,
                due: new DateTimeImmutable('2026-07-29T10:00:00Z'),
                lastReview: new DateTimeImmutable('2026-07-24T12:00:00Z'),
            ),
            new DateTimeImmutable('2026-07-29T12:00:00Z'),
        );

        $response = $this->actingAs($user)->getJson('/api/v1/progress/dashboard')
            ->assertOk()
            ->assertJsonPath('data.type', 'progress')
            ->assertJsonPath('data.overview.completed_lessons', 1)
            ->assertJsonPath('data.overview.quiz_attempts', 1)
            ->assertJsonPath('data.overview.average_score', 80)
            ->assertJsonPath('data.fsrs.reviewed_cards', 2)
            ->assertJsonPath('data.fsrs.due_count', 2)
            ->assertJsonPath('data.fsrs.average_stability', 7.5)
            ->assertJsonPath('data.fsrs.average_difficulty', 5)
            ->assertJsonPath('data.fsrs.state_distribution.0.state', 'new')
            ->assertJsonPath('data.fsrs.state_distribution.0.count', 1)
            ->assertJsonPath('data.fsrs.state_distribution.1.state', 'learning')
            ->assertJsonPath('data.fsrs.state_distribution.1.count', 0)
            ->assertJsonPath('data.fsrs.state_distribution.2.state', 'review')
            ->assertJsonPath('data.fsrs.state_distribution.2.count', 1)
            ->assertJsonPath('data.fsrs.state_distribution.3.state', 'relearning')
            ->assertJsonPath('data.fsrs.state_distribution.3.count', 1)
            ->assertJsonCount(7, 'data.fsrs.forecast')
            ->assertJsonPath('data.fsrs.forecast.0.date', '2026-07-29')
            ->assertJsonPath('data.fsrs.forecast.0.count', 1)
            ->assertJsonPath('data.fsrs.forecast.2.date', '2026-07-31')
            ->assertJsonPath('data.fsrs.forecast.2.count', 1)
            ->assertJsonPath('data.courses.0.id', $course->id)
            ->assertJsonPath('data.courses.0.completed_lessons', 1)
            ->assertJsonPath('data.courses.0.total_lessons', 2)
            ->assertJsonPath('data.courses.0.progress_percent', 50)
            ->assertJsonPath('data.courses.0.units.0.id', $unit->id)
            ->assertJsonPath('data.courses.0.units.0.lessons.0.completed', true)
            ->assertJsonPath('data.courses.0.units.0.lessons.1.completed', false)
            ->assertJsonPath('data.recent_sessions.0.lesson.id', $second->id)
            ->assertJsonPath('data.quiz_performance.attempts', 1)
            ->assertJsonPath('data.quiz_performance.correct_rate', 80)
            ->assertJsonPath('data.listening.sessions', 1)
            ->assertJsonPath('data.listening.completed_sessions', 1)
            ->assertJsonPath('data.listening.shadowing_attempts', 3)
            ->assertJsonPath('data.listening.best_pronunciation_score', 88.5);

        $this->getJson("/api/v1/progress/course/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.completed_lessons', 1)
            ->assertJsonPath('data.total_lessons', 2)
            ->assertJsonPath('data.progress_percent', 50);

        $this->assertEqualsWithDelta(
            ($expectedRetention + app(FsrsScheduler::class)->retrievability(
                new FsrsCard(
                    state: 'relearning',
                    stability: 5,
                    difficulty: 6,
                    due: new DateTimeImmutable('2026-07-31T09:00:00Z'),
                    lastReview: new DateTimeImmutable('2026-07-28T12:00:00Z'),
                ),
                new DateTimeImmutable('2026-07-29T12:00:00Z'),
            )) / 2,
            $response->json('data.fsrs.retention'),
            0.0001,
        );
        $this->assertSame(3, UserVocabulary::where('user_id', $user->id)->count());
        $this->assertSame($review->revision, $review->fresh()->revision);
    }

    public function test_dashboard_returns_null_fsrs_averages_for_an_empty_review_population(): void
    {
        $user = User::factory()->create();
        $this->card($user, ['state' => 'learning', 'due_at' => null]);

        $this->actingAs($user)->getJson('/api/v1/progress/dashboard')
            ->assertOk()
            ->assertJsonPath('data.fsrs.reviewed_cards', 0)
            ->assertJsonPath('data.fsrs.retention', null)
            ->assertJsonPath('data.fsrs.average_stability', null)
            ->assertJsonPath('data.fsrs.average_difficulty', null)
            ->assertJsonCount(7, 'data.fsrs.forecast');
    }

    public function test_reviewed_card_with_missing_difficulty_still_contributes_to_retention(): void
    {
        $user = User::factory()->create();
        $this->card($user, [
            'state' => 'review',
            'stability' => 12,
            'difficulty' => null,
            'last_reviewed_at' => '2026-07-28T12:00:00Z',
            'due_at' => '2026-07-30T12:00:00Z',
        ]);

        $this->actingAs($user)->getJson('/api/v1/progress/dashboard')
            ->assertOk()
            ->assertJsonPath('data.fsrs.reviewed_cards', 1)
            ->assertJsonPath('data.fsrs.average_stability', 12)
            ->assertJsonPath('data.fsrs.average_difficulty', null)
            ->assertJson(fn ($json) => $json->whereType('data.fsrs.retention', 'double')->etc());
    }

    public function test_preview_is_scoped_revision_safe_and_non_mutating(): void
    {
        $owner = User::factory()->create();
        $state = $this->card($owner, [
            'state' => 'learning',
            'due_at' => '2026-07-29T11:00:00Z',
            'revision' => 3,
        ]);
        $other = User::factory()->create();
        $otherState = $this->card($other, ['due_at' => '2026-07-29T11:00:00Z']);

        $this->actingAs($owner)->postJson('/api/v1/fsrs/preview', [
            'card_id' => $otherState->id,
            'base_revision' => 0,
        ])->assertUnprocessable();

        $this->postJson('/api/v1/fsrs/preview', [
            'card_id' => $state->id,
            'base_revision' => 2,
        ])->assertConflict();

        $before = $state->fresh()->toArray();
        $response = $this->postJson('/api/v1/fsrs/preview', [
            'card_id' => $state->id,
            'base_revision' => 3,
        ])->assertOk()
            ->assertJsonPath('data.type', 'fsrs_preview')
            ->assertJsonPath('data.card_id', $state->id)
            ->assertJsonPath('data.base_revision', 3)
            ->assertJsonPath('data.generated_at', '2026-07-29T12:00:00.000000Z')
            ->assertJsonCount(4, 'data.ratings')
            ->assertJsonPath('data.ratings.0.rating', 'again')
            ->assertJsonPath('data.ratings.1.rating', 'hard')
            ->assertJsonPath('data.ratings.2.rating', 'good')
            ->assertJsonPath('data.ratings.3.rating', 'easy');

        foreach ($response->json('data.ratings') as $rating) {
            $this->assertIsString($rating['due_at']);
            $this->assertGreaterThan(0, $rating['interval_seconds']);
        }
        $this->assertSame($before, $state->fresh()->toArray());
    }

    private function learningContext(): array
    {
        $user = User::factory()->create();
        $course = Course::create([
            'title' => 'Progress course',
            'slug' => 'progress-'.Str::random(8),
            'status' => 'published',
        ]);
        $unit = Unit::create([
            'course_id' => $course->id,
            'title' => 'Core',
            'sort_order' => 0,
            'status' => 'published',
        ]);
        $first = $this->lesson($course, $unit, 'First', 0);
        $second = $this->lesson($course, $unit, 'Second', 1);
        $hiddenUnit = Unit::create([
            'course_id' => $course->id,
            'title' => 'Hidden',
            'sort_order' => 1,
            'status' => 'draft',
        ]);
        $this->lesson($course, $hiddenUnit, 'Hidden', 0);
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        return [$user, $course, $unit, $first, $second];
    }

    private function lesson(Course $course, Unit $unit, string $title, int $order): Lesson
    {
        return Lesson::create([
            'course_id' => $course->id,
            'unit_id' => $unit->id,
            'title' => $title,
            'slug' => strtolower($title).'-'.Str::random(6),
            'sort_order' => $order,
            'status' => 'published',
        ]);
    }

    private function card(User $user, array $attributes): UserVocabulary
    {
        $word = Vocabulary::create([
            'word' => 'word-'.Str::random(8),
            'meaning' => 'meaning',
        ]);

        return UserVocabulary::create([
            'user_id' => $user->id,
            'vocabulary_id' => $word->id,
            ...$attributes,
        ]);
    }
}
