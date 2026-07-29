<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\Level;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = Course::query()
            ->with(['level', 'category'])
            ->withCount('lessons', 'units');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->integer('level_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $sortBy = $request->string('sort_by')->toString();
        match ($sortBy) {
            'title' => $query->orderBy('title'),
            'created_at' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('updated_at'),
        };

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            CourseResource::collection($page->items()),
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:courses,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'language' => ['nullable', 'string', 'max:10'],
            'thumbnail_url' => ['nullable', 'url', 'max:2048'],
            'estimated_duration' => ['nullable', 'integer', 'min:0'],
            'total_xp' => ['nullable', 'integer', 'min:0'],
            'level_id' => ['nullable', 'integer', 'exists:levels,id'],
            'category_id' => ['nullable', 'integer', 'exists:course_categories,id'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']).'-'.Str::random(6);
        $validated['status'] = $validated['status'] ?? 'draft';

        $course = Course::create($validated);

        Log::info('admin.course.created', [
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
        ]);

        $course->load(['level', 'category']);
        $course->loadCount('lessons', 'units');

        return ApiResponse::success(new CourseResource($course), 201);
    }

    public function show(Course $course): JsonResponse
    {
        $course->load(['level', 'category', 'topics', 'units']);
        $course->loadCount('lessons', 'units');

        return ApiResponse::success(new CourseResource($course));
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:courses,slug,'.$course->id],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'language' => ['nullable', 'string', 'max:10'],
            'thumbnail_url' => ['nullable', 'url', 'max:2048'],
            'estimated_duration' => ['nullable', 'integer', 'min:0'],
            'total_xp' => ['nullable', 'integer', 'min:0'],
            'level_id' => ['nullable', 'integer', 'exists:levels,id'],
            'category_id' => ['nullable', 'integer', 'exists:course_categories,id'],
        ]);

        // Never overwrite external_id from LexiLingo
        unset($validated['external_id']);

        $course->update($validated);

        Log::info('admin.course.updated', [
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
        ]);

        $course->load(['level', 'category']);
        $course->loadCount('lessons', 'units');

        return ApiResponse::success(new CourseResource($course));
    }

    public function destroy(Request $request, Course $course): JsonResponse
    {
        Log::info('admin.course.deleted', [
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
            'title' => $course->title,
        ]);

        $course->delete();

        return response()->json(null, 204);
    }

    public function levels(): JsonResponse
    {
        return ApiResponse::success(Level::orderBy('sort_order')->get()->map(fn ($l) => [
            'id' => $l->id,
            'name' => $l->name,
            'slug' => $l->slug,
        ]));
    }
}
