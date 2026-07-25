<?php

namespace App\Policies;

use App\Models\Attempt;
use App\Models\User;

class AttemptPolicy
{
    public function view(User $user, Attempt $attempt): bool
    {
        return $user->id === $attempt->user_id;
    }

    public function update(User $user, Attempt $attempt): bool
    {
        return $this->view($user, $attempt);
    }

    public function finish(User $user, Attempt $attempt): bool
    {
        return $this->view($user, $attempt);
    }
}
