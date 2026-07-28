<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $enrollments = Enrollment::query()
            ->with('course')
            ->where('user_id', $request->user()->id)
            ->latest('enrolled_at')
            ->get()
            ->map(fn (Enrollment $enrollment): array => $this->payload($enrollment));

        return ApiResponse::success($enrollments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['course_id' => ['required', 'integer', 'exists:courses,id']]);
        $course = Course::query()->whereKey($data['course_id'])->where('status', 'published')->firstOrFail();
        $enrollment = Enrollment::firstOrCreate(
            ['user_id' => $request->user()->id, 'course_id' => $course->id],
            ['status' => 'active', 'enrolled_at' => now('UTC')],
        );
        $enrollment->load('course');

        return ApiResponse::success($this->payload($enrollment), status: $enrollment->wasRecentlyCreated ? 201 : 200);
    }

    private function payload(Enrollment $enrollment): array
    {
        return [
            'type' => 'enrollment',
            'id' => $enrollment->id,
            'course_id' => $enrollment->course_id,
            'title' => $enrollment->course->title,
            'status' => $enrollment->status,
            'completed_at' => $enrollment->completed_at?->toISOString(),
        ];
    }
}
