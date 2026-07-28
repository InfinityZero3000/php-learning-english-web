<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('admin', 'super_admin')) {
            $email = strtolower((string) $user->email);
            $role = $user->hasRole('super_admin') ? 'super_admin_emails' : 'admin_emails';
            config()->set("admin_access.{$role}", [$email]);
            config()->set('admin_access.'.($role === 'admin_emails' ? 'super_admin_emails' : 'admin_emails'), []);
            $user->forceFill(['google_id' => 'test-google-'.$user->getAuthIdentifier(), 'auth_provider' => 'google'])->save();
            $this->withSession(['google_admin' => [
                'user_id' => $user->getAuthIdentifier(),
                'subject' => $user->google_id,
                'email' => $email,
            ], 'google_admin_reauthenticated_at' => now()->timestamp]);
        }

        return parent::actingAs($user, $guard);
    }
}
