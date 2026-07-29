<?php

namespace App\Policies;

use App\Models\User;

class TopicPolicy
{
    public function manage(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
