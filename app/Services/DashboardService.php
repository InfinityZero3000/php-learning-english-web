<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Bookmark;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const CACHE_TTL = 600; // 10 minutes

    public function getGeneralStats(): array
    {
        return Cache::remember('dashboard:stats', self::CACHE_TTL, function () {
            return [
                'total_users'        => User::count(),
                'total_courses'      => Course::count(),
                'total_lessons'      => Lesson::count(),
                'total_vocabularies' => Vocabulary::count(),
                'total_quizzes'      => \App\Models\Quiz::count(),
                'total_attempts'     => Attempt::count(),
                'avg_quiz_score'     => round(Attempt::avg('score') ?? 0, 2),
                'new_users_today'    => User::whereDate('created_at', today())->count(),
                'active_users_week'  => User::where('updated_at', '>=', now()->subWeek())->count(),
            ];
        });
    }

    public function getUserDashboard(int $userId): array
    {
        $cacheKey = "user:{$userId}:dashboard";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            $attempts = Attempt::where('user_id', $userId);

            return [
                'bookmarks_count'    => Bookmark::where('user_id', $userId)->count(),
                'total_attempts'     => $attempts->count(),
                'avg_quiz_score'     => round($attempts->avg('score') ?? 0, 2),
                'highest_score'      => $attempts->max('score') ?? 0,
                'passed_quizzes'     => $attempts->where('passed', true)->count(),
                'failed_quizzes'     => $attempts->where('passed', false)->count(),
                'completed_lessons'  => DB::table('progress')->where('user_id', $userId)->whereNotNull('completed_at')->count(),
                'recent_activity'    => $attempts->with('quiz:id,title')
                    ->latest('created_at')
                    ->limit(5)
                    ->get()
                    ->map(function ($attempt) {
                        return [
                            'quiz_title'  => $attempt->quiz?->title,
                            'score'       => $attempt->score,
                            'passed'      => $attempt->passed,
                            'completed_at' => $attempt->created_at?->toDateTimeString(),
                        ];
                    }),
            ];
        });
    }

    public function getAdminChartData(): array
    {
        return Cache::remember('admin:chart_data', self::CACHE_TTL, function () {
            return [
                'users_per_day' => $this->getUsersPerDay(30),
                'quiz_scores_distribution' => $this->getQuizScoreDistribution(),
                'top_courses' => $this->getTopCourses(),
            ];
        });
    }

    private function getUsersPerDay(int $days = 30): array
    {
        return User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getQuizScoreDistribution(): array
    {
        $ranges = [
            '0-20'   => [0, 20],
            '21-40'  => [21, 40],
            '41-60'  => [41, 60],
            '61-80'  => [61, 80],
            '81-100' => [81, 100],
        ];

        $distribution = [];
        foreach ($ranges as $label => [$min, $max]) {
            $distribution[$label] = Attempt::whereBetween('score', [$min, $max])->count();
        }

        return $distribution;
    }

    private function getTopCourses(): array
    {
        return Course::withCount('lessons')
            ->orderByDesc('lessons_count')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id'            => $c->id,
                'title'         => $c->title,
                'lessons_count' => $c->lessons_count,
            ])
            ->toArray();
    }

    public function clearUserCache(int $userId): void
    {
        Cache::forget("user:{$userId}:dashboard");
    }

    public function clearAllCache(): void
    {
        Cache::forget('dashboard:stats');
        Cache::forget('admin:chart_data');
    }
}