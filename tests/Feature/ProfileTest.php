<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_ui_lives_in_nextjs_and_web_mutations_are_retired(): void
    {
        config(['app.frontend_url' => 'https://learner.test']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertRedirect('https://learner.test/profile');
        $this->actingAs($user)->put('/profile')->assertMethodNotAllowed();
        $this->actingAs($user)->delete('/profile')->assertMethodNotAllowed();
    }

    public function test_guest_profile_still_uses_the_named_login_redirect(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
    }
}
