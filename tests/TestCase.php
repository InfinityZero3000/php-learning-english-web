<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $admin = method_exists($user, 'hasRole') && $user->hasRole('admin', 'super_admin');
        if ($admin) {
            $email = strtolower((string) $user->email);
            $role = $user->hasRole('super_admin') ? 'super_admin_emails' : 'admin_emails';
            config()->set("admin_access.{$role}", [$email]);
            config()->set('admin_access.'.($role === 'admin_emails' ? 'super_admin_emails' : 'admin_emails'), []);
            $user->forceFill(['google_id' => 'test-google-'.$user->getAuthIdentifier(), 'auth_provider' => 'google'])->save();
        }

        $result = parent::actingAs($user, $guard);
        if ($admin) {
            $this->startSession();
            $session = $this->app['session']->driver();
            $session->invalidate();
            $session->put('google_admin', [
                'user_id' => $user->getAuthIdentifier(),
                'subject' => $user->google_id,
                'email' => $email,
            ]);
            $session->put('google_admin_reauthenticated_at', now()->timestamp);
            $session->put('google_admin_reauthenticated', [
                'user_id' => $user->getAuthIdentifier(),
                'subject' => $user->google_id,
                'session_id' => $session->getId(),
                'at' => now()->timestamp,
            ]);
            $this->withCookie((string) config('session.cookie'), $session->getId());
            $this->withCredentials();
        }

        return $result;
    }
}
