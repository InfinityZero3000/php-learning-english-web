<?php

namespace Tests\Feature;

use App\Models\OperationsAudit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoteSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_command_promotes_existing_user_and_audits_it(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'role_id' => Role::where('slug', 'learner')->value('id'),
        ]);

        $this->artisan('user:promote-super-admin', ['email' => $user->email])
            ->expectsConfirmation("Promote {$user->email} to super admin?", 'yes')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole('super_admin'));
        $this->assertDatabaseHas('operations_audits', [
            'action' => 'user.promoted_super_admin',
            'target_type' => User::class,
            'target_id' => (string) $user->id,
        ]);
        $this->assertNull(OperationsAudit::firstOrFail()->actor_id);
    }

    public function test_cancelled_command_makes_no_change(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'role_id' => Role::where('slug', 'learner')->value('id'),
        ]);

        $this->artisan('user:promote-super-admin', ['email' => $user->email])
            ->expectsConfirmation("Promote {$user->email} to super admin?", 'no')
            ->assertFailed();

        $this->assertTrue($user->fresh()->hasRole('learner'));
        $this->assertDatabaseCount('operations_audits', 0);
    }
}
