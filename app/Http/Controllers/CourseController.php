<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Level;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['level', 'topics']);

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        $courses = $query->paginate(12)->withQueryString();
        $levels = Level::orderBy('sort_order')->get();

        return view('courses.index', compact('courses', 'levels'));
    }

    public function create()
    {
        $levels = Level::orderBy('sort_order')->get();
        $topics = Topic::orderBy('name')->get();

        return view('courses.create', compact('levels', 'topics'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'level_id' => 'nullable|exists:levels,id',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,published',
            'topics' => 'nullable|array',
            'topics.*' => 'exists:topics,id',
        ]);

        $validated['slug'] = $this->generateUniqueSlug($validated['title']);

        $course = Course::create($validated);

        if (! empty($validated['topics'])) {
            $course->topics()->sync($validated['topics']);
        }

        return redirect()->route('admin.courses.index')->with('success', 'Tạo khóa học thành công!');
    }

    public function show(Course $course)
    {
        $course->load(['level', 'topics', 'lessons' => function ($q) {
            $q->orderBy('sort_order');
        }]);

        return view('courses.show', compact('course'));
    }

    public function edit(Course $course)
    {
        $levels = Level::orderBy('sort_order')->get();
        $topics = Topic::orderBy('name')->get();

        return view('courses.edit', compact('course', 'levels', 'topics'));
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'level_id' => 'nullable|exists:levels,id',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,published',
            'topics' => 'nullable|array',
            'topics.*' => 'exists:topics,id',
        ]);

        if ($course->title !== $validated['title']) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $course->id);
        }

        $course->update($validated);

        if (isset($validated['topics'])) {
            $course->topics()->sync($validated['topics']);
        } else {
            $course->topics()->detach();
        }

        return redirect()->route('admin.courses.show', $course)->with('success', 'Cập nhật khóa học thành công!');
    }

    public function destroy(Course $course)
    {
        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'Khóa học đã được xóa!');
    }

    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (true) {
            $exists = Course::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists();

            if (! $exists) {
                break;
            }

            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
