<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Course $course): bool
    {
        return true; // Published courses visible to all
    }

    public function create(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }

    public function update(User $user, Course $course): bool
    {
        return $user->role?->slug === 'admin';
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->role?->slug === 'admin';
    }
}