<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        VerifyEmail::createUrlUsing(fn ($user) => URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        ));

        ResetPassword::createUrlUsing(fn ($user, string $token) => sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim(config('app.frontend_url'), '/'),
            urlencode($token),
            urlencode($user->getEmailForPasswordReset()),
        ));

        // Gate cho phép dùng @can('is-admin') trong Blade
        Gate::define('is-admin', function ($user) {
            return $user->role && $user->role->slug === 'admin';
        });
    }
}
