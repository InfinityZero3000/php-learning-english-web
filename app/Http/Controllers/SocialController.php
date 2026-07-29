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
        try {
            $googleUser = Socialite::driver('google')->user();
            if (! $googleUser->getEmail()) {
                return $this->oauthError('oauth_email_missing');
            }
            $user = $this->loginSocialUser($googleUser->getEmail(), $googleUser->getName());
        } catch (\Throwable) {
            return $this->oauthError('oauth_failed');
        }
        Auth::login($user);
        $request->session()->regenerate();

        $path = $request->session()->pull('google_oauth_return', '/profile');

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').$path);
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
        try {
            $facebookUser = Socialite::driver('facebook')->user();
            if (! $facebookUser->getEmail()) {
                return $this->oauthError('oauth_email_missing');
            }
            $user = $this->loginSocialUser($facebookUser->getEmail(), $facebookUser->getName());
        } catch (\Throwable) {
            return $this->oauthError('oauth_failed');
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/profile');
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

    private function oauthError(string $code)
    {
        $safeCode = in_array($code, ['oauth_cancelled', 'oauth_email_missing', 'oauth_failed'], true)
            ? $code : 'oauth_failed';

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/login?error='.$safeCode);
    }
}
