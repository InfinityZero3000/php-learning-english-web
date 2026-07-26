<?php

namespace Tests\Feature;

use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_legacy_registration_and_verification_posts_are_retired(): void
    {
        $this->post('/register')->assertMethodNotAllowed();
        $this->post('/verify-email/resend')->assertNotFound();
    }
}
