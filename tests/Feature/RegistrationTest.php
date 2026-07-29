<?php

namespace Tests\Feature;

use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_registration_ui_lives_in_nextjs_and_legacy_mutation_is_retired(): void
    {
        config(['app.frontend_url' => 'https://learner.test']);

        $this->get('/register')->assertRedirect('https://learner.test/register');
        $this->post('/register')->assertMethodNotAllowed();
    }
}
