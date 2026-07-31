<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecentGoogleAdmin
{
    public function require(Request $request): void
    {
        $user = $request->user();
        $fresh = $request->session()->get('google_admin_reauthenticated');
        $confirmedAt = (int) ($fresh['at'] ?? 0);
        if ($user && is_array($fresh)
            && (int) ($fresh['user_id'] ?? 0) === $user->id
            && hash_equals((string) $user->google_id, (string) ($fresh['subject'] ?? ''))
            && hash_equals($request->session()->getId(), (string) ($fresh['session_id'] ?? ''))
            && $confirmedAt <= now()->timestamp
            && now()->timestamp - $confirmedAt <= 900) {
            return;
        }

        $request->session()->forget('google_admin_reauthenticated');
        throw new HttpException(428, 'Recent Google verification is required.');
    }
}
