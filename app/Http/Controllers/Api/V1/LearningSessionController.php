<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use App\Services\LearningSessionService;
use App\Services\SupervisionAlertService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LearningSessionController extends Controller
{
    public function assignments(Request $request): JsonResponse
    {
        return ApiResponse::success(Assignment::query()
            ->where('learner_id', $request->user()->id)
            ->with(['teacher:id,name', 'lesson:id,title', 'vocabulary:id,word,meaning'])
            ->latest()->get()
            ->map(fn (Assignment $assignment) => ['type' => 'assignment', ...$assignment->toArray()]));
    }

    public function plan(Request $request, LearningSessionService $sessions): JsonResponse
    {
        return ApiResponse::success($sessions->plan($request->user()));
    }

    public function store(Request $request, LearningSessionService $sessions): JsonResponse
    {
        $input = $request->validate([
            'enrollment_id' => ['nullable', 'integer'],
            'assignment_id' => ['nullable', 'integer'],
            'lesson_id' => ['nullable', 'integer'],
            'timezone' => ['nullable', 'timezone'],
        ]);
        if (isset($input['enrollment_id']) === isset($input['assignment_id'])) {
            abort(422, 'Provide exactly one enrollment_id or assignment_id.');
        }
        if (isset($input['assignment_id'], $input['lesson_id'])) {
            abort(422, 'lesson_id is only supported with enrollment_id.');
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

    public function attempt(Request $request, LearningSession $session): JsonResponse
    {
        $requestId = $this->requestId($request);
        $data = $request->validate([
            'activity_id' => ['required', 'string', 'max:128'],
            'answer' => ['required', 'string', 'max:2000'],
            'duration_ms' => ['required', 'integer', 'min:0', 'max:3600000'],
            'hint_count' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        $fingerprint = hash('sha256', json_encode([$session->id, $data], JSON_THROW_ON_ERROR));
        if ($existing = LearningEvent::query()->where('request_id', $requestId)->first()) {
            $this->assertAttemptReplay($existing, $request->user()->id, $fingerprint);

            return ApiResponse::success(['type' => 'learning_attempt', 'event_id' => $existing->id, 'is_correct' => $existing->is_correct]);
        }
        abort_unless($session->user_id === $request->user()->id && $session->status === 'active', 403);
        $created = false;
        try {
            $event = DB::transaction(function () use ($request, $session, $requestId, $data, $fingerprint, &$created): LearningEvent {
                $session = LearningSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
                abort_unless($session->user_id === $request->user()->id && $session->status === 'active', 403);
                if ($existing = LearningEvent::query()->where('request_id', $requestId)->first()) {
                    $this->assertAttemptReplay($existing, $request->user()->id, $fingerprint);

                    return $existing;
                }
                $vocabularyId = str_starts_with($data['activity_id'], 'vocabulary:')
                    ? (int) substr($data['activity_id'], 11) : 0;
                $vocabulary = Vocabulary::query()->whereKey($vocabularyId)->where('lesson_id', $session->lesson_id)->firstOrFail();
                $correct = mb_strtolower(trim($data['answer'])) === mb_strtolower(trim($vocabulary->meaning));
                $event = LearningEvent::create([
                    'learning_session_id' => $session->id,
                    'user_id' => $request->user()->id,
                    'event_type' => 'answer',
                    'request_id' => $requestId,
                    'response' => $data['answer'],
                    'is_correct' => $correct,
                    'hint_level' => $data['hint_count'],
                    'duration_ms' => $data['duration_ms'],
                    'occurred_at' => now('UTC'),
                    'metadata' => [
                        'activity_id' => $data['activity_id'],
                        'vocabulary_id' => $vocabulary->id,
                        'idempotency_fingerprint' => $fingerprint,
                    ],
                ]);
                $created = true;
                if (($session->summary['vocabulary_id'] ?? null) === null) {
                    UserVocabulary::firstOrCreate(
                        ['user_id' => $request->user()->id, 'vocabulary_id' => $vocabulary->id],
                        ['due_at' => now('UTC')],
                    );
                }

                return $event;
            });
        } catch (QueryException $exception) {
            $event = LearningEvent::query()->where('request_id', $requestId)->first();
            if (! $event) {
                throw $exception;
            }
            $this->assertAttemptReplay($event, $request->user()->id, $fingerprint);
        }
        if ($created) {
            app(SupervisionAlertService::class)->evaluate($request->user());
        }

        return ApiResponse::success(['type' => 'learning_attempt', 'event_id' => $event->id, 'is_correct' => $event->is_correct]);
    }

    private function assertAttemptReplay(LearningEvent $event, int $userId, string $fingerprint): void
    {
        if ((int) $event->user_id !== $userId || $event->event_type !== 'answer'
            || data_get($event->metadata, 'idempotency_fingerprint') !== $fingerprint) {
            throw new ConflictHttpException('X-Request-ID was already used for another operation.');
        }
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
