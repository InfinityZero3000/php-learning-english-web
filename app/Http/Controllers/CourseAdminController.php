<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Level;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseAdminController extends Controller
{
    public function index(Request $request): View
    {
        $courses = Course::query()
            ->with(['level', 'topics'])
            ->withCount('lessons')
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->level_id, fn ($q, $id) => $q->where('level_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->paginate($request->per_page ?? 15)
            ->withQueryString();

        $levels = Level::orderBy('sort_order')->get();

        return view('admin.courses.index', compact('courses', 'levels'));
    }

    public function create(): View
    {
        $levels = Level::orderBy('sort_order')->get();
        $topics = Topic::orderBy('name')->get();

        return view('admin.courses.create', compact('levels', 'topics'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'level_id' => 'nullable|exists:levels,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:courses,slug',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,published,archived',
            'topic_ids' => 'sometimes|array',
            'topic_ids.*' => 'exists:topics,id',
        ]);

        $course = Course::create($data);

        if ($request->has('topic_ids')) {
            $course->topics()->sync($request->topic_ids);
        }

        return redirect()->route('admin.courses.index')
            ->with('success', 'Khóa học đã được tạo.');
    }

    public function edit(Course $course): View
    {
        $levels = Level::orderBy('sort_order')->get();
        $topics = Topic::orderBy('name')->get();
        $course->load('topics');

        return view('admin.courses.edit', compact('course', 'levels', 'topics'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'level_id' => 'nullable|exists:levels,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:courses,slug,' . $course->id,
            'description' => 'nullable|string',
            'status' => 'required|in:draft,published,archived',
            'topic_ids' => 'sometimes|array',
            'topic_ids.*' => 'exists:topics,id',
        ]);

        $course->update($data);

        if ($request->has('topic_ids')) {
            $course->topics()->sync($request->topic_ids);
        }

        return redirect()->route('admin.courses.index')
            ->with('success', 'Khóa học đã được cập nhật.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();

        return redirect()->route('admin.courses.index')
            ->with('success', 'Khóa học đã được xóa.');
    }
}