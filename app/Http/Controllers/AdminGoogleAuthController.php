<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\AdminGoogleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AdminGoogleAuthController extends Controller
{
    public function entry(Request $request): RedirectResponse
    {
        $return = $this->safeReturn($request->string('return')->toString());
        $challenge = (string) Str::uuid();
        Cache::put("admin-google-challenge:{$challenge}", [
            'return' => $return,
            'subject' => $request->user()?->google_id,
        ], now()->addMinutes(5));

        return redirect()->away(
            rtrim((string) config('app.admin_frontend_url'), '/')."/api/v1/auth/oauth/google/admin/start?challenge={$challenge}"
        );
    }

    public function redirect(Request $request, AdminGoogleAccess $access): RedirectResponse
    {
        $this->clearAdminState($request);
        $challenge = $request->string('challenge')->toString();
        if (! $access->validConfiguration() || ! Str::isUuid($challenge)
            || ! Cache::has("admin-google-challenge:{$challenge}")) {
            return $this->loginRedirect('configuration');
        }
        $request->session()->put('google_admin_oauth_mode', 'login');
        $request->session()->put('google_admin_challenge', $challenge);

        return Socialite::driver('google')
            ->redirectUrl($this->adminCallbackUrl())
            ->with(['prompt' => 'select_account', 'max_age' => 0])
            ->redirect();
    }

    public function callback(Request $request, AdminGoogleAccess $access): RedirectResponse
    {
        try {
            // Google's token exchange re-checks redirect_uri against the one used for
            // the authorization request above; a mismatch here fails with
            // redirect_uri_mismatch even though the console URI is registered and the
            // authorize step already succeeded, since this is a separate Socialite
            // instance for a separate HTTP request that otherwise defaults back to
            // config('services.google.redirect') — the learner flow's fixed URI.
            $google = Socialite::driver('google')->redirectUrl($this->adminCallbackUrl())->user();
            $email = strtolower(trim((string) $google->getEmail()));
            $subject = trim((string) $google->getId());
            $verified = filter_var(data_get($google->user, 'email_verified'), FILTER_VALIDATE_BOOL);
            $role = $access->role($email);
            $challenge = $request->session()->pull('google_admin_challenge');
            $expected = is_string($challenge) ? Cache::get("admin-google-challenge:{$challenge}") : null;

            abort_unless($verified && $subject !== '' && $role && is_array($expected), 403);
            if ($expected['subject'] ?? null) {
                abort_unless(hash_equals((string) $expected['subject'], $subject), 403);
            }
            $roleId = Role::query()->where('slug', $role)->value('id');
            if (! $roleId) {
                Cache::forget("admin-google-challenge:{$challenge}");
                $this->clearAdminState($request);

                return $this->loginRedirect('configuration');
            }

            $user = DB::transaction(function () use ($email, $google, $subject, $roleId): User {
                $user = User::query()->where('email', $email)->lockForUpdate()->first();
                $subjectOwner = User::query()->where('google_id', $subject)->lockForUpdate()->first();
                abort_if(($user?->google_id && $user->google_id !== $subject)
                    || ($subjectOwner && $subjectOwner->id !== $user?->id)
                    || ($user?->auth_provider && $user->auth_provider !== 'google'), 403);
                $user ??= new User(['email' => $email, 'password' => Str::password(40)]);
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
            abort_if($user->locked_at, 423, 'Account is locked.');

            Cache::forget("admin-google-challenge:{$challenge}");
            $nonce = (string) Str::uuid();
            Cache::put("admin-google-handoff:{$nonce}", true, now()->addMinutes(2));
            $handoff = Crypt::encryptString(json_encode([
                'nonce' => $nonce,
                'user_id' => $user->id,
                'subject' => $subject,
                'email' => $email,
                'return' => $expected['return'] ?? '/dashboard',
                'expires_at' => now()->addMinutes(2)->timestamp,
            ], JSON_THROW_ON_ERROR));

            return redirect()->away(
                rtrim((string) config('app.admin_frontend_url'), '/').'/login?handoff='.urlencode($handoff)
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->clearAdminState($request);

            return $this->loginRedirect('denied');
        }
    }

    public function handoff(Request $request, AdminGoogleAccess $access): JsonResponse
    {
        $data = $request->validate(['handoff' => ['required', 'string', 'max:4096']]);
        try {
            $payload = json_decode(Crypt::decryptString($data['handoff']), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(401, 'Invalid or expired Google handoff.');
        }
        abort_unless(is_array($payload) && (int) ($payload['expires_at'] ?? 0) >= now()->timestamp, 401);
        $nonce = (string) ($payload['nonce'] ?? '');
        abort_unless($nonce !== '' && Cache::pull("admin-google-handoff:{$nonce}"), 401);
        $user = User::query()->with('role')->findOrFail((int) ($payload['user_id'] ?? 0));
        abort_if($user->locked_at, 423, 'Account is locked.');
        abort_unless(hash_equals((string) $user->google_id, (string) ($payload['subject'] ?? '')), 401);
        abort_unless(hash_equals(strtolower($user->email), (string) ($payload['email'] ?? '')), 401);
        abort_unless($access->role($user->email) === $user->role?->slug, 403);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put('google_admin', [
            'user_id' => $user->id,
            'subject' => $user->google_id,
            'email' => strtolower($user->email),
        ]);
        $request->session()->put('google_admin_reauthenticated_at', now()->timestamp);
        $request->session()->put('google_admin_reauthenticated', [
            'user_id' => $user->id,
            'subject' => $user->google_id,
            'session_id' => $request->session()->getId(),
            'at' => now()->timestamp,
        ]);

        return response()->json(['data' => [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->slug],
            'return' => $payload['return'] ?? '/dashboard',
        ]]);
    }

    private function clearAdminState(Request $request): void
    {
        Auth::logout();
        $request->session()->forget([
            'google_admin', 'google_admin_reauthenticated_at',
            'google_admin_reauthenticated', 'google_admin_oauth_mode',
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function loginRedirect(string $error): RedirectResponse
    {
        return redirect()->away(rtrim((string) config('app.admin_frontend_url'), '/')."/login?error={$error}");
    }

    /**
     * Must be identical on both the authorize (redirect()) and token-exchange
     * (callback()) Socialite calls, or Google's token endpoint rejects the
     * exchange with redirect_uri_mismatch even though the console URI matches.
     */
    private function adminCallbackUrl(): string
    {
        return rtrim((string) config('app.admin_frontend_url'), '/').'/api/v1/auth/oauth/google/admin/callback';
    }

    private function safeReturn(string $return): string
    {
        if (in_array($return, ['/operations', '/roles', '/users', '/import'], true)) {
            return $return;
        }

        parse_str((string) parse_url($return, PHP_URL_QUERY), $query);

        return parse_url($return, PHP_URL_PATH) === '/import'
            && ctype_digit((string) ($query['run'] ?? ''))
            && count($query) === 1
                ? '/import?run='.$query['run']
                : '/dashboard';
    }
}
