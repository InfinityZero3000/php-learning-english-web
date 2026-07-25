<?php

namespace App\Policies;

use App\Models\User;

class ProgressPolicy
{
    public function viewUser(User $user, int $userId): bool
    {
        return $user->id === $userId || $user->role?->slug === 'admin';
    }
}
