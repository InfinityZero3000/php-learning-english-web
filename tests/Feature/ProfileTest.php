<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create(['name' => 'Nguyen Van A']);

        $response = $this->actingAs($user)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('Nguyen Van A');
    }

    public function test_guest_cannot_view_profile(): void
    {
        $response = $this->get(route('profile'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect(route('profile'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'old-password123',
            'new_password' => 'new-password123',
        ]);

        $response->assertRedirect(route('profile'));
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_password_change_rejected_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'wrong-password',
            'new_password' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }

    public function test_new_password_required_when_current_password_given(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password123')]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'old-password123',
        ]);

        $response->assertSessionHasErrors('new_password');
        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }
}
