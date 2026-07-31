<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\AdminGoogleAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequireGoogleAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $marker = $request->session()->get('google_admin');
        $role = app(AdminGoogleAccess::class)->role($user?->email);
        $identityMatches = $user && is_array($marker)
            && (int) ($marker['user_id'] ?? 0) === $user->id
            && hash_equals((string) $user->google_id, (string) ($marker['subject'] ?? ''))
            && hash_equals(strtolower($user->email), (string) ($marker['email'] ?? ''));

        if ($user && ! $identityMatches && ! $user->hasRole('admin', 'super_admin')) {
            return response()->json(['message' => 'Administrator access is required.'], 403);
        }

        if (! $identityMatches || ! $role) {
            if ($user && $user->hasRole('admin', 'super_admin')) {
                DB::transaction(function () use ($user): void {
                    $locked = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
                    $locked->forceFill(['role_id' => Role::query()->where('slug', 'learner')->value('id')])->save();
                });
            }
            $request->session()->forget([
                'google_admin', 'google_admin_reauthenticated_at', 'google_admin_reauthenticated',
            ]);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Google administrator authentication is required.'], 401)
                : redirect()->away(rtrim((string) config('app.admin_frontend_url'), '/').'/login?error=expired');
        }

        $user = DB::transaction(function () use ($user, $role) {
            $locked = $user->newQuery()->with('role')->lockForUpdate()->findOrFail($user->id);
            $roleId = Role::query()->where('slug', $role)->lockForUpdate()->value('id');
            abort_unless($roleId, 503);
            if ($locked->role_id !== $roleId) {
                $locked->forceFill(['role_id' => $roleId])->save();
                $locked->load('role');
            }

            return $locked;
        });
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
