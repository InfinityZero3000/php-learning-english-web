<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseCategoryResource;
use App\Models\CourseCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CategoryAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = CourseCategory::query()->withCount('courses');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->integer('is_active'));
        }

        $query->orderBy('sort_order')->orderBy('name');

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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:course_categories,slug'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        $category = CourseCategory::create($validated);

        Log::info('admin.category.created', [
            'user_id' => $request->user()->id,
            'category_id' => $category->id,
        ]);

        return ApiResponse::success(new CourseCategoryResource($category), 201);
    }

    public function show(CourseCategory $category): JsonResponse
    {
        $category->loadCount('courses');

        return ApiResponse::success(new CourseCategoryResource($category));
    }

    public function update(Request $request, CourseCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:course_categories,slug,'.$category->id],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Never overwrite external_id from LexiLingo
        unset($validated['external_id']);

        $category->update($validated);

        Log::info('admin.category.updated', [
            'user_id' => $request->user()->id,
            'category_id' => $category->id,
        ]);

        return ApiResponse::success(new CourseCategoryResource($category));
    }

    public function destroy(Request $request, CourseCategory $category): JsonResponse
    {
        if ($category->courses()->exists()) {
            return ApiResponse::error(
                'CATEGORY_HAS_COURSES',
                'Không thể xóa danh mục đang có khóa học.',
                422,
            );
        }

        Log::info('admin.category.deleted', [
            'user_id' => $request->user()->id,
            'category_id' => $category->id,
            'name' => $category->name,
        ]);

        $category->delete();

        return response()->json(null, 204);
    }
}
