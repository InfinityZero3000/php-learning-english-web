<?php

namespace App\Policies;

use App\Models\User;

class OperationsPolicy
{
    public function manageContent(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin');
    }

    public function manageOperations(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function manageUsers(User $user): bool
    {
        return $this->manageContent($user);
    }

    public function manageRoles(User $user): bool
    {
        return $this->manageOperations($user);
    }

    public function assignTeachers(User $user): bool
    {
        return $this->manageOperations($user);
    }

    public function startContentSync(User $user): bool
    {
        return $this->manageContent($user);
    }

    public function retryContentSync(User $user): bool
    {
        return $this->manageOperations($user);
    }

    public function applyContentImport(User $user): bool
    {
        return $this->manageOperations($user);
    }
}
