<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\LearningPolicy;
use App\Policies\OperationsPolicy;
use App\Policies\UserPolicy;
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

        Gate::policy(User::class, UserPolicy::class);
        Gate::define('is-admin', fn (User $user) => $user->hasRole('admin', 'super_admin'));
        Gate::define('manage-content', [OperationsPolicy::class, 'manageContent']);
        Gate::define('manage-operations', [OperationsPolicy::class, 'manageOperations']);
        Gate::define('manage-users', [OperationsPolicy::class, 'manageUsers']);
        Gate::define('manage-roles', [OperationsPolicy::class, 'manageRoles']);
        Gate::define('assign-teachers', [OperationsPolicy::class, 'assignTeachers']);
        Gate::define('start-content-sync', [OperationsPolicy::class, 'startContentSync']);
        Gate::define('retry-content-sync', [OperationsPolicy::class, 'retryContentSync']);
        Gate::define('apply-content-import', [OperationsPolicy::class, 'applyContentImport']);
        Gate::define('view-learning-evidence', [LearningPolicy::class, 'viewEvidence']);
        Gate::define('upload-media', fn (User $user) => $user->hasRole('admin', 'super_admin'));
        Gate::define('delete-media', fn (User $user) => $user->hasRole('admin', 'super_admin'));
    }
}
