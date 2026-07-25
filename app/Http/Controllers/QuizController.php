<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function index()
    {
        $quizzes = Quiz::with(['lesson.course', 'questions'])
            ->orderBy('created_at', 'desc')
            ->paginate(12);
        return view('quizzes.list', compact('quizzes'));
    }

    public function show(Request $request, Quiz $quiz)
    {
        $quiz->load(['lesson.course', 'questions' => function ($q) {
            $q->orderBy('sort_order')->orderBy('id');
        }, 'questions.answers']);

        return view('quizzes.show', compact('quiz'));
    }

    public function submit(Request $request, Quiz $quiz)
    {
        $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'exists:answers,id'],
        ]);

        $quiz->load('questions.answers');
        $totalQuestions = $quiz->questions->count();
        $correctCount = 0;

        $userAnswers = $request->input('answers'); // [question_id => answer_id]

        foreach ($quiz->questions as $question) {
            $userAnswerId = $userAnswers[$question->id] ?? null;
            if ($userAnswerId) {
                $answer = $question->answers->where('id', $userAnswerId)->first();
                if ($answer && $answer->is_correct) {
                    $correctCount++;
                }
            }
        }

        $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100) : 0;
        $passed = $score >= $quiz->passing_score;

        $attempt = Attempt::create([
            'user_id' => auth()->id(),
            'quiz_id' => $quiz->id,
            'score' => $score,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'passed' => $passed,
            'answers_data' => $userAnswers,
            'completed_at' => now(),
        ]);

        // Save individual answers
        foreach ($userAnswers as $questionId => $answerId) {
            DB::table('attempt_answers')->insert([
                'attempt_id' => $attempt->id,
                'question_id' => $questionId,
                'answer_id' => $answerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('quizzes.result', $attempt);
    }

    public function result(Request $request, Attempt $attempt)
    {
        $attempt->load('quiz.questions.answers', 'quiz.lesson.course');

        $quiz = $attempt->quiz;
        $quiz->load(['questions' => function ($q) {
            $q->orderBy('sort_order');
        }, 'questions.answers']);

        return view('quizzes.result', compact('attempt', 'quiz'));
    }

    public function play(Request $request, Quiz $quiz)
    {
        $quiz->load(['lesson.course', 'questions' => function ($q) {
            $q->orderBy('sort_order')->orderBy('id');
        }, 'questions.answers']);

        return view('quizzes.show', compact('quiz'));
    }
}