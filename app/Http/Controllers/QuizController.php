<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /**
     * Danh sách tất cả quiz.
     */
    public function index(Request $request)
    {
        $query = Quiz::with('lesson.course')
            ->withCount('questions');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        $quizzes = $query->latest()->paginate(10)->withQueryString();
        $lessons  = Lesson::with('course')->orderBy('title')->get();

        return view('quizzes.index', compact('quizzes', 'lessons'));
    }

    /**
     * Form tạo quiz mới.
     */
    public function create(Request $request)
    {
        $lessons     = Lesson::with('course')->orderBy('title')->get();
        $lessonId    = $request->lesson_id;  // prefill nếu đến từ lesson
        return view('quizzes.create', compact('lessons', 'lessonId'));
    }

    /**
     * Lưu quiz + câu hỏi + đáp án cùng lúc.
     */
    public function store(Request $request)
    {
        $request->validate([
            'lesson_id'     => 'required|exists:lessons,id',
            'title'         => 'required|string|max:255',
            'passing_score' => 'required|integer|min:0|max:100',
            'status'        => 'required|in:draft,published',
            'questions'     => 'required|array|min:1',
            'questions.*.content'   => 'required|string',
            'questions.*.answers'   => 'required|array|min:2',
            'questions.*.answers.*.content'    => 'required|string',
            'questions.*.answers.*.is_correct' => 'nullable|boolean',
        ], [
            'lesson_id.required'           => 'Vui lòng chọn bài học.',
            'title.required'               => 'Tiêu đề quiz không được để trống.',
            'questions.required'           => 'Quiz phải có ít nhất 1 câu hỏi.',
            'questions.*.content.required' => 'Nội dung câu hỏi không được để trống.',
            'questions.*.answers.*.content.required' => 'Nội dung đáp án không được để trống.',
        ]);

        // Kiểm tra mỗi câu hỏi có ít nhất 1 đáp án đúng
        foreach ($request->questions as $i => $q) {
            $hasCorrect = collect($q['answers'] ?? [])->contains(fn ($a) => ! empty($a['is_correct']));
            if (! $hasCorrect) {
                return back()
                    ->withInput()
                    ->withErrors(["questions.{$i}.answers" => "Câu hỏi " . ($i + 1) . " phải có ít nhất 1 đáp án đúng."]);
            }
        }

        // Tạo quiz
        $quiz = Quiz::create([
            'lesson_id'     => $request->lesson_id,
            'title'         => $request->title,
            'passing_score' => $request->passing_score,
            'status'        => $request->status,
        ]);

        // Tạo câu hỏi + đáp án
        foreach ($request->questions as $order => $qData) {
            $question = Question::create([
                'quiz_id'     => $quiz->id,
                'content'     => $qData['content'],
                'explanation' => $qData['explanation'] ?? null,
                'sort_order'  => $order,
            ]);

            foreach ($qData['answers'] as $aData) {
                Answer::create([
                    'question_id' => $question->id,
                    'content'     => $aData['content'],
                    'is_correct'  => ! empty($aData['is_correct']),
                ]);
            }
        }

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Quiz "' . $quiz->title . '" đã được tạo thành công!');
    }

    /**
     * Chi tiết quiz kèm tất cả câu hỏi + đáp án.
     */
    public function show(Quiz $quiz)
    {
        $quiz->load(['lesson.course', 'questions.answers', 'attempts']);
        return view('quizzes.show', compact('quiz'));
    }

    /**
     * Form chỉnh sửa quiz.
     */
    public function edit(Quiz $quiz)
    {
        $quiz->load(['questions.answers']);
        $lessons = Lesson::with('course')->orderBy('title')->get();
        return view('quizzes.edit', compact('quiz', 'lessons'));
    }

    /**
     * Cập nhật quiz + đồng bộ câu hỏi + đáp án.
     */
    public function update(Request $request, Quiz $quiz)
    {
        $request->validate([
            'lesson_id'     => 'required|exists:lessons,id',
            'title'         => 'required|string|max:255',
            'passing_score' => 'required|integer|min:0|max:100',
            'status'        => 'required|in:draft,published',
            'questions'     => 'required|array|min:1',
            'questions.*.content'   => 'required|string',
            'questions.*.answers'   => 'required|array|min:2',
            'questions.*.answers.*.content'    => 'required|string',
            'questions.*.answers.*.is_correct' => 'nullable|boolean',
        ]);

        // Kiểm tra đáp án đúng
        foreach ($request->questions as $i => $q) {
            $hasCorrect = collect($q['answers'] ?? [])->contains(fn ($a) => ! empty($a['is_correct']));
            if (! $hasCorrect) {
                return back()
                    ->withInput()
                    ->withErrors(["questions.{$i}.answers" => "Câu hỏi " . ($i + 1) . " phải có ít nhất 1 đáp án đúng."]);
            }
        }

        // Cập nhật quiz
        $quiz->update([
            'lesson_id'     => $request->lesson_id,
            'title'         => $request->title,
            'passing_score' => $request->passing_score,
            'status'        => $request->status,
        ]);

        // Xóa câu hỏi + đáp án cũ → tạo lại
        $quiz->questions()->each(function ($question) {
            $question->answers()->delete();
        });
        $quiz->questions()->delete();

        foreach ($request->questions as $order => $qData) {
            $question = Question::create([
                'quiz_id'     => $quiz->id,
                'content'     => $qData['content'],
                'explanation' => $qData['explanation'] ?? null,
                'sort_order'  => $order,
            ]);

            foreach ($qData['answers'] as $aData) {
                Answer::create([
                    'question_id' => $question->id,
                    'content'     => $aData['content'],
                    'is_correct'  => ! empty($aData['is_correct']),
                ]);
            }
        }

        return redirect()->route('admin.quizzes.show', $quiz)
            ->with('success', 'Quiz đã được cập nhật!');
    }

    /**
     * Xóa quiz (cascade → câu hỏi, đáp án, attempts).
     */
    public function destroy(Quiz $quiz)
    {
        $title = $quiz->title;
        $quiz->delete();

        return redirect()->route('admin.quizzes.index')
            ->with('success', 'Đã xóa quiz "' . $title . '".');
    }
}

