<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\AdminGoogleAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireGoogleAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin', 'super_admin')) {
            return response()->json(['message' => 'Administrator access is required.'], 403);
        }
        $marker = $request->session()->get('google_admin');
        $role = app(AdminGoogleAccess::class)->role($user?->email);
        $valid = $user && is_array($marker)
            && (int) ($marker['user_id'] ?? 0) === $user->id
            && hash_equals((string) $user->google_id, (string) ($marker['subject'] ?? ''))
            && hash_equals(strtolower($user->email), (string) ($marker['email'] ?? ''))
            && $role;

        if (! $valid) {
            if ($user && $user->hasRole('admin', 'super_admin')) {
                $user->forceFill(['role_id' => Role::query()->where('slug', 'learner')->value('id')])->save();
            }
            $request->session()->forget(['google_admin', 'google_admin_reauthenticated_at']);
            Auth::logout();

            return response()->json(['message' => 'Google administrator authentication is required.'], 401);
        }

        if (! $user->hasRole($role)) {
            $user->forceFill(['role_id' => Role::query()->where('slug', $role)->value('id')])->save();
            $user->load('role');
        }

        return $next($request);
    }
}
