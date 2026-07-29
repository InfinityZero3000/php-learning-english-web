<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    public function test_guest_admin_pages_redirect_to_the_named_login_route(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/admin/users')->assertRedirect(route('login'));
    }
}
