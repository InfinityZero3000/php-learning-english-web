<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_legacy_login_post_is_retired(): void
    {
        $this->post('/login')->assertMethodNotAllowed();
    }
}
