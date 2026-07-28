<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgressResource;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Progress;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function myProgress(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $userId = $request->user()->id;

        $query = Progress::where('user_id', $userId)
            ->with('lesson.course')
            ->orderByDesc('completed_at');

        if ($request->filled('course_id')) {
            $query->whereHas('lesson', fn ($q) => $q->where('course_id', $request->integer('course_id')));
        }

        $page = $query->paginate($perPage);

        return ApiResponse::success(
            ProgressResource::collection($page->items()),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function courseProgress(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->status === 'published', 404);

        $userId = $request->user()->id;
        $lessons = $course->lessons()->where('status', 'published')->orderBy('sort_order')->get();
        $completedLessonIds = Progress::where('user_id', $userId)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->pluck('lesson_id')
            ->toArray();

        $totalLessons = $lessons->count();
        $completedCount = count($completedLessonIds);
        $progressPercent = $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100) : 0;

        return ApiResponse::success([
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            'progress_percent' => $progressPercent,
            'completed_lessons' => $completedCount,
            'total_lessons' => $totalLessons,
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $completedLessons = Progress::where('user_id', $userId)->count();

        $quizAttempts = Attempt::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->count();

        $averageScore = Attempt::where('user_id', $userId)
            ->whereNotNull('score')
            ->avg('score') ?? 0;

        $recentActivity = Progress::where('user_id', $userId)
            ->with('lesson')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get();

        $recentAttempts = Attempt::where('user_id', $userId)
            ->with('quiz.lesson')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'overview' => [
                'completed_lessons' => $completedLessons,
                'quiz_attempts' => $quizAttempts,
                'average_score' => round($averageScore, 2),
            ],
            'recent_activity' => ProgressResource::collection($recentActivity),
            'recent_attempts' => $recentAttempts->map(fn ($attempt) => [
                'id' => $attempt->id,
                'quiz_id' => $attempt->quiz_id,
                'score' => $attempt->score,
                'completed_at' => $attempt->completed_at,
            ]),
        ]);
    }

    public function markCompleted(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless(
            $lesson->status === 'published'
            && $lesson->course()->where('status', 'published')->exists(),
            404,
        );

        $userId = $request->user()->id;

        Progress::firstOrCreate([
            'user_id' => $userId,
            'lesson_id' => $lesson->id,
        ], [
            'completed_at' => now(),
        ]);

        return ApiResponse::success(['status' => 'completed']);
    }
}
