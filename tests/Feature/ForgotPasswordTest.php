<?php

namespace Tests\Feature;

use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    public function test_legacy_password_posts_are_retired(): void
    {
        $this->post('/forgot-password')->assertMethodNotAllowed();
        $this->post('/reset-password')->assertNotFound();
    }
}
