<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_legacy_login_page_redirects_to_learner_frontend(): void
    {
        config(['app.frontend_url' => 'https://learner.test']);

        $this->get('/login')->assertRedirect('https://learner.test/login');
    }

    public function test_legacy_login_post_is_retired(): void
    {
        $this->post('/login')->assertMethodNotAllowed();
    }
}
