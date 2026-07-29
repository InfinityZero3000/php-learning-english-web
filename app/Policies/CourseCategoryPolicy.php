<?php

namespace App\Policies;

use App\Models\User;

class CourseCategoryPolicy
{
    public function manage(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
