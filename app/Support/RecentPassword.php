<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecentPassword
{
    public function require(Request $request): void
    {
        if ($request->session()->has('google_admin')) {
            $confirmedAt = (int) $request->session()->get('google_admin_reauthenticated_at', 0);
            if (Date::now()->timestamp - $confirmedAt <= 900) {
                return;
            }

            throw new HttpException(428, 'Recent Google verification is required.');
        }

        $confirmedAt = $request->session()->get('auth.password_confirmed_at', 0);

        if (Date::now()->timestamp - $confirmedAt <= (int) config('auth.password_timeout')) {
            return;
        }

        Validator::make($request->all(), [
            'password' => ['required', 'current_password'],
        ])->validate();

        $request->session()->passwordConfirmed();
    }
}
