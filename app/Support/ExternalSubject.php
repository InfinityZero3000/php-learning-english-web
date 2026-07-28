<?php

namespace App\Support;

use App\Models\User;
use RuntimeException;

class ExternalSubject
{
    public function for(User $user): string
    {
        $secret = config('services.lexilingo.subject_hmac_secret');
        if (! is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('LexiLingo subject HMAC secret is not configured.');
        }

        return rtrim(strtr(base64_encode(hash_hmac('sha256', (string) $user->id, $secret, true)), '+/', '-_'), '=');
    }
}
