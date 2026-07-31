<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\AdminGoogleAuthController;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminGoogleAccess;
use App\Support\SafeFrontendPath;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class OAuthController extends Controller
{
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->validateProvider($provider);
        $request->session()->put('auth.oauth_next', SafeFrontendPath::normalize($request->query('next')));

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        if ($provider === 'google' && $request->session()->pull('google_admin_oauth_mode') === 'login') {
            return app(AdminGoogleAuthController::class)->callback($request, app(AdminGoogleAccess::class));
        }

        $next = SafeFrontendPath::normalize($request->session()->pull('auth.oauth_next'));

        if ($request->query('error')) {
            return $this->failure('cancelled');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
            $email = $socialUser->getEmail();
            if (! $email) {
                return $this->failure('email_missing');
            }

            $user = User::query()->where('email', $email)->first();
            if ($user && $user->role?->slug !== 'learner') {
                return $this->failure('role_conflict');
            }
            if ($user?->locked_at) {
                return $this->failure('locked');
            }

            if (! $user) {
                $roleId = Role::query()->where('slug', 'learner')->value('id');
                if (! $roleId) {
                    throw new \RuntimeException('Learner role is not configured');
                }
                try {
                    $user = User::create([
                        'role_id' => $roleId,
                        'name' => $socialUser->getName() ?: $email,
                        'email' => $email,
                        'password' => Str::password(32),
                    ]);
                } catch (QueryException $exception) {
                    $user = User::query()->where('email', $email)->first();
                    if (! $user) {
                        throw $exception;
                    }
                }
                if ($user->role?->slug !== 'learner') {
                    return $this->failure('role_conflict');
                }
            }
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->away($this->frontend('/auth/callback?'.http_build_query(['next' => $next])));
        } catch (InvalidStateException $exception) {
            Log::warning('OAuth callback failed', ['provider' => $provider, 'exception' => $exception::class]);

            return $this->failure('invalid_state');
        } catch (Throwable $exception) {
            Log::warning('OAuth callback failed', ['provider' => $provider, 'exception' => $exception::class]);

            return $this->failure('provider_failed');
        }
    }

    private function validateProvider(string $provider): void
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);
    }

    private function failure(string $code): RedirectResponse
    {
        return redirect()->away($this->frontend('/login?'.http_build_query(['oauth_error' => $code])));
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
