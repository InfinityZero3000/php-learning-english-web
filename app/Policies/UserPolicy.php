<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin');
    }

    public function updateRole(User $actor, User $target, Role $role): bool
    {
        if (! $actor->hasRole('super_admin')) {
            return false;
        }

        if ($actor->is($target) && $target->role_id !== $role->id) {
            return false;
        }

        return ! ($target->hasRole('super_admin')
            && $role->slug !== 'super_admin'
            && User::query()->whereHas('role', fn ($query) => $query->where('slug', 'super_admin'))->count() <= 1);
    }
}
