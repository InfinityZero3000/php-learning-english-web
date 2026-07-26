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
        $userId = $request->user()->id;

        $existing = Bookmark::where('user_id', $userId)
            ->where('vocabulary_id', $vocabulary->id)
            ->where('bookmark_type', 'vocabulary')
            ->first();

        if ($existing) {
            $existing->delete();

            return ApiResponse::success(['status' => 'unbookmarked']);
        }

        Bookmark::create([
            'user_id' => $userId,
            'vocabulary_id' => $vocabulary->id,
            'bookmark_type' => 'vocabulary',
        ]);

        return ApiResponse::success(['status' => 'bookmarked'], 201);
    }

    public function toggleLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $userId = $request->user()->id;

        $existing = Bookmark::where('user_id', $userId)
            ->where('lesson_id', $lesson->id)
            ->where('bookmark_type', 'lesson')
            ->first();

        if ($existing) {
            $existing->delete();

            return ApiResponse::success(['status' => 'unbookmarked']);
        }

        Bookmark::create([
            'user_id' => $userId,
            'vocabulary_id' => null,
            'lesson_id' => $lesson->id,
            'bookmark_type' => 'lesson',
        ]);

        return ApiResponse::success(['status' => 'bookmarked'], 201);
    }
}
