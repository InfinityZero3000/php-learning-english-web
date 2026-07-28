<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Enrollment;
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
            ->whereNotNull('lesson_id')
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '<=', now('UTC')))
            ->orderBy('due_at')
            ->get()
            ->map(fn (Assignment $assignment): array => [
                'id' => $assignment->id, 'type' => 'teacher_lesson', 'priority' => $priority++,
            ])->all();

        foreach (UserVocabulary::query()->where('user_id', $user->id)->where('due_at', '<=', now('UTC'))->orderBy('due_at')->get() as $card) {
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

        return ['type' => 'learning_plan', 'items' => $items];
    }

    public function start(User $user, array $input): LearningSession
    {
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

            return LearningSession::create([
                'user_id' => $user->id,
                'course_id' => $lesson->course_id,
                'lesson_id' => $lesson->id,
                'status' => 'active',
                'started_at' => now('UTC'),
                'summary' => ['assignment_id' => $assignment->id, 'vocabulary_id' => $assignment->vocabulary_id],
            ]);
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

        return LearningSession::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $user->id,
            'course_id' => $enrollment->course_id,
            'lesson_id' => $lesson->id,
            'status' => 'active',
            'started_at' => now('UTC'),
        ]);
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

    public function complete(User $user, LearningSession $session): LearningSession
    {
        $this->ownedActive($user, $session);

        return DB::transaction(function () use ($user, $session): LearningSession {
            Progress::firstOrCreate(
                ['user_id' => $user->id, 'lesson_id' => $session->lesson_id],
                ['completed_at' => now('UTC')],
            );
            $summary = [
                ...($session->summary ?? []),
                'events' => $session->events()->count(),
                'completed_lesson_id' => $session->lesson_id,
            ];
            $session->update(['status' => 'completed', 'completed_at' => now('UTC'), 'summary' => $summary]);

            if ($session->enrollment && ! $session->enrollment->course->lessons()
                ->where('status', 'published')
                ->whereDoesntHave('progress', fn ($query) => $query->where('user_id', $user->id))
                ->exists()) {
                $session->enrollment->update(['status' => 'completed', 'completed_at' => now('UTC')]);
            }

            return $session->refresh();
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
