<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LearningSession;
use App\Services\LearningSessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningSessionController extends Controller
{
    public function plan(Request $request, LearningSessionService $sessions): JsonResponse
    {
        return ApiResponse::success($sessions->plan($request->user()));
    }

    public function store(Request $request, LearningSessionService $sessions): JsonResponse
    {
        $input = $request->validate([
            'enrollment_id' => ['nullable', 'integer'],
            'assignment_id' => ['nullable', 'integer'],
            'timezone' => ['nullable', 'timezone'],
        ]);
        if (isset($input['enrollment_id']) === isset($input['assignment_id'])) {
            abort(422, 'Provide exactly one enrollment_id or assignment_id.');
        }

        $requestId = $this->requestId($request);

        return ApiResponse::success($this->payload($sessions->start($request->user(), $input, $requestId)), status: 201);
    }

    public function next(Request $request, LearningSession $session, LearningSessionService $sessions): JsonResponse
    {
        return ApiResponse::success($sessions->next($request->user(), $session));
    }

    public function complete(Request $request, LearningSession $session, LearningSessionService $sessions): JsonResponse
    {
        return ApiResponse::success($this->payload($sessions->complete($request->user(), $session, $this->requestId($request))));
    }

    private function payload(LearningSession $session): array
    {
        return [
            'type' => 'learning_session',
            'id' => $session->id,
            'course_id' => $session->course_id,
            'lesson_id' => $session->lesson_id,
            'status' => $session->status,
            'completed_at' => $session->completed_at?->toISOString(),
            'summary' => $session->summary,
        ];
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();

        return $requestId;
    }
}
