<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LevelResource;
use App\Models\Level;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LevelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage', Level::class);

        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $query = Level::query()
            ->withCount('courses')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            LevelResource::collection($page->items()),
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
        Gate::authorize('manage', Level::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:levels,slug'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $level = Level::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return ApiResponse::success(new LevelResource($level), 201);
    }

    public function show(Level $level): JsonResponse
    {
        Gate::authorize('manage', Level::class);

        $level->loadCount('courses');

        return ApiResponse::success(new LevelResource($level));
    }

    public function update(Request $request, Level $level): JsonResponse
    {
        Gate::authorize('manage', Level::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('levels', 'slug')->ignore($level->id)],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $level->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? $level->slug,
            'sort_order' => $validated['sort_order'] ?? $level->sort_order,
        ]);

        return ApiResponse::success(new LevelResource($level));
    }

    public function destroy(Level $level): JsonResponse
    {
        Gate::authorize('manage', Level::class);

        $level->delete();

        return ApiResponse::success(null, 204);
    }
}
