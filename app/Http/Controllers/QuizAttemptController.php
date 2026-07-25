<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\Attempt;
use App\Models\Answer;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    public function show(Quiz $quiz)
    {
        // Load câu hỏi kèm đáp án
        $quiz->load(['questions.answers']);
        return view('quizzes.attempt', compact('quiz'));
    }

    public function submit(Request $request, Quiz $quiz)
    {
        $quiz->load('questions.answers');
        
        $answers = $request->input('answers', []);
        $totalQuestions = $quiz->questions->count();
        if ($totalQuestions === 0) {
            return back()->with('error', 'Bài kiểm tra chưa có câu hỏi nào.');
        }

        $correctCount = 0;

        foreach ($quiz->questions as $question) {
            $selectedAnswerId = $answers[$question->id] ?? null;
            if ($selectedAnswerId) {
                $isCorrect = $question->answers->where('id', $selectedAnswerId)->where('is_correct', true)->isNotEmpty();
                if ($isCorrect) {
                    $correctCount++;
                }
            }
        }

        $score = round(($correctCount / $totalQuestions) * 100);

        $attempt = Attempt::create([
            'user_id'      => $request->user()->id,
            'quiz_id'      => $quiz->id,
            'score'        => $score,
            'started_at'   => now(), // Cần mechanism theo dõi lúc bắt đầu thực tế nếu muốn chính xác
            'completed_at' => now(),
        ]);

        return redirect()->route('quizzes.result', ['quiz' => $quiz->id, 'attempt_id' => $attempt->id]);
    }

    public function result(Request $request, Quiz $quiz)
    {
        $attemptId = $request->query('attempt_id');
        $attempt = Attempt::where('id', $attemptId)->where('user_id', $request->user()->id)->firstOrFail();
        
        $quiz->load('questions.answers');

        return view('quizzes.result', compact('quiz', 'attempt'));
    }
}

