<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;

class RecentPassword
{
    public function require(Request $request): void
    {
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
