<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function show(Request $request, Lesson $lesson)
    {
        $lesson->load(['course', 'vocabularies', 'quizzes' => function ($q) {
            $q->orderBy('id');
        }, 'quizzes.questions']);

        $progress = null;
        if (auth()->check()) {
            $progress = \App\Models\Progress::where('user_id', auth()->id())
                ->where('lesson_id', $lesson->id)
                ->first();
        }

        $prevLesson = Lesson::where('course_id', $lesson->course_id)
            ->where('sort_order', '<', $lesson->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        $nextLesson = Lesson::where('course_id', $lesson->course_id)
            ->where('sort_order', '>', $lesson->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        return view('lessons.show', compact('lesson', 'progress', 'prevLesson', 'nextLesson'));
    }

    public function complete(Request $request, Lesson $lesson)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        \App\Models\Progress::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'lesson_id' => $lesson->id,
            ],
            [
                'completed' => true,
                'completed_at' => now(),
            ]
        );

        return back()->with('success', 'Bài học đã được đánh dấu hoàn thành!');
    }
}