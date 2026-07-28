<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\Progress;
use App\Models\SupervisionAlert;
use App\Models\User;
use App\Models\UserVocabulary;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LearningSessionService
{
    public function plan(User $user): array
    {
        $priority = 1;
        $items = Assignment::query()
            ->where('learner_id', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '<=', now('UTC')))
            ->orderBy('due_at')
            ->get()
            ->map(fn (Assignment $assignment): array => [
                'id' => $assignment->id,
                'type' => $assignment->vocabulary_id ? 'teacher_vocabulary_practice' : 'teacher_lesson',
                'priority' => $priority++,
            ])->all();

        foreach (UserVocabulary::query()->where('user_id', $user->id)
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '<=', now('UTC')))
            ->orderBy('due_at')->get() as $card) {
            $items[] = ['id' => $card->id, 'type' => 'fsrs_review', 'priority' => $priority++];
        }

        foreach (Enrollment::query()->where('user_id', $user->id)->where('status', 'active')->get() as $enrollment) {
            $lesson = $enrollment->course->lessons()
                ->where('status', 'published')
                ->whereDoesntHave('progress', fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('sort_order')
                ->first();
            if ($lesson) {
                $items[] = ['id' => $enrollment->id, 'type' => 'course_activity', 'priority' => $priority++];
            }
        }

        $pronunciationEnrollment = Enrollment::query()->where('user_id', $user->id)->where('status', 'active')->first();
        if ($pronunciationEnrollment && SupervisionAlert::query()->where('learner_id', $user->id)
            ->where('rule_key', 'weak_pronunciation')->where('state', 'open')->exists()) {
            $items[] = ['id' => $pronunciationEnrollment->id, 'type' => 'pronunciation_task', 'priority' => $priority++];
        }

        $remediation = LearningEvent::query()->where('user_id', $user->id)->whereNotNull('is_correct')
            ->where('occurred_at', '>=', now('UTC')->subDays(7))->get()
            ->groupBy(fn (LearningEvent $event) => data_get($event->metadata, 'vocabulary_id'))
            ->map(fn ($events) => $events->sortByDesc('occurred_at')->take(5))
            ->first(fn ($events, $concept) => $concept && $events->where('is_correct', false)->count() >= 3)?->first();
        if ($remediation) {
            $items[] = ['id' => $remediation->id, 'type' => 'remediation', 'priority' => $priority++];
        }

        return ['type' => 'learning_plan', 'items' => $items];
    }

    public function start(User $user, array $input, string $requestId): LearningSession
    {
        $fingerprint = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        $replay = LearningEvent::query()->where('request_id', $requestId)->first();
        if ($replay) {
            return $this->replay($replay, $user, 'session_started', $fingerprint);
        }

        if (isset($input['assignment_id'])) {
            $assignment = Assignment::query()
                ->with(['lesson', 'vocabulary.lesson'])
                ->whereKey($input['assignment_id'])
                ->where('learner_id', $user->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->first();
            if (! $assignment) {
                throw new HttpException(403, 'Assignment is not available to this learner.');
            }
            $lesson = $assignment->lesson ?? $assignment->vocabulary?->lesson;
            if (! $lesson) {
                throw new ConflictHttpException('Assignment has no learnable lesson.');
            }

            return $this->createSession($user, [
                'course_id' => $lesson->course_id, 'lesson_id' => $lesson->id,
                'summary' => ['assignment_id' => $assignment->id, 'vocabulary_id' => $assignment->vocabulary_id],
            ], $requestId, $fingerprint);
        }

        $enrollment = Enrollment::query()
            ->whereKey($input['enrollment_id'] ?? null)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
        if (! $enrollment) {
            throw new HttpException(403, 'Enrollment is not available to this learner.');
        }
        $lesson = $enrollment->course->lessons()
            ->where('status', 'published')
            ->whereDoesntHave('progress', fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('sort_order')
            ->first();
        if (! $lesson) {
            throw new ConflictHttpException('This course has no remaining lesson.');
        }

        return $this->createSession($user, [
            'enrollment_id' => $enrollment->id, 'course_id' => $enrollment->course_id, 'lesson_id' => $lesson->id,
        ], $requestId, $fingerprint);
    }

    public function next(User $user, LearningSession $session): array
    {
        $this->ownedActive($user, $session);
        $assignedVocabularyId = $session->summary['vocabulary_id'] ?? null;
        $answeredIds = $session->events()->where('event_type', 'answer')->get('metadata')
            ->pluck('metadata')->map(fn ($metadata) => data_get($metadata, 'vocabulary_id'))
            ->filter()->unique()->values();
        $vocabulary = $session->lesson->vocabularies()
            ->when($assignedVocabularyId, fn ($query) => $query->whereKey($assignedVocabularyId))
            ->whereNotIn('id', $answeredIds)
            ->orderBy('id')
            ->first();
        $total = $assignedVocabularyId ? 1 : $session->lesson->vocabularies()->count();

        return [
            'type' => 'learning_session',
            'id' => $session->id,
            'status' => $session->status,
            'lesson_id' => $session->lesson_id,
            'progress' => ['completed' => $answeredIds->count(), 'total' => $total],
            'activity' => $vocabulary ? [
                'id' => "vocabulary:{$vocabulary->id}",
                'type' => 'vocabulary',
                'vocabulary_id' => $vocabulary->id,
                'word' => $vocabulary->word,
                'meaning' => $vocabulary->meaning,
                'audio_url' => $vocabulary->external_audio_url,
                'practice_only' => $assignedVocabularyId !== null,
            ] : null,
        ];
    }

    public function complete(User $user, LearningSession $session, string $requestId): LearningSession
    {
        $fingerprint = hash('sha256', "complete:{$session->id}");
        $replay = LearningEvent::query()->where('request_id', $requestId)->first();
        if ($replay) {
            return $this->replay($replay, $user, 'session_completed', $fingerprint);
        }
        $this->ownedActive($user, $session);

        try {
            return DB::transaction(function () use ($user, $session, $requestId, $fingerprint): LearningSession {
                $session = LearningSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
                if ($session->status !== 'active') {
                    if ($replay = LearningEvent::query()->where('request_id', $requestId)->first()) {
                        return $this->replay($replay, $user, 'session_completed', $fingerprint);
                    }
                    throw new ConflictHttpException('Learning session is not active.');
                }
                $assignmentId = $session->summary['assignment_id'] ?? null;
                $practiceOnly = ($session->summary['vocabulary_id'] ?? null) !== null;
                $expectedVocabularyIds = $practiceOnly
                    ? collect([$session->summary['vocabulary_id']])
                    : $session->lesson->vocabularies()->pluck('id');
                $answeredVocabularyIds = $session->events()->where('event_type', 'answer')->get('metadata')
                    ->pluck('metadata')->map(fn ($metadata) => data_get($metadata, 'vocabulary_id'))
                    ->filter()->unique();
                if ($expectedVocabularyIds->diff($answeredVocabularyIds)->isNotEmpty()) {
                    throw new ConflictHttpException('Complete every learning activity before finishing the session.');
                }
                $activityCount = $answeredVocabularyIds->count();
                if (! $practiceOnly) {
                    Progress::firstOrCreate(
                        ['user_id' => $user->id, 'lesson_id' => $session->lesson_id],
                        ['completed_at' => now('UTC')],
                    );
                }
                if ($assignmentId) {
                    Assignment::query()->whereKey($assignmentId)->where('learner_id', $user->id)->update([
                        'status' => 'completed', 'completed_at' => now('UTC'),
                    ]);
                }
                $summary = [
                    ...($session->summary ?? []),
                    'events' => $activityCount,
                    'completed_lesson_id' => $practiceOnly ? null : $session->lesson_id,
                ];
                $session->update(['status' => 'completed', 'completed_at' => now('UTC'), 'summary' => $summary]);

                if (! $practiceOnly && $session->enrollment && ! $session->enrollment->course->lessons()
                    ->where('status', 'published')
                    ->whereDoesntHave('progress', fn ($query) => $query->where('user_id', $user->id))
                    ->exists()) {
                    $session->enrollment->update(['status' => 'completed', 'completed_at' => now('UTC')]);
                }
                LearningEvent::create([
                    'learning_session_id' => $session->id, 'user_id' => $user->id,
                    'event_type' => 'session_completed', 'request_id' => $requestId, 'occurred_at' => now('UTC'),
                    'metadata' => ['idempotency_fingerprint' => $fingerprint],
                ]);

                return $session->refresh();
            });
        } catch (QueryException $exception) {
            $replay = LearningEvent::query()->where('request_id', $requestId)->first();
            if (! $replay) {
                throw $exception;
            }

            return $this->replay($replay, $user, 'session_completed', $fingerprint);
        }
    }

    private function createSession(User $user, array $attributes, string $requestId, string $fingerprint): LearningSession
    {
        try {
            return DB::transaction(function () use ($user, $attributes, $requestId, $fingerprint): LearningSession {
                $session = LearningSession::create([
                    ...$attributes, 'user_id' => $user->id, 'status' => 'active', 'started_at' => now('UTC'),
                ]);
                LearningEvent::create([
                    'learning_session_id' => $session->id, 'user_id' => $user->id,
                    'event_type' => 'session_started', 'request_id' => $requestId, 'occurred_at' => now('UTC'),
                    'metadata' => ['idempotency_fingerprint' => $fingerprint],
                ]);

                return $session;
            });
        } catch (QueryException $exception) {
            $replay = LearningEvent::query()->where('request_id', $requestId)->first();
            if (! $replay) {
                throw $exception;
            }

            return $this->replay($replay, $user, 'session_started', $fingerprint);
        }
    }

    private function replay(LearningEvent $event, User $user, string $type, string $fingerprint): LearningSession
    {
        if ((int) $event->user_id !== (int) $user->id || $event->event_type !== $type
            || data_get($event->metadata, 'idempotency_fingerprint') !== $fingerprint) {
            throw new ConflictHttpException('X-Request-ID was already used for another operation.');
        }

        return $event->session;
    }

    private function ownedActive(User $user, LearningSession $session): void
    {
        if ((int) $session->user_id !== (int) $user->id) {
            throw new HttpException(403, 'Learning session is not available to this learner.');
        }
        if ($session->status !== 'active') {
            throw new ConflictHttpException('Learning session is not active.');
        }
    }
}
