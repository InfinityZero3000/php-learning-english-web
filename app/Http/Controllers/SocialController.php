<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\AdminGoogleAccess;
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

    public function google(Request $request)
    {
        $next = $request->string('next')->toString();
        $request->session()->put('google_oauth_return', $this->safeReturnPath($next));

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $request)
    {
        $adminMode = $request->session()->pull('google_admin_oauth_mode');
        if ($adminMode === 'login') {
            return app(AdminGoogleAuthController::class)->callback($request, app(AdminGoogleAccess::class));
        }
        $googleUser = Socialite::driver('google')->user();
        $user = $this->loginSocialUser(
            $googleUser->getEmail(),
            $googleUser->getName(),
        );
        Auth::login($user);
        $request->session()->regenerate();

        return redirect($request->session()->pull('google_oauth_return', '/profile'));
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

    private function safeReturnPath(string $path): string
    {
        return $path !== '' && strlen($path) <= 256 && str_starts_with($path, '/') && ! str_starts_with($path, '//')
            ? $path
            : '/profile';
    }
}
