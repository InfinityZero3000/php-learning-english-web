<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuizAdminController extends Controller
{
    // Quiz CRUD
    public function index(): View
    {
        $quizzes = Quiz::with('lesson.course')->withCount('questions')->orderBy('id', 'desc')->paginate(15);
        return view('admin.quizzes.index', compact('quizzes'));
    }

    public function create(): View
    {
        $lessons = Lesson::with('course')->orderBy('course_id')->orderBy('sort_order')->get();
        return view('admin.quizzes.create', compact('lessons'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'title' => 'required|string|max:255',
            'passing_score' => 'required|integer|min:0|max:100',
            'status' => 'required|in:draft,published',
        ]);

        Quiz::create($data);

        return redirect()->route('admin.quizzes.index')->with('success', 'Quiz đã được tạo.');
    }

    public function edit(Quiz $quiz): View
    {
        $lessons = Lesson::with('course')->orderBy('course_id')->orderBy('sort_order')->get();
        return view('admin.quizzes.edit', compact('quiz', 'lessons'));
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $data = $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'title' => 'required|string|max:255',
            'passing_score' => 'required|integer|min:0|max:100',
            'status' => 'required|in:draft,published',
        ]);

        $quiz->update($data);

        return redirect()->route('admin.quizzes.index')->with('success', 'Quiz đã được cập nhật.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $quiz->delete();
        return redirect()->route('admin.quizzes.index')->with('success', 'Quiz đã được xóa.');
    }

    // Question management
    public function questions(Quiz $quiz): View
    {
        $quiz->load('questions.answers');
        return view('admin.quizzes.questions', compact('quiz'));
    }

    public function storeQuestion(Request $request, Quiz $quiz): RedirectResponse
    {
        $data = $request->validate([
            'content' => 'required|string',
            'type' => 'required|in:multiple_choice,true_false',
            'sort_order' => 'nullable|integer|min:0',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string',
            'correct_index' => 'required|integer|min:0',
            'explanation' => 'nullable|string',
        ]);

        $question = $quiz->questions()->create([
            'content' => $data['content'],
            'type' => $data['type'],
            'sort_order' => $data['sort_order'] ?? $quiz->questions()->count() + 1,
        ]);

        foreach ($data['options'] as $index => $option) {
            $question->answers()->create([
                'content' => trim($option),
                'is_correct' => $index === (int) $data['correct_index'],
                'explanation' => $index === (int) $data['correct_index'] ? ($data['explanation'] ?? null) : null,
            ]);
        }

        return redirect()->route('admin.quizzes.questions', $quiz)->with('success', 'Câu hỏi đã được thêm.');
    }

    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate([
            'content' => 'required|string',
            'type' => 'required|in:multiple_choice,true_false',
            'sort_order' => 'nullable|integer|min:0',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string',
            'correct_index' => 'required|integer|min:0',
            'explanation' => 'nullable|string',
        ]);

        $question->update([
            'content' => $data['content'],
            'type' => $data['type'],
            'sort_order' => $data['sort_order'] ?? $question->sort_order,
        ]);

        // Delete old answers and recreate
        $question->answers()->delete();
        foreach ($data['options'] as $index => $option) {
            $question->answers()->create([
                'content' => trim($option),
                'is_correct' => $index === (int) $data['correct_index'],
                'explanation' => $index === (int) $data['correct_index'] ? ($data['explanation'] ?? null) : null,
            ]);
        }

        return redirect()->route('admin.quizzes.questions', $question->quiz)->with('success', 'Câu hỏi đã được cập nhật.');
    }

    public function destroyQuestion(Question $question): RedirectResponse
    {
        $quiz = $question->quiz;
        $question->delete();
        return redirect()->route('admin.quizzes.questions', $quiz)->with('success', 'Câu hỏi đã được xóa.');
    }

    // Import
    public function import(Request $request, Quiz $quiz)
    {
        abort_unless($request->user()?->role?->slug === 'admin', 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,text/plain,doc,docx'],
        ]);

        $path = $request->file('file')->store('imports');
        $fullPath = Storage::path($path);
        $content = $this->readFile($fullPath);

        $rows = $this->parseCsv($content);
        if (empty($rows)) {
            return back()->withErrors(['file' => 'Không thể đọc nội dung file. Vui lòng kiểm tra định dạng CSV hoặc file văn bản.']);
        }

        $imported = 0;
        foreach ($rows as $row) {
            if (empty($row['question'] ?? '')) {
                continue;
            }

            $question = $quiz->questions()->create([
                'content' => trim($row['question']),
                'type' => 'multiple_choice',
                'sort_order' => $quiz->questions()->count() + 1,
            ]);

            $options = [
                $row['option_a'] ?? null,
                $row['option_b'] ?? null,
                $row['option_c'] ?? null,
                $row['option_d'] ?? null,
            ];

            $correctAnswer = trim((string) ($row['correct_answer'] ?? ''));
            foreach ($options as $index => $option) {
                if (empty($option)) {
                    continue;
                }

                $answer = new Answer([
                    'content' => trim($option),
                    'is_correct' => Str::lower(trim($option)) === Str::lower($correctAnswer) || ($index + 1) === (int) $correctAnswer,
                    'explanation' => trim((string) ($row['explanation'] ?? '')),
                ]);

                $question->answers()->save($answer);
            }

            $imported++;
        }

        return back()->with('success', 'Đã import thành công ' . $imported . ' câu hỏi vào quiz.');
    }

    protected function readFile(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            return file_get_contents($path) ?: '';
        }

        if ($extension === 'doc') {
            return strip_tags(file_get_contents($path) ?: '');
        }

        if ($extension === 'docx') {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    return strip_tags(preg_replace('/<w:br\s*\/?>/i', "\n", $xml));
                }
            }
        }

        return '';
    }

    protected function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $parts = str_getcsv($line);
            if (count($parts) < 2) {
                continue;
            }

            $rows[] = [
                'question' => $parts[0] ?? '',
                'option_a' => $parts[1] ?? '',
                'option_b' => $parts[2] ?? '',
                'option_c' => $parts[3] ?? '',
                'option_d' => $parts[4] ?? '',
                'correct_answer' => $parts[5] ?? '',
                'explanation' => $parts[6] ?? '',
            ];
        }

        return $rows;
    }
}
