<?php

namespace App\Services;

use App\Domain\Fsrs\FsrsCard;
use App\Domain\Fsrs\FsrsConfig;
use App\Domain\Fsrs\FsrsScheduler;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\VocabularyReview;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VocabularyReviewService
{
    public function __construct(private readonly FsrsScheduler $scheduler) {}

    public function review(User $user, array $input): array
    {
        return DB::transaction(function () use ($user, $input): array {
            $existing = VocabularyReview::query()->where('request_id', $input['request_id'])->first();
            if ($existing) {
                if (! $this->sameRequest($existing, $user, $input)) {
                    throw new ConflictHttpException('Request ID was already used with a different payload.');
                }

                return $this->reviewPayload($existing);
            }

            $session = LearningSession::query()
                ->whereKey($input['learning_session_id'])
                ->where('user_id', $user->id)
                ->whereIn('status', ['planned', 'active'])
                ->first();
            if (! $session) {
                throw new HttpException(403, 'Learning session is not available to this user.');
            }

            $state = UserVocabulary::query()
                ->where('user_id', $user->id)
                ->where('vocabulary_id', $input['vocabulary_id'])
                ->lockForUpdate()
                ->firstOrCreate([], [
                    'state' => 'learning',
                    'step' => 0,
                    'due_at' => now('UTC'),
                    'algorithm' => 'fsrs-6',
                    'algorithm_version' => FsrsConfig::VERSION,
                ]);

            if ((int) $state->revision !== (int) $input['base_revision']) {
                throw new ConflictHttpException('Review state changed; refresh before rating again.');
            }

            $reviewedAt = CarbonImmutable::now('UTC');
            $before = $this->stateSnapshot($state);
            $card = new FsrsCard(
                state: $state->state,
                step: $state->step,
                stability: $state->stability,
                difficulty: $state->difficulty,
                due: new DateTimeImmutable(($state->due_at ?? $reviewedAt)->clone()->utc()->format('c')),
                lastReview: $state->last_reviewed_at
                    ? new DateTimeImmutable($state->last_reviewed_at->clone()->utc()->format('c'))
                    : null,
            );
            $result = $this->scheduler->review(
                $card,
                (int) $input['rating'],
                new DateTimeImmutable($reviewedAt->format('c')),
            );
            $nextRevision = (int) $state->revision + 1;

            $state->forceFill([
                'state' => $result->card->state,
                'step' => $result->card->step,
                'due_at' => $result->card->due,
                'stability' => $result->card->stability,
                'difficulty' => $result->card->difficulty,
                'scheduled_days' => $result->scheduledDays,
                'elapsed_days' => $result->elapsedDays,
                'repetitions' => (int) $state->repetitions + 1,
                'lapses' => (int) $state->lapses + ((int) $input['rating'] === 1 ? 1 : 0),
                'last_reviewed_at' => $reviewedAt,
                'algorithm' => 'fsrs-6',
                'algorithm_version' => FsrsConfig::VERSION,
                'revision' => $nextRevision,
            ])->save();

            $after = $this->stateSnapshot($state);
            $review = VocabularyReview::create([
                'user_vocabulary_id' => $state->id,
                'request_id' => $input['request_id'],
                'rating' => $input['rating'],
                'response_time_ms' => $input['response_time_ms'] ?? null,
                'stability' => $state->stability,
                'difficulty' => $state->difficulty,
                'scheduled_days' => $state->scheduled_days,
                'elapsed_days' => $state->elapsed_days,
                'reviewed_at' => $reviewedAt,
                'algorithm' => 'fsrs-6',
                'algorithm_version' => FsrsConfig::VERSION,
                'base_revision' => $input['base_revision'],
                'resulting_revision' => $nextRevision,
                ...$this->prefixedSnapshot('before', $before),
                ...$this->prefixedSnapshot('after', $after),
            ]);
            $review->refresh();

            LearningEvent::create([
                'learning_session_id' => $session->id,
                'user_id' => $user->id,
                'vocabulary_review_id' => $review->id,
                'event_type' => 'vocabulary_review',
                'request_id' => $input['request_id'],
                'duration_ms' => $input['response_time_ms'] ?? null,
                'occurred_at' => $reviewedAt,
                'metadata' => ['rating' => (int) $input['rating']],
            ]);

            return $this->reviewPayload($review);
        }, 3);
    }

    private function sameRequest(VocabularyReview $review, User $user, array $input): bool
    {
        return $review->userVocabulary()->where('user_id', $user->id)->exists()
            && (int) $review->userVocabulary->vocabulary_id === (int) $input['vocabulary_id']
            && (int) $review->rating === (int) $input['rating']
            && (int) $review->base_revision === (int) $input['base_revision']
            && (int) ($review->response_time_ms ?? 0) === (int) ($input['response_time_ms'] ?? 0)
            && $review->vocabularyReviewEvent()
                ->where('learning_session_id', $input['learning_session_id'])
                ->exists();
    }

    private function stateSnapshot(UserVocabulary $state): array
    {
        return [
            'state' => $state->state,
            'step' => $state->step,
            'due_at' => $state->due_at,
            'stability' => $state->stability,
            'difficulty' => $state->difficulty,
            'scheduled_days' => $state->scheduled_days,
            'last_reviewed_at' => $state->last_reviewed_at,
        ];
    }

    private function prefixedSnapshot(string $prefix, array $snapshot): array
    {
        return collect($snapshot)->mapWithKeys(
            fn ($value, string $key): array => ["{$prefix}_{$key}" => $value]
        )->all();
    }

    private function reviewPayload(VocabularyReview $review): array
    {
        return [
            'request_id' => $review->request_id,
            'vocabulary_id' => $review->userVocabulary->vocabulary_id,
            'rating' => $review->rating,
            'state' => $review->after_state,
            'step' => $review->after_step,
            'due_at' => $review->after_due_at?->toISOString(),
            'stability' => $review->after_stability,
            'difficulty' => $review->after_difficulty,
            'scheduled_days' => $review->after_scheduled_days,
            'revision' => $review->resulting_revision,
            'algorithm' => $review->algorithm_version,
        ];
    }
}
