<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TopicResource;
use App\Models\Topic;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TopicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage', Topic::class);

        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $query = Topic::query()
            ->withCount(['courses', 'vocabularies'])
            ->orderBy('name');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            TopicResource::collection($page->items()),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage', Topic::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:topics,slug'],
        ]);

        $topic = Topic::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
        ]);

        return ApiResponse::success(new TopicResource($topic), 201);
    }

    public function show(Topic $topic): JsonResponse
    {
        Gate::authorize('manage', Topic::class);

        $topic->loadCount(['courses', 'vocabularies']);

        return ApiResponse::success(new TopicResource($topic));
    }

    public function update(Request $request, Topic $topic): JsonResponse
    {
        Gate::authorize('manage', Topic::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('topics', 'slug')->ignore($topic->id)],
        ]);

        $topic->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? $topic->slug,
        ]);

        return ApiResponse::success(new TopicResource($topic));
    }

    public function destroy(Topic $topic): JsonResponse
    {
        Gate::authorize('manage', Topic::class);

        $topic->delete();

        return ApiResponse::success(null, 204);
    }
}
