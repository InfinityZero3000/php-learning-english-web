<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\InterventionNote;
use App\Models\LearningEvent;
use App\Models\Progress;
use App\Models\SupervisionAlert;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $this->assigned($request, $learner);
        $events = LearningEvent::query()->where('user_id', $learner->id)->latest('occurred_at')->limit(100)->get([
            'id', 'event_type', 'response', 'is_correct', 'hint_level', 'pronunciation_score', 'duration_ms', 'occurred_at', 'metadata',
        ]);

        return ApiResponse::success($events->map(fn (LearningEvent $event) => [
            'type' => 'learning_event', ...$event->toArray(),
        ]));
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
            ->where('teacher_id', $request->user()->id)->latest()->get()
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

        $assignment = Assignment::create([
            ...$data, 'teacher_id' => $request->user()->id, 'status' => 'pending',
        ])->load(['learner:id,name', 'lesson:id,title', 'vocabulary:id,word']);

        return ApiResponse::success(['type' => 'assignment', ...$assignment->toArray()], status: 201);
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
        abort_unless($request->user()->hasRole('super_admin') || TeacherAssignment::query()
            ->where('teacher_id', $request->user()->id)->where('learner_id', $learner->id)->exists(), 403);
    }
}
