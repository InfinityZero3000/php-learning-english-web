<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReviewVocabularyRequest;
use App\Models\UserVocabulary;
use App\Services\VocabularyReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FsrsController extends Controller
{
    public function due(Request $request): JsonResponse
    {
        $limit = min(100, max(1, $request->integer('limit', 20)));
        $items = UserVocabulary::query()
            ->with('vocabulary')
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '<=', now('UTC')))
            ->orderBy('due_at')
            ->limit($limit)
            ->get()
            ->map(fn (UserVocabulary $state): array => $this->statePayload($state));

        return ApiResponse::success($items);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = UserVocabulary::query()->where('user_id', $request->user()->id);

        return ApiResponse::success([
            'total' => (clone $query)->count(),
            'due_now' => (clone $query)->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '<=', now('UTC')))->count(),
            'average_stability' => round((float) ((clone $query)->avg('stability') ?? 0), 2),
            'average_difficulty' => round((float) ((clone $query)->avg('difficulty') ?? 0), 2),
        ]);
    }

    public function review(ReviewVocabularyRequest $request, VocabularyReviewService $reviews): JsonResponse
    {
        return ApiResponse::success($reviews->review($request->user(), $request->validated()));
    }

    private function statePayload(UserVocabulary $state): array
    {
        return [
            'vocabulary' => [
                'id' => $state->vocabulary->id,
                'word' => $state->vocabulary->word,
                'meaning' => $state->vocabulary->meaning,
                'definition' => $state->vocabulary->definition,
                'example' => $state->vocabulary->example,
                'pronunciation' => $state->vocabulary->pronunciation,
                'audio_url' => $state->vocabulary->external_audio_url,
            ],
            'state' => $state->state,
            'step' => $state->step,
            'due_at' => $state->due_at?->toISOString(),
            'stability' => $state->stability,
            'difficulty' => $state->difficulty,
            'revision' => $state->revision,
        ];
    }
}
