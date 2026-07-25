<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Lesson;
use App\Services\StreakCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(StreakCalculator $streakCalculator): JsonResponse
    {
        $userId = request()->user()->id;
        $days = collect(range(0, 6))->map(fn ($day) => now()->subDays(6 - $day)->toDateString());
        $history = DB::table('study_history')->where('user_id', $userId);
        $daily = (clone $history)->where('created_at', '>=', now()->subDays(6)->startOfDay())->selectRaw('DATE(created_at) as date, SUM(duration_seconds) as seconds')->groupByRaw('DATE(created_at)')->pluck('seconds', 'date');

        return response()->json(['data' => ['lessons_completed' => DB::table('lesson_progress')->where('user_id', $userId)->where('status', 'completed')->count(), 'lessons_total' => Lesson::count(), 'average_quiz_score_30_days' => Attempt::where('user_id', $userId)->where('finished_at', '>=', now()->subDays(30))->avg('score') ?? 0, 'streak' => $streakCalculator->calculate((clone $history)->distinct()->pluck(DB::raw('DATE(created_at)'))), 'bookmarks_total' => DB::table('vocabulary_bookmarks')->where('user_id', $userId)->count(), 'progress_7_days' => $days->map(fn ($date) => ['date' => $date, 'minutes' => round(($daily[$date] ?? 0) / 60, 2)])->values()]]);
    }
}
