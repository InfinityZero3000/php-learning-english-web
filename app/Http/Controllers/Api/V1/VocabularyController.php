<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VocabularyResource;
use App\Models\Vocabulary;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VocabularyController extends Controller
{
    public function show(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $vocabulary->load(['topic', 'lesson']);
        if ($request->user()) {
            $vocabulary->setAttribute('is_bookmarked', $vocabulary->bookmarks()->where('user_id', $request->user()->id)->exists());
        }

        return ApiResponse::success((new VocabularyResource($vocabulary))->resolve());
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'saved' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'errors' => $validator->errors(),
            ]);
        }

        if ($request->boolean('saved') && ! $request->user()) {
            return ApiResponse::error('UNAUTHENTICATED', 'Authentication is required.', 401);
        }

        $perPage = $request->integer('per_page', 20);
        $query = Vocabulary::query()->with(['topic', 'lesson'])->orderBy('id');

        if ($request->user()) {
            $query->withExists(['bookmarks as is_bookmarked' => fn ($bookmarks) => $bookmarks
                ->where('user_id', $request->user()->id)]);
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($words) use ($search): void {
                $words->where('word', 'like', "%{$search}%")
                    ->orWhere('meaning', 'like', "%{$search}%")
                    ->orWhere('definition', 'like', "%{$search}%");
            });
        }

        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->integer('topic_id'));
        }

        if ($request->boolean('saved')) {
            $query->whereHas('bookmarks', fn ($bookmarks) => $bookmarks->where('user_id', $request->user()->id));
        }

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            VocabularyResource::collection($page->items()),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }
}
