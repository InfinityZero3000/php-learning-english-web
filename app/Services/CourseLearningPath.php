<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Lesson;
use App\Models\Progress;
use App\Models\Unit;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CourseLearningPath
{
    public function path(User $user, Course $course): array
    {
        abort_unless($course->status === 'published', 404);
        $enrollment = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
        $completedIds = $this->completedIds($user, $course);
        $units = Unit::query()
            ->where('course_id', $course->id)
            ->where('status', 'published')
            ->with(['lessons' => fn ($query) => $query
                ->where('status', 'published')
                ->with('prerequisites')
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $legacyLessons = Lesson::query()
            ->where('course_id', $course->id)
            ->whereNull('unit_id')
            ->where('status', 'published')
            ->with('prerequisites')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $next = $enrollment?->status === 'active'
            ? $this->nextEligibleLesson($user, $enrollment)
            : null;
        $active = $enrollment?->status === 'active'
            ? LearningSession::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->first()
            : null;
        $candidateIds = $this->candidates($course)->pluck('id');
        $total = $candidateIds->count();
        $completed = $candidateIds->intersect($completedIds)->count();
        $unitPayloads = $units->map(fn (Unit $unit) => [
            'id' => $unit->id,
            'title' => $unit->title,
            'description' => $unit->description,
            'sort_order' => $unit->sort_order,
            'icon_url' => $unit->icon_url,
            'background_color' => $unit->background_color,
            'completed_lessons' => $unit->lessons->whereIn('id', $completedIds)->count(),
            'total_lessons' => $unit->lessons->count(),
            'lessons' => $unit->lessons->map(
                fn (Lesson $lesson) => $this->lessonPayload($lesson, $completedIds, $next?->id),
            )->all(),
        ]);
        if ($legacyLessons->isNotEmpty()) {
            $unitPayloads->prepend([
                'id' => null,
                'title' => 'General',
                'description' => 'Lessons not assigned to a unit yet.',
                'sort_order' => -1,
                'icon_url' => null,
                'background_color' => null,
                'completed_lessons' => $legacyLessons->whereIn('id', $completedIds)->count(),
                'total_lessons' => $legacyLessons->count(),
                'lessons' => $legacyLessons->map(
                    fn (Lesson $lesson) => $this->lessonPayload($lesson, $completedIds, $next?->id),
                )->all(),
            ]);
        }

        return [
            'type' => 'course_learning_path',
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'description' => $course->description,
                'thumbnail_url' => $course->thumbnail_url,
                'estimated_duration' => $course->estimated_duration,
                'total_xp' => $course->total_xp,
            ],
            'enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
            ] : null,
            'progress' => [
                'completed_lessons' => $completed,
                'total_lessons' => $total,
                'percentage' => $total ? (int) round($completed / $total * 100) : 0,
            ],
            'units' => $unitPayloads->values()->all(),
            'next_action' => $active ? [
                'type' => 'resume_session',
                'session_id' => $active->id,
                'lesson_id' => $active->lesson_id,
            ] : ($next ? [
                'type' => 'start_lesson',
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $next->id,
            ] : null),
        ];
    }

    public function nextEligibleLesson(User $user, Enrollment $enrollment): ?Lesson
    {
        if (! $this->isAvailable($enrollment)) {
            return null;
        }
        $completedIds = $this->completedIds($user, $enrollment->course);

        return $this->candidates($enrollment->course)
            ->first(fn (Lesson $lesson) => ! $completedIds->contains($lesson->id)
                && $lesson->prerequisites->every(fn (Lesson $required) => $completedIds->contains($required->id)));
    }

    public function eligibleLesson(User $user, Enrollment $enrollment, int $lessonId): Lesson
    {
        if (! $this->isAvailable($enrollment)) {
            throw new ConflictHttpException('The course is not available for a new session.');
        }
        $lesson = $this->candidates($enrollment->course)->firstWhere('id', $lessonId);
        $completedIds = $this->completedIds($user, $enrollment->course);
        if (! $lesson || $completedIds->contains($lesson->id)
            || $lesson->prerequisites->contains(fn (Lesson $required) => ! $completedIds->contains($required->id))) {
            throw new ConflictHttpException('The selected lesson is not currently eligible.');
        }

        return $lesson;
    }

    public function hasIncompleteLessons(User $user, Enrollment $enrollment): bool
    {
        $completedIds = $this->completedIds($user, $enrollment->course);

        return $this->candidates($enrollment->course)
            ->contains(fn (Lesson $lesson) => ! $completedIds->contains($lesson->id));
    }

    public function candidates(Course $course)
    {
        return Lesson::query()
            ->select('lessons.*')
            ->leftJoin('units', 'units.id', '=', 'lessons.unit_id')
            ->where('lessons.course_id', $course->id)
            ->where('lessons.status', 'published')
            ->where(fn ($query) => $query->whereNull('lessons.unit_id')->orWhere('units.status', 'published'))
            ->with('prerequisites')
            ->orderByRaw('COALESCE(units.sort_order, -1)')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->get();
    }

    private function completedIds(User $user, Course $course)
    {
        return Progress::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->whereIn('lesson_id', $course->lessons()->select('id'))
            ->pluck('lesson_id');
    }

    private function isAvailable(Enrollment $enrollment): bool
    {
        return $enrollment->status === 'active' && $enrollment->course->status === 'published';
    }

    private function lessonPayload(Lesson $lesson, $completedIds, ?int $nextId): array
    {
        $completed = $completedIds->contains($lesson->id);
        $missing = $lesson->prerequisites
            ->reject(fn (Lesson $required) => $completedIds->contains($required->id));

        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'sort_order' => $lesson->sort_order,
            'estimated_minutes' => $lesson->estimated_minutes,
            'xp_reward' => $lesson->xp_reward,
            'status' => $completed ? 'completed' : ($missing->isEmpty() ? 'available' : 'locked'),
            'is_current' => ! $completed && $missing->isEmpty() && $lesson->id === $nextId,
            'prerequisites' => $lesson->prerequisites->map(fn (Lesson $required) => [
                'id' => $required->id,
                'title' => $required->title,
                'completed' => $completedIds->contains($required->id),
            ])->all(),
            'locked_reason' => $missing->isEmpty()
                ? null
                : 'Complete '.implode(', ', $missing->pluck('title')->all()).' first.',
        ];
    }
}
