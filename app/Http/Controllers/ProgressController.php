<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Progress;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function index()
    {
        if (app()->isProduction()) {
            return redirect()->away(config('app.frontend_url').'/progress');
        }

        $user = auth()->user();

        // Bài học đã hoàn thành
        $completedLessons = Progress::where('user_id', $user->id)
            ->where('completed_at', '!=', null)
            ->with('lesson.course')
            ->orderByDesc('completed_at')
            ->get();

        // Tất cả bài học trong hệ thống
        $totalLessons = Lesson::count();

        // Các lần làm quiz
        $attempts = Attempt::where('user_id', $user->id)
            ->with('quiz.lesson')
            ->orderByDesc('completed_at')
            ->get();

        $totalAttempts = $attempts->count();
        $passedAttempts = $attempts->where('score', '>=', function ($q) {
            // dùng filter thay vì closure trên collection
        })->count();

        // Tính riêng bằng filter
        $passedAttempts = $attempts->filter(function ($attempt) {
            return $attempt->score >= ($attempt->quiz->passing_score ?? 60);
        })->count();

        $avgScore = $totalAttempts > 0 ? round($attempts->avg('score'), 1) : 0;

        // Tiến độ từng khóa học
        $courses = Course::with(['lessons'])->get()->map(function ($course) use ($completedLessons) {
            $lessonIds = $course->lessons->pluck('id');
            $doneCount = $completedLessons->whereIn('lesson_id', $lessonIds)->count();
            $totalCount = $lessonIds->count();
            $course->done = $doneCount;
            $course->total = $totalCount;
            $course->pct = $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0;

            return $course;
        })->filter(fn ($c) => $c->total > 0);

        return view('progress.index', compact(
            'completedLessons', 'totalLessons', 'attempts',
            'totalAttempts', 'passedAttempts', 'avgScore', 'courses'
        ));
    }

    // Đánh dấu bài học đã hoàn thành (gọi từ trang show bài học)
    public function markComplete(Request $request, Lesson $lesson)
    {
        Progress::updateOrCreate(
            ['user_id' => auth()->id(), 'lesson_id' => $lesson->id],
            ['completed_at' => now()]
        );

        return back()->with('success', 'Đã đánh dấu hoàn thành bài học "'.$lesson->title.'"!');
    }
}
