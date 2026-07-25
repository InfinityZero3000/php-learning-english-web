<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Level;
use App\Models\Topic;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::query()->with('level', 'lessons');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->input('level_id'));
        }

        if ($request->filled('topic_id')) {
            $topicId = $request->input('topic_id');
            $query->whereHas('topics', fn($q) => $q->where('topic_id', $topicId));
        }

        $courses = $query->orderBy('created_at', 'desc')->paginate(12);
        $levels = Level::all();
        $topics = Topic::all();

        return view('courses.index', compact('courses', 'levels', 'topics'));
    }

    public function show(Request $request, Course $course)
    {
        $course->load(['level', 'lessons' => function ($q) {
            $q->orderBy('sort_order')->orderBy('id');
        }, 'lessons.quizzes', 'lessons.vocabularies']);

        // Progress tracking
        $completedLessons = 0;
        $totalQuizzes = 0;
        $completedQuizzes = 0;

        if (auth()->check()) {
            $userId = auth()->id();
            $totalLessons = $course->lessons->count();
            $completedLessons = \App\Models\Progress::where('user_id', $userId)
                ->whereIn('lesson_id', $course->lessons->pluck('id'))
                ->where('completed', true)
                ->count();

            $quizIds = $course->lessons->pluck('quizzes')->flatten()->pluck('id');
            $totalQuizzes = $quizIds->count();
            $completedQuizzes = \App\Models\Attempt::where('user_id', $userId)
                ->whereIn('quiz_id', $quizIds)
                ->where('passed', true)
                ->count();
        }

        return view('courses.show', compact('course', 'completedLessons', 'totalQuizzes', 'completedQuizzes'));
    }
}