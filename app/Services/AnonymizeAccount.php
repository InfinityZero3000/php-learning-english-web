<?php

namespace App\Services;

use App\Models\OperationsAudit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnonymizeAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $superAdminRole = Role::query()->where('slug', 'super_admin')->lockForUpdate()->first();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($superAdminRole
                && $lockedUser->role_id === $superAdminRole->id
                && User::query()->where('role_id', $superAdminRole->id)->count() <= 1) {
                throw ValidationException::withMessages([
                    'password' => 'The final super admin account cannot be deleted.',
                ]);
            }

            $id = $lockedUser->id;
            $email = $lockedUser->email;

            DB::table('intervention_notes')->where('learner_id', $id)->orWhere('teacher_id', $id)->delete();
            DB::table('assignments')->where('learner_id', $id)->orWhere('teacher_id', $id)->delete();
            DB::table('teacher_assignments')->where('learner_id', $id)->orWhere('teacher_id', $id)->delete();
            DB::table('supervision_alerts')->where('learner_id', $id)->where('state', '!=', 'resolved')->delete();
            DB::table('supervision_alerts')->where('learner_id', $id)->where('state', 'resolved')->update([
                'evidence' => json_encode(['anonymized' => true]),
                'resolution_note' => null,
            ]);
            DB::table('supervision_alerts')->where('assignee_id', $id)->update(['assignee_id' => null]);
            DB::table('supervision_alerts')->where('resolved_by', $id)->update(['resolved_by' => null]);
            DB::table('learning_sessions')->where('user_id', $id)->delete();
            DB::table('enrollments')->where('user_id', $id)->delete();
            DB::table('attempts')->where('user_id', $id)->delete();
            DB::table('progress')->where('user_id', $id)->delete();
            DB::table('bookmarks')->where('user_id', $id)->delete();
            DB::table('operations_audits')->where('actor_id', $id)->update(['actor_id' => null]);
            DB::table('alert_rules')->where('created_by', $id)->update(['created_by' => null]);
            DB::table('quota_policies')->where('created_by', $id)->update(['created_by' => null]);
            DB::table('sessions')->where('user_id', $id)->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            $lockedUser->forceFill([
                'role_id' => null,
                'name' => 'Deleted user',
                'email' => 'deleted-'.Str::uuid().'@example.invalid',
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
            ])->save();

            OperationsAudit::create([
                'action' => 'account.anonymized',
                'target_type' => User::class,
                'target_id' => (string) $id,
                'context' => ['retained_reviews' => true],
                'occurred_at' => now(),
            ]);
        });

        $user->refresh();
    }
}
