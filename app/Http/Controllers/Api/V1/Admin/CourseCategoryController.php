<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseCategoryResource;
use App\Models\CourseCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage', CourseCategory::class);

        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $query = CourseCategory::query()
            ->withCount('courses')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            CourseCategoryResource::collection($page->items()),
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
        Gate::authorize('manage', CourseCategory::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:course_categories,slug'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $category = CourseCategory::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);
        $category->applyLocalEdit([]);

        return ApiResponse::success(new CourseCategoryResource($category), 201);
    }

    public function show(CourseCategory $category): JsonResponse
    {
        Gate::authorize('manage', CourseCategory::class);

        $category->loadCount('courses');

        return ApiResponse::success(new CourseCategoryResource($category));
    }

    public function update(Request $request, CourseCategory $category): JsonResponse
    {
        Gate::authorize('manage', CourseCategory::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('course_categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $category->applyLocalEdit([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? $category->slug,
            'description' => $validated['description'] ?? $category->description,
            'icon' => $validated['icon'] ?? $category->icon,
            'color' => $validated['color'] ?? $category->color,
            'sort_order' => $validated['sort_order'] ?? $category->sort_order,
            'is_active' => $validated['is_active'] ?? $category->is_active,
        ]);

        return ApiResponse::success(new CourseCategoryResource($category));
    }

    public function destroy(CourseCategory $category): JsonResponse
    {
        Gate::authorize('manage', CourseCategory::class);

        if ($category->courses()->exists()) {
            return ApiResponse::error('CATEGORY_HAS_COURSES', 'Không thể xóa danh mục đang có khóa học.', 422);
        }

        $category->delete();

        return ApiResponse::success(null, 204);
    }
}
