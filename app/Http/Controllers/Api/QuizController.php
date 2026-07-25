<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizSession;
use App\Models\QuizSessionAnswer;
use App\Models\UserWordProgress;
use App\Models\Vocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function stats(): JsonResponse
    {
        $user = auth()->user();
        $totalSessions = QuizSession::where('user_id', $user->id)->count();
        $completedSessions = QuizSession::where('user_id', $user->id)->where('completed', true)->count();
        $avgScore = QuizSession::where('user_id', $user->id)->avg('score') ?? 0;
        $totalCorrect = QuizSession::where('user_id', $user->id)->sum('correct_answers');
        $totalQuestions = QuizSession::where('user_id', $user->id)->sum('total_questions');

        return response()->json([
            'totalSessions' => $totalSessions,
            'completedSessions' => $completedSessions,
            'averageScore' => round((float) $avgScore, 1),
            'totalCorrect' => $totalCorrect,
            'totalQuestions' => $totalQuestions,
        ]);
    }

    public function recentSessions(): JsonResponse
    {
        $sessions = QuizSession::where('user_id', auth()->id())
            ->where('completed', true)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json($sessions);
    }

    public function start(Request $request): JsonResponse
    {
        $query = Vocabulary::query()->with('topic');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }
        if ($request->filled('topicId')) {
            $query->where('topic_id', $request->topicId);
        }
        if ($request->filled('cefrLevel')) {
            $query->where('cefr_level', $request->cefrLevel);
        }

        $count = min((int) ($request->count ?? 10), 50);
        $vocabs = $query->inRandomOrder()->limit($count)->get();

        // Generate distractors for multiple-choice quizzes
        $allTranslations = Vocabulary::pluck('translation', 'id')->toArray();
        $allIds = array_keys($allTranslations);

        $words = $vocabs->map(function ($vocab) use ($allTranslations, $allIds) {
            // Pick 3 random distractors (translations from other words)
            $otherIds = array_diff($allIds, [$vocab->id]);
            shuffle($otherIds);
            $distractorIds = array_slice($otherIds, 0, min(3, count($otherIds)));
            $distractors = array_values(array_intersect_key($allTranslations, array_flip($distractorIds)));

            return [
                'id' => $vocab->id,
                'word' => $vocab->word,
                'translation' => $vocab->translation,
                'difficulty' => $vocab->difficulty,
                'pronunciation' => $vocab->pronunciation,
                'partOfSpeech' => $vocab->part_of_speech,
                'cefrLevel' => $vocab->cefr_level,
                'topic' => $vocab->topic ? ['id' => $vocab->topic->id, 'name' => $vocab->topic->name] : null,
            ];
        });

        $session = QuizSession::create([
            'user_id' => auth()->id(),
            'total_questions' => $count,
            'correct_answers' => 0,
            'type' => $request->type ?? 'vocabulary',
            'category' => $request->category,
            'difficulty' => $request->difficulty,
            'topic_id' => $request->topicId,
            'cefr_level' => $request->cefrLevel,
        ]);

        return response()->json([
            'sessionId' => $session->id,
            'words' => $words,
            'totalQuestions' => $count,
            'distractors' => [], // frontend can generate options client-side
        ]);
    }

    public function answer(Request $request, QuizSession $session): JsonResponse
    {
        $request->validate([
            'wordId' => 'required|exists:vocabularies,id',
            'correct' => 'required|boolean',
        ]);

        $wordId = (int) $request->query('wordId', $request->wordId);
        $correct = filter_var($request->query('correct', $request->correct), FILTER_VALIDATE_BOOLEAN);

        QuizSessionAnswer::create([
            'quiz_session_id' => $session->id,
            'vocabulary_id' => $wordId,
            'correct' => $correct,
            'response_ms' => (int) ($request->query('responseMs', 0)),
        ]);

        if ($correct) {
            $session->increment('correct_answers', 1);
        }

        // Update word progress
        $progress = UserWordProgress::firstOrNew([
            'user_id' => auth()->id(),
            'vocabulary_id' => $wordId,
        ]);

        if ($correct) {
            $progress->correct_count = ($progress->correct_count ?? 0) + 1;
            $progress->stability = min(($progress->stability ?? 0) + 0.5, 10);
            $progress->repetitions = ($progress->repetitions ?? 0) + 1;
        } else {
            $progress->incorrect_count = ($progress->incorrect_count ?? 0) + 1;
            $progress->stability = max(($progress->stability ?? 0) - 1, 0);
            $progress->lapses = ($progress->lapses ?? 0) + 1;
        }

        $progress->last_reviewed_at = now();
        $progress->mastery = match (true) {
            $progress->stability >= 5 => 'mastered',
            $progress->stability >= 2 => 'review',
            $progress->stability > 0 => 'learning',
            default => 'new',
        };
        $progress->due = now()->addDays(max(1, (int) ($progress->stability * 2)));
        $progress->save();

        return response()->json($session->fresh());
    }

    public function complete(Request $request, QuizSession $session): JsonResponse
    {
        $total = $session->total_questions;
        $correct = $session->correct_answers;
        $score = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

        $session->update([
            'completed' => true,
            'score' => $score,
            'completed_at' => now(),
        ]);

        return response()->json($session->fresh());
    }
}