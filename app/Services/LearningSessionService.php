<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\Progress;
use App\Models\User;
use App\Models\UserVocabulary;
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
                'id' => $assignment->id, 'type' => 'teacher_lesson', 'priority' => $priority++,
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

        $remediation = LearningEvent::query()->where('user_id', $user->id)->where('is_correct', false)
            ->latest('occurred_at')->first();
        if ($remediation) {
            $items[] = ['id' => $remediation->id, 'type' => 'remediation', 'priority' => $priority++];
        }

        return ['type' => 'learning_plan', 'items' => $items];
    }

    public function start(User $user, array $input, string $requestId): LearningSession
    {
        $replay = LearningEvent::query()->where('request_id', $requestId)->where('user_id', $user->id)
            ->where('event_type', 'session_started')->first();
        if ($replay) {
            return $replay->session;
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
            ], $requestId);
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
        ], $requestId);
    }

    public function next(User $user, LearningSession $session): array
    {
        $this->ownedActive($user, $session);
        $assignedVocabularyId = $session->summary['vocabulary_id'] ?? null;
        $vocabulary = $session->lesson->vocabularies()
            ->when($assignedVocabularyId, fn ($query) => $query->whereKey($assignedVocabularyId))
            ->whereDoesntHave('userVocabularies.reviews', function ($query) use ($session): void {
                $query->whereHas('vocabularyReviewEvent', fn ($event) => $event->where('learning_session_id', $session->id));
            })
            ->orderBy('id')
            ->first();

        return [
            'type' => 'learning_session',
            'id' => $session->id,
            'status' => $session->status,
            'lesson_id' => $session->lesson_id,
            'activity' => $vocabulary ? [
                'id' => "vocabulary:{$vocabulary->id}",
                'type' => 'vocabulary',
                'vocabulary_id' => $vocabulary->id,
                'word' => $vocabulary->word,
                'meaning' => $vocabulary->meaning,
                'practice_only' => $assignedVocabularyId !== null,
            ] : null,
        ];
    }

    public function complete(User $user, LearningSession $session, string $requestId): LearningSession
    {
        $replay = LearningEvent::query()->where('request_id', $requestId)->where('user_id', $user->id)
            ->where('event_type', 'session_completed')->first();
        if ($replay) {
            return $replay->session;
        }
        $this->ownedActive($user, $session);

        return DB::transaction(function () use ($user, $session, $requestId): LearningSession {
            $session = LearningSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== 'active') {
                throw new ConflictHttpException('Learning session is not active.');
            }
            $activityCount = $session->events()->whereNotIn('event_type', ['session_started'])->count();
            if ($activityCount === 0) {
                throw new ConflictHttpException('Complete at least one learning activity first.');
            }
            $assignmentId = $session->summary['assignment_id'] ?? null;
            $practiceOnly = ($session->summary['vocabulary_id'] ?? null) !== null;
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
            ]);

            return $session->refresh();
        });
    }

    private function createSession(User $user, array $attributes, string $requestId): LearningSession
    {
        return DB::transaction(function () use ($user, $attributes, $requestId): LearningSession {
            $session = LearningSession::create([
                ...$attributes, 'user_id' => $user->id, 'status' => 'active', 'started_at' => now('UTC'),
            ]);
            LearningEvent::create([
                'learning_session_id' => $session->id, 'user_id' => $user->id,
                'event_type' => 'session_started', 'request_id' => $requestId, 'occurred_at' => now('UTC'),
            ]);

            return $session;
        });
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
