<?php

namespace App\Services;

use App\Domain\Fsrs\FsrsCard;
use App\Domain\Fsrs\FsrsScheduler;
use App\Models\Attempt;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\MediaListeningSession;
use App\Models\Progress;
use App\Models\User;
use App\Models\UserVocabulary;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Collection;

class LearnerProgressSummary
{
    public function __construct(private readonly FsrsScheduler $scheduler) {}

    public function build(User $user): array
    {
        $now = CarbonImmutable::now('UTC');
        $cards = UserVocabulary::query()->where('user_id', $user->id)->get();
        $reviewed = $cards->filter(
            fn (UserVocabulary $card): bool => $card->last_reviewed_at !== null
                && (float) $card->stability > 0
        );
        $completedProgress = Progress::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with('lesson.course')
            ->get();
        $attempts = Attempt::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with('quiz.lesson')
            ->latest('completed_at')
            ->get();

        return [
            'type' => 'progress',
            'overview' => [
                'completed_lessons' => $completedProgress->count(),
                'quiz_attempts' => $attempts->count(),
                'average_score' => round((float) ($attempts->avg('score') ?? 0), 2),
            ],
            'fsrs' => $this->fsrs($cards, $reviewed, $now),
            'courses' => $this->courses($user, $completedProgress->pluck('lesson_id')),
            'recent_sessions' => $this->recentSessions($user),
            'quiz_performance' => $this->quizPerformance($attempts),
            'listening' => $this->listening($user),
            'recent_activity' => $completedProgress
                ->sortByDesc('completed_at')
                ->take(5)
                ->values()
                ->map(fn (Progress $progress): array => [
                    'type' => 'progress',
                    'id' => $progress->id,
                    'lesson_id' => $progress->lesson_id,
                    'completed_at' => $progress->completed_at?->toISOString(),
                    'lesson' => [
                        'id' => $progress->lesson->id,
                        'title' => $progress->lesson->title,
                        'course_id' => $progress->lesson->course_id,
                    ],
                ])->all(),
            'recent_attempts' => $attempts->take(5)->map(fn (Attempt $attempt): array => [
                'id' => $attempt->id,
                'quiz_id' => $attempt->quiz_id,
                'score' => $attempt->score,
                'completed_at' => $attempt->completed_at?->toISOString(),
            ])->all(),
        ];
    }

    private function fsrs(Collection $cards, Collection $reviewed, CarbonImmutable $now): array
    {
        $retention = $reviewed->map(fn (UserVocabulary $card): float => $this->scheduler->retrievability(
            $this->fsrsCard($card, $now),
            new DateTimeImmutable($now->format('c')),
        ));
        $forecast = collect(range(0, 6))->map(function (int $offset) use ($cards, $now): array {
            $date = $now->addDays($offset)->toDateString();

            return [
                'date' => $date,
                'count' => $cards->filter(
                    fn (UserVocabulary $card): bool => $card->due_at?->clone()->utc()->toDateString() === $date
                )->count(),
            ];
        });

        return [
            'reviewed_cards' => $reviewed->count(),
            'due_count' => $cards->filter(
                fn (UserVocabulary $card): bool => $card->due_at === null || $card->due_at->lte($now)
            )->count(),
            'retention' => $retention->isEmpty() ? null : round((float) $retention->avg(), 4),
            'average_stability' => $reviewed->isEmpty() ? null : round((float) $reviewed->avg('stability'), 2),
            'average_difficulty' => $reviewed->whereNotNull('difficulty')->isEmpty()
                ? null
                : round((float) $reviewed->avg('difficulty'), 2),
            'state_distribution' => [
                [
                    'state' => 'new',
                    'count' => $cards->where('state', 'learning')->whereNull('last_reviewed_at')->count(),
                ],
                [
                    'state' => 'learning',
                    'count' => $cards->where('state', 'learning')->whereNotNull('last_reviewed_at')->count(),
                ],
                ['state' => 'review', 'count' => $cards->where('state', 'review')->count()],
                ['state' => 'relearning', 'count' => $cards->where('state', 'relearning')->count()],
            ],
            'forecast' => $forecast->all(),
        ];
    }

