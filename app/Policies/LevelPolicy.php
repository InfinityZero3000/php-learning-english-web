<?php

namespace App\Policies;

use App\Models\User;

class LevelPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin');
    }
}
