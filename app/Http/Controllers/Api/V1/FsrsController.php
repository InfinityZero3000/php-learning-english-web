<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Fsrs\FsrsCard;
use App\Domain\Fsrs\FsrsScheduler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PreviewFsrsRequest;
use App\Http\Requests\Api\V1\ReviewVocabularyRequest;
use App\Models\UserVocabulary;
use App\Services\VocabularyReviewService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FsrsController extends Controller
{
    public function due(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $page = UserVocabulary::query()
            ->with('vocabulary')
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '<=', now('UTC')))
            ->orderBy('due_at')
            ->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (UserVocabulary $state): array => $this->statePayload($state)),
            meta: ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        );
    }

    public function stats(Request $request): JsonResponse
    {
        $query = UserVocabulary::query()->where('user_id', $request->user()->id);

        return ApiResponse::success([
            'type' => 'fsrs_stats',
            'due_count' => (clone $query)->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '<=', now('UTC')))->count(),
            'new_count' => (clone $query)->where('state', 'learning')->whereNull('last_reviewed_at')->count(),
            'learning_count' => (clone $query)->whereIn('state', ['learning', 'relearning'])->count(),
            'review_count' => (clone $query)->where('state', 'review')->count(),
            'average_stability' => round((float) ((clone $query)->avg('stability') ?? 0), 2),
            'average_difficulty' => round((float) ((clone $query)->avg('difficulty') ?? 0), 2),
        ]);
    }

    public function review(ReviewVocabularyRequest $request, VocabularyReviewService $reviews): JsonResponse
    {
        return ApiResponse::success($reviews->review($request->user(), $request->validated()));
    }

    public function preview(PreviewFsrsRequest $request, FsrsScheduler $scheduler): JsonResponse
    {
        $input = $request->validated();
        $state = UserVocabulary::query()
            ->whereKey($input['card_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        if ((int) $state->revision !== (int) $input['base_revision']) {
            throw new ConflictHttpException('Review state changed; refresh before rating again.');
        }

        $now = CarbonImmutable::now('UTC');
        $date = new DateTimeImmutable($now->format('c'));
        $card = new FsrsCard(
            state: $state->state,
            step: $state->step,
            stability: $state->stability,
            difficulty: $state->difficulty,
            due: new DateTimeImmutable(($state->due_at ?? $now)->clone()->utc()->format('c')),
            lastReview: $state->last_reviewed_at
                ? new DateTimeImmutable($state->last_reviewed_at->clone()->utc()->format('c'))
                : null,
        );
        $labels = [1 => 'again', 2 => 'hard', 3 => 'good', 4 => 'easy'];
        $ratings = collect($scheduler->preview($card, $date))
            ->map(fn ($result, int $rating): array => [
                'rating' => $labels[$rating],
                'due_at' => CarbonImmutable::instance($result->card->due)->toISOString(),
                'interval_seconds' => max(1, $result->card->due->getTimestamp() - $date->getTimestamp()),
            ])->values();

        return ApiResponse::success([
            'type' => 'fsrs_preview',
            'card_id' => $state->id,
            'base_revision' => (int) $state->revision,
            'generated_at' => $now->toISOString(),
            'ratings' => $ratings,
        ]);
    }

    private function statePayload(UserVocabulary $state): array
    {
        return [
            'type' => 'fsrs_card',
            'id' => $state->id,
            'vocabulary_id' => $state->vocabulary->id,
            'word' => $state->vocabulary->word,
            'meaning' => $state->vocabulary->meaning,
            'definition' => $state->vocabulary->definition,
            'example' => $state->vocabulary->example,
            'pronunciation' => $state->vocabulary->pronunciation,
            'audio_url' => $state->vocabulary->external_audio_url,
            'state' => $state->state,
            'step' => $state->step,
            'due_at' => $state->due_at?->toISOString(),
            'stability' => $state->stability,
            'difficulty' => $state->difficulty,
            'revision' => $state->revision,
        ];
    }
}
