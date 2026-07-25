<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LessonAdminController extends Controller
{
    public function index(): View
    {
        $lessons = Lesson::with('course')->orderBy('course_id')->orderBy('sort_order')->paginate(15);
        return view('admin.lessons.index', compact('lessons'));
    }

    public function create(): View
    {
        $courses = Course::orderBy('title')->get();
        return view('admin.lessons.create', compact('courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:lessons,slug',
            'content' => 'nullable|string',
            'video_url' => 'nullable|string|max:500',
            'sort_order' => 'required|integer|min:0',
            'status' => 'required|in:draft,published',
        ]);

        Lesson::create($data);

        return redirect()->route('admin.lessons.index')->with('success', 'Bài học đã được tạo.');
    }

    public function edit(Lesson $lesson): View
    {
        $courses = Course::orderBy('title')->get();
        return view('admin.lessons.edit', compact('lesson', 'courses'));
    }

    public function update(Request $request, Lesson $lesson): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:lessons,slug,' . $lesson->id,
            'content' => 'nullable|string',
            'video_url' => 'nullable|string|max:500',
            'sort_order' => 'required|integer|min:0',
            'status' => 'required|in:draft,published',
        ]);

        $lesson->update($data);

        return redirect()->route('admin.lessons.index')->with('success', 'Bài học đã được cập nhật.');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $lesson->delete();
        return redirect()->route('admin.lessons.index')->with('success', 'Bài học đã được xóa.');
    }
}