<?php

namespace Tests\Feature;

use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    public function test_password_ui_lives_in_nextjs_and_legacy_mutations_are_retired(): void
    {
        config(['app.frontend_url' => 'https://learner.test']);

        $this->get('/forgot-password')->assertRedirect('https://learner.test/forgot-password');
        $this->get('/reset-password/token?email=a%40example.com')
            ->assertRedirect('https://learner.test/reset-password?token=token&email=a%40example.com');
        $this->assertContains($this->post('/forgot-password')->getStatusCode(), [404, 405]);
        $this->assertContains($this->post('/reset-password')->getStatusCode(), [404, 405]);
    }
}