    private function courses(User $user, Collection $completedLessonIds): array
    {
        $completed = $completedLessonIds->mapWithKeys(fn (int $id): array => [$id => true]);
        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'completed'])
            ->whereHas('course', fn ($query) => $query->where('status', 'published'))
            ->with([
                'course',
                'course.units' => fn ($query) => $query->where('status', 'published')->orderBy('sort_order')->orderBy('id'),
                'course.units.lessons' => fn ($query) => $query->where('status', 'published')->orderBy('sort_order')->orderBy('id'),
                'course.lessons' => fn ($query) => $query->whereNull('unit_id')->where('status', 'published')->orderBy('sort_order')->orderBy('id'),
            ])
            ->latest('enrolled_at')
            ->get();

        return $enrollments->map(function (Enrollment $enrollment) use ($completed): array {
            $course = $enrollment->course;
            $units = $course->units->map(
                fn ($unit): array => $this->unitPayload($unit->id, $unit->title, $unit->lessons, $completed)
            );
            if ($course->lessons->isNotEmpty()) {
                $units->push($this->unitPayload(null, 'General', $course->lessons, $completed));
            }
            $total = $units->sum('total_lessons');
            $done = $units->sum('completed_lessons');

            return [
                'id' => $course->id,
                'title' => $course->title,
                'enrollment_status' => $enrollment->status,
                'completed_lessons' => $done,
                'total_lessons' => $total,
                'progress_percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
                'units' => $units->all(),
            ];
        })->all();
    }

    private function unitPayload(?int $id, string $title, Collection $lessons, Collection $completed): array
    {
        $items = $lessons->map(fn ($lesson): array => [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'completed' => $completed->has($lesson->id),
        ]);

        return [
            'id' => $id,
            'title' => $title,
            'completed_lessons' => $items->where('completed', true)->count(),
            'total_lessons' => $items->count(),
            'lessons' => $items->all(),
        ];
    }

    private function recentSessions(User $user): array
    {
        return LearningSession::query()
            ->where('user_id', $user->id)
            ->with(['course:id,title', 'lesson:id,title'])
            ->latest('started_at')
            ->limit(5)
            ->get()
            ->map(fn (LearningSession $session): array => [
                'id' => $session->id,
                'status' => $session->status,
                'started_at' => $session->started_at?->toISOString(),
                'completed_at' => $session->completed_at?->toISOString(),
                'course' => $session->course ? ['id' => $session->course->id, 'title' => $session->course->title] : null,
                'lesson' => $session->lesson ? ['id' => $session->lesson->id, 'title' => $session->lesson->title] : null,
            ])->all();
    }

    private function quizPerformance(Collection $attempts): array
    {
        $totalQuestions = (int) $attempts->sum('total_questions');

        return [
            'attempts' => $attempts->count(),
            'average_score' => $attempts->isEmpty() ? null : round((float) $attempts->avg('score'), 2),
            'correct_rate' => $totalQuestions > 0
                ? round((int) $attempts->sum('correct_answers') / $totalQuestions * 100, 2)
                : null,
            'recent' => $attempts->take(5)->map(fn (Attempt $attempt): array => [
                'id' => $attempt->id,
                'quiz_id' => $attempt->quiz_id,
                'quiz_title' => $attempt->quiz->title,
                'lesson_title' => $attempt->quiz->lesson->title,
                'score' => $attempt->score,
                'completed_at' => $attempt->completed_at?->toISOString(),
            ])->all(),
        ];
    }

    private function listening(User $user): array
    {
        $sessions = MediaListeningSession::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->get();

        return [
            'sessions' => $sessions->count(),
            'completed_sessions' => $sessions->filter(
                fn (MediaListeningSession $session): bool => $session->completed_at !== null
            )->count(),
            'minutes_listened' => (int) round($sessions->sum('position_seconds') / 60),
            'shadowing_attempts' => (int) $sessions->sum('shadowing_attempts'),
            'best_pronunciation_score' => $sessions->max('best_pronunciation_score'),
            'recent' => $sessions->take(5)->map(fn (MediaListeningSession $session): array => [
                'id' => $session->id,
                'title' => $session->title,
                'watched_percentage' => $session->watched_percentage,
                'shadowing_attempts' => $session->shadowing_attempts,
                'best_pronunciation_score' => $session->best_pronunciation_score,
                'status' => $session->status,
            ])->all(),
        ];
    }

    private function fsrsCard(UserVocabulary $state, CarbonImmutable $now): FsrsCard
    {
        return new FsrsCard(
            state: $state->state,
            step: $state->step,
            stability: $state->stability,
            difficulty: $state->difficulty,
            due: new DateTimeImmutable(($state->due_at ?? $now)->clone()->utc()->format('c')),
            lastReview: $state->last_reviewed_at
                ? new DateTimeImmutable($state->last_reviewed_at->clone()->utc()->format('c'))
                : null,
        );
    }
}
