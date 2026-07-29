<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    public function test_guest_admin_pages_redirect_to_the_admin_frontend_login(): void
    {
        $login = rtrim((string) config('app.admin_frontend_url'), '/').'/login';
        $this->get('/admin')->assertRedirect($login);
        $this->get('/admin/users')->assertRedirect($login);
    }
}
