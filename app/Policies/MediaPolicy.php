<?php

namespace App\Policies;

use App\Models\User;

class MediaPolicy
{
    /**
     * Only admins may upload media.
     */
    public function upload(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin');
    }

    /**
     * Only admins may delete media.
     */
    public function delete(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin');
    }
}
