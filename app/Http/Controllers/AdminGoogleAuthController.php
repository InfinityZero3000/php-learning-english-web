<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\AdminGoogleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AdminGoogleAuthController extends Controller
{
    public function redirect(Request $request, AdminGoogleAccess $access): RedirectResponse
    {
        $this->clearAdminState($request);
        if (! $access->validConfiguration()) {
            return $this->loginRedirect('configuration');
        }

        $request->session()->put('google_admin_oauth_mode', 'login');

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, AdminGoogleAccess $access): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
            $email = strtolower(trim((string) $google->getEmail()));
            $subject = trim((string) $google->getId());
            $verified = filter_var(data_get($google->user, 'email_verified'), FILTER_VALIDATE_BOOL);
            $role = $access->role($email);

            if (! $verified || $subject === '' || ! $role) {
                $this->clearAdminState($request);

                return $this->loginRedirect('denied');
            }

            $user = DB::transaction(function () use ($email, $google, $subject, $role): User {
                $user = User::query()->where('email', $email)->lockForUpdate()->first();
                $subjectOwner = User::query()->where('google_id', $subject)->lockForUpdate()->first();

                if (($user?->google_id && $user->google_id !== $subject)
                    || ($subjectOwner && $subjectOwner->id !== $user?->id)
                    || ($user?->auth_provider && $user->auth_provider !== 'google')) {
                    abort(403);
                }

                $roleId = Role::query()->where('slug', $role)->value('id');
                abort_unless($roleId, 503);

                $user ??= new User([
                    'email' => $email,
                    'password' => Str::password(40),
                ]);
                $user->fill([
                    'name' => $google->getName() ?: $email,
                    'google_id' => $subject,
                    'auth_provider' => 'google',
                    'role_id' => $roleId,
                ]);
                $user->email_verified_at ??= now();
                $user->save();

                return $user;
            });

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $request->session()->put('google_admin', [
                'user_id' => $user->id,
                'subject' => $subject,
                'email' => $email,
            ]);

            return redirect()->away(rtrim((string) config('app.admin_frontend_url'), '/').'/dashboard');
        } catch (Throwable $exception) {
            report($exception);
            $this->clearAdminState($request);

            return $this->loginRedirect('denied');
        }
    }

    public function reauthenticate(Request $request): RedirectResponse
    {
        $return = $request->string('return')->toString();
        $request->session()->forget('google_admin_reauthenticated_at');
        $request->session()->put(
            'google_admin_reauth_return',
            in_array($return, ['/operations', '/roles', '/users'], true) ? $return : '/dashboard',
        );
        $request->session()->put('google_admin_oauth_mode', 'reauthenticate');

        return Socialite::driver('google')->with([
            'prompt' => 'select_account',
            'max_age' => 0,
        ])->redirect();
    }

    public function reauthenticateCallback(Request $request, AdminGoogleAccess $access): RedirectResponse
    {
        try {
            $user = $request->user();
            $marker = $request->session()->get('google_admin');
            $google = Socialite::driver('google')->user();
            $email = strtolower(trim((string) $google->getEmail()));
            $subject = trim((string) $google->getId());
            $verified = filter_var(data_get($google->user, 'email_verified'), FILTER_VALIDATE_BOOL);

            abort_unless($user && is_array($marker) && $verified, 403);
            abort_unless($access->role($email) === $user->role?->slug, 403);
            abort_unless((int) ($marker['user_id'] ?? 0) === $user->id, 403);
            abort_unless(hash_equals((string) $user->google_id, $subject), 403);
            abort_unless(hash_equals(strtolower($user->email), $email), 403);

            $request->session()->put('google_admin_reauthenticated_at', now()->timestamp);
            $return = $request->session()->pull('google_admin_reauth_return', '/dashboard');

            return redirect()->away(rtrim((string) config('app.admin_frontend_url'), '/').$return);
        } catch (Throwable $exception) {
            report($exception);
            $request->session()->forget(['google_admin_reauthenticated_at', 'google_admin_reauth_return']);

            return $this->loginRedirect('denied');
        }
    }

    private function clearAdminState(Request $request): void
    {
        Auth::logout();
        $request->session()->forget(['google_admin', 'google_admin_reauthenticated_at', 'google_admin_oauth_mode']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function loginRedirect(string $error): RedirectResponse
    {
        return redirect()->away(rtrim((string) config('app.admin_frontend_url'), '/')."/login?error={$error}");
    }
}
