<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function courses(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = Course::query()
            ->with(['level', 'category', 'topics'])
            ->withCount('lessons')
            ->where('status', 'published');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->integer('level_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('topic_id')) {
            $query->whereHas('topics', fn ($q) => $q->where('topics.id', $request->integer('topic_id')));
        }

        $sortBy = $request->string('sort_by')->toString();
        match ($sortBy) {
            'title' => $query->orderBy('title'),
            'created_at' => $query->orderBy('created_at', 'desc'),
            default => $query->orderBy('total_xp', 'desc'),
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

    public function course(Course $course): JsonResponse
    {
        abort_unless($course->status === 'published', 404);

        $course->load(['level', 'category', 'topics']);
        $course->loadCount('lessons', 'units');

        return ApiResponse::success(new CourseResource($course));
    }

    public function lessons(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = Lesson::query()
            ->with('course')
            ->withCount('quizzes', 'vocabularies')
            ->where('status', 'published')
            ->whereHas('course', fn ($course) => $course->where('status', 'published'));

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->integer('course_id'));
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

    public function lesson(Lesson $lesson): JsonResponse
    {
        abort_unless(
            $lesson->status === 'published'
            && $lesson->course()->where('status', 'published')->exists(),
            404,
        );

        $lesson->load(['course', 'quizzes', 'vocabularies']);
        $lesson->loadCount('quizzes', 'vocabularies');

        return ApiResponse::success(new LessonResource($lesson));
    }

    public function courseLessons(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->status === 'published', 404);

        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $query = $course->lessons()
            ->with('course')
            ->withCount('quizzes', 'vocabularies')
            ->where('status', 'published')
            ->orderBy('sort_order');

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
}
