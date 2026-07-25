<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_profile_password_and_delete_account(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->actingAs($user);

        $this->putJson('/api/v1/profile', ['name' => 'Updated'])
            ->assertOk()->assertJsonPath('data.name', 'Updated');
        $this->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertNoContent();
        $this->deleteJson('/api/v1/profile', ['password' => 'new-password'])
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
