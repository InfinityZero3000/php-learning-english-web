<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearningController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        [$from, $to, $meta] = $this->range($request);
        $this->authorizeRequest($request);
        $enrollments = DB::table('enrollments')->whereBetween('enrolled_at', [$from, $to])->count();
        $completed = DB::table('enrollments')->whereBetween('completed_at', [$from, $to])->count();
        $correctRate = DB::table('learning_events')->whereBetween('occurred_at', [$from, $to])
            ->whereNotNull('is_correct')->avg('is_correct');

        return ApiResponse::success([
            'type' => 'learning_overview',
            'enrollments' => $enrollments,
            'completed_enrollments' => $completed,
            'completion_rate' => $enrollments ? round($completed * 100 / $enrollments, 2) : 0,
            'sessions_started' => DB::table('learning_sessions')->whereBetween('started_at', [$from, $to])->count(),
            'sessions_completed' => DB::table('learning_sessions')->whereBetween('completed_at', [$from, $to])->count(),
            'quiz_attempts' => DB::table('attempts')->whereBetween('completed_at', [$from, $to])->count(),
            'average_score' => $this->rounded(DB::table('attempts')->whereBetween('completed_at', [$from, $to])->avg('score')),
            'average_duration_ms' => $this->rounded(DB::table('learning_events')->whereBetween('occurred_at', [$from, $to])->avg('duration_ms')),
            'correct_rate' => $correctRate === null ? null : round((float) $correctRate * 100, 2),
        ], meta: $meta);
    }

    public function quizzes(Request $request): JsonResponse
    {
        [$from, $to, $meta] = $this->range($request);
        $this->authorizeRequest($request);
        $byCourse = DB::table('attempts')
            ->join('quizzes', 'quizzes.id', '=', 'attempts.quiz_id')
            ->join('lessons', 'lessons.id', '=', 'quizzes.lesson_id')
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->whereBetween('attempts.completed_at', [$from, $to])
            ->groupBy('courses.id', 'courses.title')
            ->orderBy('courses.id')
            ->get(['courses.id as course_id', 'courses.title as course_title', DB::raw('COUNT(attempts.id) as attempts'), DB::raw('AVG(attempts.score) as average_score')])
            ->map(fn ($row) => [...(array) $row, 'attempts' => (int) $row->attempts, 'average_score' => $this->rounded($row->average_score)])
            ->all();

        return ApiResponse::success([
            'type' => 'quiz_analytics',
            'attempts' => array_sum(array_column($byCourse, 'attempts')),
            'by_course' => $byCourse,
            'by_date' => $this->dateBuckets('attempts', 'completed_at'),
        ], meta: $meta);
    }

    public function fsrs(Request $request): JsonResponse
    {
        [$from, $to, $meta] = $this->range($request);
        $this->authorizeRequest($request);
        $reviews = DB::table('vocabulary_reviews')->whereBetween('reviewed_at', [$from, $to]);

        return ApiResponse::success([
            'type' => 'fsrs_analytics',
            'reviews' => (clone $reviews)->count(),
            'average_stability' => $this->rounded((clone $reviews)->avg('stability')),
            'average_difficulty' => $this->rounded((clone $reviews)->avg('difficulty')),
            'state_bands' => DB::table('user_vocabularies')->select('state', DB::raw('COUNT(*) as count'))
                ->groupBy('state')->orderBy('state')->get()
                ->map(fn ($row) => ['state' => $row->state, 'count' => (int) $row->count])->all(),
            'by_date' => $this->dateBuckets('vocabulary_reviews', 'reviewed_at'),
        ], meta: $meta);
    }

    public function progress(Request $request): JsonResponse
    {
        [$from, $to, $meta] = $this->range($request);
        $this->authorizeRequest($request);
        $byCourse = DB::table('progress')->join('lessons', 'lessons.id', '=', 'progress.lesson_id')
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->whereBetween('progress.completed_at', [$from, $to])
            ->groupBy('courses.id', 'courses.title')->orderBy('courses.id')
            ->get(['courses.id as course_id', 'courses.title as course_title', DB::raw('COUNT(progress.id) as completed_lessons')])
            ->map(fn ($row) => [...(array) $row, 'completed_lessons' => (int) $row->completed_lessons])->all();

        return ApiResponse::success([
            'type' => 'progress_analytics',
            'completed_lessons' => array_sum(array_column($byCourse, 'completed_lessons')),
            'by_course' => $byCourse,
            'by_date' => $this->dateBuckets('progress', 'completed_at'),
        ], meta: $meta);
    }

    public function report(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $this->authorizeRequest($request);
        $period = $from->toDateString().'/'.$to->toDateString();
        $enrollments = $this->courseCounts('enrollments', 'enrolled_at', $from, $to);
        $started = $this->courseCounts('learning_sessions', 'started_at', $from, $to);
        $completed = $this->courseCounts('enrollments', 'completed_at', $from, $to);
        $scores = DB::table('attempts')->join('quizzes', 'quizzes.id', '=', 'attempts.quiz_id')
            ->join('lessons', 'lessons.id', '=', 'quizzes.lesson_id')
            ->whereBetween('attempts.completed_at', [$from, $to])->groupBy('lessons.course_id')
            ->pluck(DB::raw('AVG(attempts.score)'), 'lessons.course_id');
        $courses = DB::table('courses')->orderBy('id')->get(['id', 'title']);

        return response()->streamDownload(function () use ($courses, $period, $enrollments, $started, $completed, $scores): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['period', 'course_id', 'course_title', 'enrollments', 'started', 'completed', 'completion_rate', 'average_score']);
            foreach ($courses as $course) {
                $enrolled = (int) ($enrollments[$course->id] ?? 0);
                $done = (int) ($completed[$course->id] ?? 0);
                fputcsv($output, [
                    $period, $course->id, $this->safeCsv($course->title), $enrolled,
                    (int) ($started[$course->id] ?? 0), $done,
                    $enrolled ? round($done * 100 / $enrolled, 2) : 0,
                    $this->rounded($scores[$course->id] ?? null),
                ]);
            }
            fclose($output);
        }, 'learning-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $to = CarbonImmutable::createFromFormat('Y-m-d', $data['to'] ?? now('UTC')->toDateString(), 'UTC')->startOfDay();
        $from = CarbonImmutable::createFromFormat('Y-m-d', $data['from'] ?? $to->subDays(29)->toDateString(), 'UTC')->startOfDay();
        if ($from->greaterThan($to) || $from->diffInDays($to) > 365) {
            throw ValidationException::withMessages(['from' => 'The inclusive date range must be between 1 and 366 days.']);
        }

        return [$from, $to->endOfDay(), ['from' => $from->toDateString(), 'to' => $to->toDateString()]];
    }

    private function authorizeRequest(Request $request): void
    {
        abort_unless($request->user()->can('manage-content'), 403);
    }

    private function dateBuckets(string $table, string $column): array
    {
        // The caller already constrains its aggregate; this helper is used only with fixed internal identifiers.
        [$from, $to] = $this->range(request());

        return DB::table($table)->whereBetween($column, [$from, $to])
            ->selectRaw("DATE({$column}) as date, COUNT(*) as count")
            ->groupBy(DB::raw("DATE({$column})"))->orderBy('date')->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count])->all();
    }

    private function courseCounts(string $table, string $column, CarbonImmutable $from, CarbonImmutable $to)
    {
        return DB::table($table)->whereBetween($column, [$from, $to])
            ->groupBy('course_id')->pluck(DB::raw('COUNT(*)'), 'course_id');
    }

    private function rounded(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }

    private function safeCsv(string $value): string
    {
        $value = preg_replace('/^[\x00-\x20\x7F]+/u', '', $value) ?? '';

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
