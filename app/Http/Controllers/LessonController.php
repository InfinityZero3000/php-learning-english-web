<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LessonController extends Controller
{
    /**
     * Danh sách tất cả bài học (có thể lọc theo course).
     */
    public function index(Request $request)
    {
        $query = Lesson::with('course')
            ->orderBy('course_id')
            ->orderBy('sort_order');

        // Lọc theo course
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Tìm kiếm theo tiêu đề
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Lọc theo trạng thái
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $lessons = $query->paginate(12)->withQueryString();
        $courses  = Course::orderBy('title')->get();

        return view('lessons.index', compact('lessons', 'courses'));
    }

    /**
     * Form tạo bài học mới.
     */
    public function create()
    {
        $courses = Course::orderBy('title')->get();
        return view('lessons.create', compact('courses'));
    }

    /**
     * Lưu bài học mới vào database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id'  => 'required|exists:courses,id',
            'title'      => 'required|string|max:255',
            'content'    => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status'     => 'required|in:draft,published',
        ], [
            'course_id.required' => 'Vui lòng chọn khóa học.',
            'course_id.exists'   => 'Khóa học không tồn tại.',
            'title.required'     => 'Tiêu đề bài học không được để trống.',
            'title.max'          => 'Tiêu đề không vượt quá 255 ký tự.',
        ]);

        $validated['slug'] = $this->generateUniqueSlug($validated['title'], $validated['course_id']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        Lesson::create($validated);

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Bài học "' . $validated['title'] . '" đã được tạo thành công!');
    }

    /**
     * Xem chi tiết một bài học kèm danh sách quiz.
     */
    public function show(Lesson $lesson)
    {
        $lesson->load(['course', 'quizzes.questions', 'vocabularies']);
        return view('lessons.show', compact('lesson'));
    }

    /**
     * Form chỉnh sửa bài học.
     */
    public function edit(Lesson $lesson)
    {
        $courses = Course::orderBy('title')->get();
        return view('lessons.edit', compact('lesson', 'courses'));
    }

    /**
     * Cập nhật thông tin bài học.
     */
    public function update(Request $request, Lesson $lesson)
    {
        $validated = $request->validate([
            'course_id'  => 'required|exists:courses,id',
            'title'      => 'required|string|max:255',
            'content'    => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status'     => 'required|in:draft,published',
        ], [
            'course_id.required' => 'Vui lòng chọn khóa học.',
            'title.required'     => 'Tiêu đề bài học không được để trống.',
        ]);

        // Tạo slug mới nếu tiêu đề thay đổi
        if ($lesson->title !== $validated['title'] || $lesson->course_id !== (int) $validated['course_id']) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $validated['course_id'], $lesson->id);
        }

        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $lesson->update($validated);

        return redirect()->route('admin.lessons.show', $lesson)
            ->with('success', 'Bài học đã được cập nhật!');
    }

    /**
     * Xóa bài học (cascade → quiz, câu hỏi, đáp án).
     */
    public function destroy(Lesson $lesson)
    {
        $title = $lesson->title;
        $lesson->delete();

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Đã xóa bài học "' . $title . '".');
    }

    // =====================================================
    // PRIVATE HELPERS
    // =====================================================

    private function generateUniqueSlug(string $title, int $courseId, ?int $excludeId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i    = 2;

        while (true) {
            $exists = Lesson::where('course_id', $courseId)
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists();

            if (! $exists) {
                break;
            }

            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}

