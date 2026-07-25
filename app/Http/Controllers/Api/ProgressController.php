<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserWordProgress;
use App\Models\UserStreak;
use App\Models\Vocabulary;
use App\Models\QuizSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function progressList(): JsonResponse
    {
        $progress = UserWordProgress::where('user_id', auth()->id())
            ->with('vocabulary.topic')
            ->orderByDesc('last_reviewed_at')
            ->limit(100)
            ->get();

        $data = $progress->map(function ($p) {
            return [
                'id' => $p->id,
                'word' => [
                    'id' => $p->vocabulary->id,
                    'word' => $p->vocabulary->word,
                    'translation' => $p->vocabulary->translation,
                    'difficulty' => $p->vocabulary->difficulty,
                    'cefrLevel' => $p->vocabulary->cefr_level,
                    'topic' => $p->vocabulary->topic ? ['id' => $p->vocabulary->topic->id, 'name' => $p->vocabulary->topic->name] : null,
                ],
                'mastery' => $p->mastery,
                'correctCount' => $p->correct_count,
                'incorrectCount' => $p->incorrect_count,
                'lastReviewed' => $p->last_reviewed_at?->toDateTimeString(),
                'nextReviewAt' => $p->due?->toDateTimeString(),
                'stability' => (float) $p->stability,
                'difficulty' => (float) $p->difficulty,
            ];
        });

        return response()->json($data);
    }

    public function stats(): JsonResponse
    {
        $user = auth()->user();
        $total = UserWordProgress::where('user_id', $user->id)->count();
        $new = UserWordProgress::where('user_id', $user->id)->where('mastery', 'new')->count();
        $learning = UserWordProgress::where('user_id', $user->id)->where('mastery', 'learning')->count();
        $review = UserWordProgress::where('user_id', $user->id)->where('mastery', 'review')->count();
        $mastered = UserWordProgress::where('user_id', $user->id)->where('mastery', 'mastered')->count();

        return response()->json([
            'total' => $total,
            'new' => $new,
            'learning' => $learning,
            'review' => $review,
            'mastered' => $mastered,
        ]);
    }

    public function reviewQueue(): JsonResponse
    {
        $items = UserWordProgress::where('user_id', auth()->id())
            ->where('due', '<=', now())
            ->with('vocabulary.topic')
            ->orderBy('due')
            ->limit(50)
            ->get();

        $data = $items->map(function ($p) {
            return [
                'id' => $p->id,
                'word' => [
                    'id' => $p->vocabulary->id,
                    'word' => $p->vocabulary->word,
                    'translation' => $p->vocabulary->translation,
                    'difficulty' => $p->vocabulary->difficulty,
                    'cefrLevel' => $p->vocabulary->cefr_level,
                    'topic' => $p->vocabulary->topic ? ['id' => $p->vocabulary->topic->id, 'name' => $p->vocabulary->topic->name] : null,
                ],
                'mastery' => $p->mastery,
                'correctCount' => $p->correct_count,
                'incorrectCount' => $p->incorrect_count,
                'lastReviewed' => $p->last_reviewed_at?->toDateTimeString(),
                'nextReviewAt' => $p->due?->toDateTimeString(),
                'stability' => (float) $p->stability,
                'difficulty' => (float) $p->difficulty,
            ];
        });

        return response()->json($data);
    }

    public function dueList(Request $request): JsonResponse
    {
        $limit = min((int) ($request->limit ?? 20), 100);
        $items = UserWordProgress::where('user_id', auth()->id())
            ->where('due', '<=', now())
            ->with('vocabulary.topic')
            ->orderBy('due')
            ->limit($limit)
            ->get();

        $data = $items->map(function ($p) {
            return [
                'id' => $p->id,
                'word' => [
                    'id' => $p->vocabulary->id,
                    'word' => $p->vocabulary->word,
                    'translation' => $p->vocabulary->translation,
                    'difficulty' => $p->vocabulary->difficulty,
                    'cefrLevel' => $p->vocabulary->cefr_level,
                    'topic' => $p->vocabulary->topic ? ['id' => $p->vocabulary->topic->id, 'name' => $p->vocabulary->topic->name] : null,
                ],
                'mastery' => $p->mastery,
                'correctCount' => $p->correct_count,
                'incorrectCount' => $p->incorrect_count,
                'lastReviewed' => $p->last_reviewed_at?->toDateTimeString(),
                'nextReviewAt' => $p->due?->toDateTimeString(),
                'stability' => (float) $p->stability,
                'difficulty' => (float) $p->difficulty,
            ];
        });

        return response()->json($data);
    }

    public function fsrsStats(): JsonResponse
    {
        $user = auth()->user();
        return response()->json([
            'new' => UserWordProgress::where('user_id', $user->id)->where('mastery', 'new')->count(),
            'learning' => UserWordProgress::where('user_id', $user->id)->where('mastery', 'learning')->count(),
            'review' => UserWordProgress::where('user_id', $user->id)->where('mastery', 'review')->count(),
            'mastered' => UserWordProgress::where('user_id', $user->id)->where('mastery', 'mastered')->count(),
            'due' => UserWordProgress::where('user_id', $user->id)->where('due', '<=', now())->count(),
        ]);
    }

    public function reviewWord(Request $request): JsonResponse
    {
        $wordId = (int) ($request->query('wordId') ?? $request->wordId);
        $rating = (float) ($request->query('rating') ?? $request->rating ?? 3);
        $responseMs = (int) ($request->query('responseMs') ?? $request->responseMs ?? 0);

        $progress = UserWordProgress::firstOrNew([
            'user_id' => auth()->id(),
            'vocabulary_id' => $wordId,
        ]);

        // FSRS-like review: rating 1-4 maps to Again/Hard/Good/Easy
        if ($rating >= 3) {
            $progress->correct_count = ($progress->correct_count ?? 0) + 1;
            $progress->stability = min(($progress->stability ?? 0) + ($rating >= 4 ? 1.0 : 0.5), 10);
            $progress->repetitions = ($progress->repetitions ?? 0) + 1;
        } else {
            $progress->incorrect_count = ($progress->incorrect_count ?? 0) + 1;
            $progress->stability = max(($progress->stability ?? 0) - ($rating <= 2 ? 1 : 0.5), 0);
            $progress->lapses = ($progress->lapses ?? 0) + 1;
        }

        $progress->last_reviewed_at = now();
        $progress->mastery = match (true) {
            $progress->stability >= 5 => 'mastered',
            $progress->stability >= 2 => 'review',
            $progress->stability > 0 => 'learning',
            default => 'new',
        };

        // Next review interval
        $interval = max(1, (int) ($progress->stability * 2));
        $progress->due = now()->addDays($interval);
        $progress->difficulty = $progress->difficulty ?? 0;
        $progress->save();

        $progress->load('vocabulary.topic');

        return response()->json([
            'id' => $progress->id,
            'word' => [
                'id' => $progress->vocabulary->id,
                'word' => $progress->vocabulary->word,
                'translation' => $progress->vocabulary->translation,
                'difficulty' => $progress->vocabulary->difficulty,
                'cefrLevel' => $progress->vocabulary->cefr_level,
                'topic' => $progress->vocabulary->topic ? ['id' => $progress->vocabulary->topic->id, 'name' => $progress->vocabulary->topic->name] : null,
            ],
            'mastery' => $progress->mastery,
            'correctCount' => $progress->correct_count,
            'incorrectCount' => $progress->incorrect_count,
            'lastReviewed' => $progress->last_reviewed_at?->toDateTimeString(),
            'nextReviewAt' => $progress->due?->toDateTimeString(),
            'stability' => (float) $progress->stability,
            'difficulty' => (float) $progress->difficulty,
        ]);
    }

    public function getStreak(): JsonResponse
    {
        $user = auth()->user();
        $streak = UserStreak::firstOrCreate(
            ['user_id' => $user->id],
            ['current_streak' => 0, 'longest_streak' => 0, 'total_check_ins' => 0]
        );

        return response()->json([
            'current' => $streak->current_streak,
            'longest' => $streak->longest_streak,
            'totalCheckIns' => $streak->total_check_ins,
        ]);
    }

    public function checkIn(): JsonResponse
    {
        $user = auth()->user();
        $today = now()->toDateString();

        $streak = UserStreak::firstOrCreate(
            ['user_id' => $user->id],
            ['current_streak' => 0, 'longest_streak' => 0, 'total_check_ins' => 0]
        );

        if ($streak->last_check_in_date !== $today) {
            $yesterday = now()->subDay()->toDateString();
            if ($streak->last_check_in_date === $yesterday) {
                $streak->current_streak += 1;
            } else {
                $streak->current_streak = 1;
            }

            $streak->last_check_in_date = $today;
            $streak->last_activity_date = $today;
            $streak->total_check_ins += 1;
            $streak->longest_streak = max($streak->longest_streak, $streak->current_streak);
            $streak->save();
        }

        return response()->json([
            'current' => $streak->current_streak,
            'longest' => $streak->longest_streak,
            'totalCheckIns' => $streak->total_check_ins,
        ]);
    }
}