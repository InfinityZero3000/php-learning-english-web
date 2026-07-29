<?php

namespace App\Console\Commands;

use App\Models\OperationsAudit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PromoteSuperAdmin extends Command
{
    protected $signature = 'user:promote-super-admin {email}';

    protected $description = 'Promote an existing user to super admin';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        if ($user->hasRole('super_admin')) {
            $this->info('User is already a super admin.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Promote {$user->email} to super admin?")) {
            $this->warn('Promotion cancelled.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user): void {
            $role = Role::query()->where('slug', 'super_admin')->lockForUpdate()->firstOrFail();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = $lockedUser->role?->slug;
            $lockedUser->update(['role_id' => $role->id]);

            OperationsAudit::create([
                'action' => 'user.promoted_super_admin',
                'target_type' => User::class,
                'target_id' => (string) $lockedUser->id,
                'context' => ['source' => 'artisan'],
                'before_state' => ['role' => $before],
                'after_state' => ['role' => 'super_admin'],
                'occurred_at' => now(),
            ]);
        });

        $this->info('User promoted to super admin.');

        return self::SUCCESS;
    }
}
