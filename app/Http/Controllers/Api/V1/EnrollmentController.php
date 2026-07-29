<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $page = Enrollment::query()
            ->with('course')
            ->where('user_id', $request->user()->id)
            ->latest('enrolled_at')
            ->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (Enrollment $enrollment): array => $this->payload($enrollment)),
            meta: ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['course_id' => ['required', 'integer', 'exists:courses,id']]);
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $fingerprint = hash('sha256', "enroll:{$data['course_id']}");
        if ($event = LearningEvent::query()->where('request_id', $requestId)->first()) {
            return $this->replay($event, $request->user()->id, $fingerprint);
        }
        $course = Course::query()->whereKey($data['course_id'])->where('status', 'published')->firstOrFail();
        try {
            $enrollment = DB::transaction(function () use ($request, $requestId, $fingerprint, $course): Enrollment {
                $enrollment = Enrollment::firstOrCreate(
                    ['user_id' => $request->user()->id, 'course_id' => $course->id],
                    ['status' => 'active', 'enrolled_at' => now('UTC')],
                );
                LearningEvent::create([
                    'user_id' => $request->user()->id,
                    'event_type' => 'enrollment_created',
                    'request_id' => $requestId,
                    'occurred_at' => now('UTC'),
                    'metadata' => ['enrollment_id' => $enrollment->id, 'idempotency_fingerprint' => $fingerprint],
                ]);

                return $enrollment;
            });
        } catch (QueryException $exception) {
            $event = LearningEvent::query()->where('request_id', $requestId)->first();
            if (! $event) {
                throw $exception;
            }

            return $this->replay($event, $request->user()->id, $fingerprint);
        }
        $enrollment->load('course');

        return ApiResponse::success($this->payload($enrollment), status: 201);
    }

    private function replay(LearningEvent $event, int $userId, string $fingerprint): JsonResponse
    {
        if ((int) $event->user_id !== $userId || $event->event_type !== 'enrollment_created'
            || data_get($event->metadata, 'idempotency_fingerprint') !== $fingerprint) {
            throw new ConflictHttpException('X-Request-ID was already used for another operation.');
        }
        $enrollment = Enrollment::query()->with('course')->findOrFail(data_get($event->metadata, 'enrollment_id'));

        return ApiResponse::success($this->payload($enrollment), status: 201);
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
