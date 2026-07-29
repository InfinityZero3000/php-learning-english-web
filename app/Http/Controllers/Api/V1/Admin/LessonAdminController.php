<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LessonAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = Lesson::query()
            ->with('course')
            ->withCount('quizzes', 'vocabularies');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->integer('course_id'));
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->integer('unit_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('lesson_type')) {
            $query->where('lesson_type', $request->string('lesson_type'));
        }

        $query->orderBy('course_id')->orderBy('sort_order');

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            LessonResource::collection($page->items()),
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
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'lesson_type' => ['nullable', 'string', 'max:50'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
            'pass_threshold' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']).'-'.Str::random(6);
        $validated['status'] = $validated['status'] ?? 'draft';

        $lesson = Lesson::create($validated);

        Log::info('admin.lesson.created', [
            'user_id' => $request->user()->id,
            'lesson_id' => $lesson->id,
        ]);

        $lesson->load('course');
        $lesson->loadCount('quizzes', 'vocabularies');

        return ApiResponse::success(new LessonResource($lesson), 201);
    }

    public function show(Lesson $lesson): JsonResponse
    {
        $lesson->load(['course', 'quizzes', 'vocabularies']);
        $lesson->loadCount('quizzes', 'vocabularies');

        return ApiResponse::success(new LessonResource($lesson));
    }

    public function update(Request $request, Lesson $lesson): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['sometimes', 'required', 'integer', 'exists:courses,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'lesson_type' => ['nullable', 'string', 'max:50'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
            'pass_threshold' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        // Never overwrite external_id from LexiLingo
        unset($validated['external_id']);

        $lesson->update($validated);

        Log::info('admin.lesson.updated', [
            'user_id' => $request->user()->id,
            'lesson_id' => $lesson->id,
        ]);

        $lesson->load('course');
        $lesson->loadCount('quizzes', 'vocabularies');

        return ApiResponse::success(new LessonResource($lesson));
    }

    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        Log::info('admin.lesson.deleted', [
            'user_id' => $request->user()->id,
            'lesson_id' => $lesson->id,
            'title' => $lesson->title,
        ]);

        $lesson->delete();

        return response()->json(null, 204);
    }
}
