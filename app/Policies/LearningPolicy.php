<?php

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;

class LearningPolicy
{
    public function viewEvidence(User $actor, User $learner): bool
    {
        return $actor->is($learner) || (
            $actor->hasRole('teacher', 'super_admin')
            && TeacherAssignment::query()
                ->where('teacher_id', $actor->id)
                ->where('learner_id', $learner->id)
                ->exists()
        );
    }
}
