<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookmarkResource;
use App\Models\Bookmark;
use App\Models\Lesson;
use App\Models\Vocabulary;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookmarkApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = Bookmark::query()
            ->where('user_id', $request->user()->id)
            ->with(['vocabulary', 'lesson']);

        if ($request->filled('bookmark_type')) {
            $query->where('bookmark_type', $request->string('bookmark_type'));
        }

        $page = $query->orderByDesc('created_at')->paginate($perPage);

        return ApiResponse::success(
            BookmarkResource::collection($page->items()),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function toggleVocabulary(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        return $this->toggle($request, [
            'vocabulary_id' => $vocabulary->id,
            'bookmark_type' => 'vocabulary',
        ]);
    }

    public function toggleLesson(Request $request, Lesson $lesson): JsonResponse
    {
        return $this->toggle($request, [
            'vocabulary_id' => null,
            'lesson_id' => $lesson->id,
            'bookmark_type' => 'lesson',
        ]);
    }

    private function toggle(Request $request, array $attributes): JsonResponse
    {
        return DB::transaction(function () use ($request, $attributes): JsonResponse {
            $userId = $request->user()->id;
            $request->user()->newQuery()->whereKey($userId)->lockForUpdate()->firstOrFail();

            $bookmark = Bookmark::query()
                ->where('user_id', $userId)
                ->where('bookmark_type', $attributes['bookmark_type'])
                ->when(
                    $attributes['bookmark_type'] === 'lesson',
                    fn ($query) => $query->where('lesson_id', $attributes['lesson_id']),
                    fn ($query) => $query->where('vocabulary_id', $attributes['vocabulary_id']),
                )
                ->first();

            if ($bookmark) {
                $bookmark->delete();

                return ApiResponse::success(['status' => 'unbookmarked']);
            }

            Bookmark::create(['user_id' => $userId, ...$attributes]);

            return ApiResponse::success(['status' => 'bookmarked'], 201);
        }, 3);
    }
}
