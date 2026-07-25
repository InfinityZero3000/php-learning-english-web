<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Google Login
    |--------------------------------------------------------------------------
    */

    public function google()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $request)
    {
        $googleUser = Socialite::driver('google')->user();
        $user = $this->loginSocialUser(
            $googleUser->getEmail(),
            $googleUser->getName(),
        );
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/profile');
    }

    /*
    |--------------------------------------------------------------------------
    | Facebook Login
    |--------------------------------------------------------------------------
    */

    public function facebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function facebookCallback(Request $request)
    {
        $facebookUser = Socialite::driver('facebook')->user();
        $user = $this->loginSocialUser(
            $facebookUser->getEmail(),
            $facebookUser->getName(),
        );
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/profile');
    }

    private function loginSocialUser(?string $email, ?string $name): User
    {
        if (! $email) {
            throw ValidationException::withMessages([
                'email' => 'Nhà cung cấp đăng nhập không trả về email.',
            ]);
        }

        $roleId = Role::query()->where('slug', 'learner')->value('id');
        if (! $roleId) {
            throw ValidationException::withMessages([
                'role' => 'Hệ thống chưa được cấu hình vai trò người học.',
            ]);
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'role_id' => $roleId,
                'name' => $name ?: $email,
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $user;
    }
}
