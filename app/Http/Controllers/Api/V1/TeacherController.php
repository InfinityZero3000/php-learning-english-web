<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\InterventionNote;
use App\Models\LearningEvent;
use App\Models\OperationsAudit;
use App\Models\Progress;
use App\Models\SupervisionAlert;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TeacherController extends Controller
{
    public function learners(Request $request): JsonResponse
    {
        $this->teacher($request);
        $learners = User::query()->whereIn('id', TeacherAssignment::query()
            ->where('teacher_id', $request->user()->id)->select('learner_id'))
            ->with('role:id,slug')->get(['id', 'name', 'email', 'role_id'])
            ->map(fn (User $learner) => ['type' => 'user', ...$learner->toArray()]);

        return ApiResponse::success($learners);
    }

    public function learner(Request $request, User $learner): JsonResponse
    {
        $this->assigned($request, $learner);

        return ApiResponse::success([
            'type' => 'user', 'id' => $learner->id, 'name' => $learner->name, 'email' => $learner->email,
        ]);
    }

    public function progress(Request $request, User $learner): JsonResponse
    {
        $this->assigned($request, $learner);

        return ApiResponse::success([
            'type' => 'progress',
            'learner_id' => $learner->id,
            'completed_lessons' => Progress::query()->where('user_id', $learner->id)->count(),
            'recent_events' => LearningEvent::query()->where('user_id', $learner->id)->where('occurred_at', '>=', now()->subDays(7))->count(),
        ]);
    }

    public function evidence(Request $request, User $learner): JsonResponse
    {
        abort_if($request->user()->hasRole('super_admin'), 403, 'Use the audited operational evidence endpoint.');
        $this->assigned($request, $learner);

        return ApiResponse::success($this->evidenceData($learner));
    }

    public function operationalEvidence(Request $request, User $learner): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        abort_unless($learner->hasRole('learner'), 403);
        abort_unless($request->user()->can('view-learning-evidence', $learner), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        if (OperationsAudit::query()->where('request_id', $requestId)->exists()) {
            throw new ConflictHttpException('X-Request-ID was already used. Use a fresh id for each evidence access.');
        }
        $events = $this->evidenceData($learner);
        OperationsAudit::create([
            'actor_id' => $request->user()->id,
            'action' => 'learning_evidence.viewed',
            'target_type' => 'user',
            'target_id' => (string) $learner->id,
            'request_id' => $requestId,
            'context' => [
                'reason' => $data['reason'],
                'fields' => ['id', 'event_type', 'response', 'is_correct', 'hint_level', 'pronunciation_score', 'duration_ms', 'occurred_at', 'metadata'],
            ],
            'occurred_at' => now('UTC'),
        ]);

        return ApiResponse::success($events);
    }

    private function evidenceData(User $learner): array
    {
        $events = LearningEvent::query()->where('user_id', $learner->id)->latest('occurred_at')->limit(100)->get([
            'id', 'event_type', 'response', 'is_correct', 'hint_level', 'pronunciation_score', 'duration_ms', 'occurred_at', 'metadata',
        ]);

        return $events->map(fn (LearningEvent $event) => [
            'type' => 'learning_event', ...$event->toArray(),
        ])->all();
    }

    public function alerts(Request $request): JsonResponse
    {
        $this->teacher($request);
        $learnerIds = TeacherAssignment::query()->where('teacher_id', $request->user()->id)->select('learner_id');

        return ApiResponse::success(SupervisionAlert::query()->with('learner:id,name,email')
            ->whereIn('learner_id', $learnerIds)->latest('detected_at')->get()
            ->map(fn (SupervisionAlert $alert) => ['type' => 'supervision_alert', ...$alert->toArray()]));
    }

    public function alert(Request $request, SupervisionAlert $alert): JsonResponse
    {
        $this->assigned($request, $alert->learner);

        return ApiResponse::success([
            'type' => 'supervision_alert',
            ...$alert->load(['learner:id,name,email', 'assignments', 'interventionNotes'])->toArray(),
        ]);
    }

    public function resolve(Request $request, SupervisionAlert $alert): JsonResponse
    {
        $this->assigned($request, $alert->learner);
        $data = $request->validate([
            'resolution_code' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $alert->update([
            'state' => 'resolved', 'active_fingerprint' => null, 'resolved_at' => now('UTC'),
            'resolved_by' => $request->user()->id,
            'resolution_note' => $data['resolution_code'].(($data['note'] ?? null) ? ': '.$data['note'] : ''),
        ]);

        return ApiResponse::success(['type' => 'supervision_alert', ...$alert->refresh()->toArray()]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $this->teacher($request);

        return ApiResponse::success(Assignment::query()->with(['learner:id,name', 'lesson:id,title', 'vocabulary:id,word'])
            ->when(! $request->user()->hasRole('super_admin'), fn ($query) => $query
                ->where('teacher_id', $request->user()->id)
                ->whereIn('learner_id', TeacherAssignment::query()
                    ->where('teacher_id', $request->user()->id)->select('learner_id')))
            ->latest()->get()
            ->map(fn (Assignment $assignment) => ['type' => 'assignment', ...$assignment->toArray()]));
    }

    public function createAssignment(Request $request): JsonResponse
    {
        $this->teacher($request);
        $data = $request->validate([
            'learner_id' => ['required', 'integer', 'exists:users,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id', 'required_without:vocabulary_id'],
            'vocabulary_id' => ['nullable', 'integer', 'exists:vocabularies,id', 'required_without:lesson_id'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
        ]);
        if (isset($data['lesson_id'], $data['vocabulary_id'])) {
            throw ValidationException::withMessages(['lesson_id' => 'Choose either a lesson or vocabulary target, not both.']);
        }
        $learner = User::findOrFail($data['learner_id']);
        $this->assigned($request, $learner);
        $fingerprint = $this->assignmentFingerprint(['create', $data]);
        if ($audit = $this->assignmentReplay($request, 'assignment.created', $fingerprint)) {
            return ApiResponse::success([
                'type' => 'assignment',
                ...Assignment::with(['learner:id,name', 'lesson:id,title', 'vocabulary:id,word'])
                    ->findOrFail($audit->target_id)->toArray(),
            ], status: 201);
        }

        try {
            $assignment = DB::transaction(function () use ($request, $data, $fingerprint): Assignment {
                $assignment = Assignment::create([
                    ...$data, 'teacher_id' => $request->user()->id, 'status' => 'pending',
                ]);
                $this->assignmentAudit($request, 'assignment.created', $assignment, $fingerprint);

                return $assignment;
            })->load(['learner:id,name', 'lesson:id,title', 'vocabulary:id,word']);
        } catch (QueryException $exception) {
            if ($audit = $this->assignmentReplay($request, 'assignment.created', $fingerprint)) {
                $assignment = Assignment::with(['learner:id,name', 'lesson:id,title', 'vocabulary:id,word'])
                    ->findOrFail($audit->target_id);
            } else {
                throw $exception;
            }
        }

        return ApiResponse::success(['type' => 'assignment', ...$assignment->toArray()], status: 201);
    }

    public function updateAssignment(Request $request, Assignment $assignment): JsonResponse
    {
        $this->assigned($request, $assignment->learner);
        abort_unless($request->user()->hasRole('super_admin') || $assignment->teacher_id === $request->user()->id, 403);
        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,in_progress,cancelled'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'due_at' => ['sometimes', 'nullable', 'date'],
        ]);
        abort_if($assignment->status === 'completed', 409, 'Completed assignments cannot be changed.');
        $fingerprint = $this->assignmentFingerprint(['update', $assignment->id, $data]);
        if ($audit = $this->assignmentReplay($request, 'assignment.updated', $fingerprint)) {
            $assignment = Assignment::findOrFail($audit->target_id);
        } else {
            try {
                DB::transaction(function () use ($request, $assignment, $data, $fingerprint): void {
                    $assignment->update($data);
                    $this->assignmentAudit($request, 'assignment.updated', $assignment, $fingerprint);
                });
            } catch (QueryException $exception) {
                if (! $this->assignmentReplay($request, 'assignment.updated', $fingerprint)) {
                    throw $exception;
                }
                $assignment->refresh();
            }
        }

        return ApiResponse::success([
            'type' => 'assignment',
            ...$assignment->load(['learner:id,name', 'lesson:id,title', 'vocabulary:id,word'])->toArray(),
        ]);
    }

    public function note(Request $request): JsonResponse
    {
        $this->teacher($request);
        $data = $request->validate([
            'learner_id' => ['required', 'integer', 'exists:users,id'],
            'supervision_alert_id' => ['nullable', 'integer', 'exists:supervision_alerts,id'],
            'assignment_id' => ['nullable', 'integer', 'exists:assignments,id'],
            'note' => ['required', 'string', 'max:4000'],
        ]);
        $this->assigned($request, User::findOrFail($data['learner_id']));
        if (isset($data['supervision_alert_id'])) {
            abort_unless(SupervisionAlert::query()->whereKey($data['supervision_alert_id'])
                ->where('learner_id', $data['learner_id'])->exists(), 422);
        }
        if (isset($data['assignment_id'])) {
            abort_unless(Assignment::query()->whereKey($data['assignment_id'])
                ->where('learner_id', $data['learner_id'])
                ->when(! $request->user()->hasRole('super_admin'), fn ($query) => $query->where('teacher_id', $request->user()->id))
                ->exists(), 422);
        }

        $note = InterventionNote::create([...$data, 'teacher_id' => $request->user()->id]);

        return ApiResponse::success(['type' => 'intervention_note', ...$note->toArray()], status: 201);
    }

    private function teacher(Request $request): void
    {
        abort_unless($request->user()->hasRole('teacher', 'super_admin'), 403);
    }

    private function assigned(Request $request, User $learner): void
    {
        $this->teacher($request);
        abort_unless($learner->hasRole('learner'), 403);
        abort_unless($request->user()->hasRole('super_admin') || TeacherAssignment::query()
            ->where('teacher_id', $request->user()->id)->where('learner_id', $learner->id)->exists(), 403);
    }

    private function assignmentReplay(Request $request, string $action, string $fingerprint): ?OperationsAudit
    {
        $requestId = $request->header('X-Request-ID');
        validator(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $audit = OperationsAudit::query()->where('request_id', $requestId)->first();
        if ($audit && ($audit->action !== $action || data_get($audit->context, 'fingerprint') !== $fingerprint)) {
            throw new ConflictHttpException('X-Request-ID was already used for another operation.');
        }

        return $audit;
    }

    private function assignmentAudit(Request $request, string $action, Assignment $assignment, string $fingerprint): void
    {
        OperationsAudit::create([
            'actor_id' => $request->user()->id, 'action' => $action,
            'target_type' => 'assignment', 'target_id' => (string) $assignment->id,
            'request_id' => $request->header('X-Request-ID'),
            'context' => ['fingerprint' => $fingerprint],
            'after_state' => $assignment->toArray(), 'occurred_at' => now('UTC'),
        ]);
    }

    private function assignmentFingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
