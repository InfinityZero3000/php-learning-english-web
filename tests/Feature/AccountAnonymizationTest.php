<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\OperationsAudit;
use App\Models\Role;
use App\Models\SupervisionAlert;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use App\Models\VocabularyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountAnonymizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_deletion_anonymizes_account_and_retained_history(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'email' => 'learner@example.com',
            'role_id' => Role::where('slug', 'learner')->value('id'),
        ]);
        $course = Course::firstOrFail();
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        LearningSession::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
        $vocabulary = Vocabulary::create([
            'word' => 'private',
            'meaning' => 'retained',
        ]);
        $userVocabulary = UserVocabulary::create([
            'user_id' => $user->id,
            'vocabulary_id' => $vocabulary->id,
        ]);
        $review = VocabularyReview::create([
            'user_vocabulary_id' => $userVocabulary->id,
            'rating' => 3,
            'stability' => 1,
            'difficulty' => 5,
            'scheduled_days' => 1,
            'elapsed_days' => 0,
            'reviewed_at' => now(),
        ]);
        $resolvedAlert = SupervisionAlert::create([
            'learner_id' => $user->id,
            'rule_key' => 'retained',
            'rule_version' => 1,
            'fingerprint' => 'retained-alert',
            'severity' => 'high',
            'evidence' => ['response' => 'personal text'],
            'state' => 'resolved',
            'detected_at' => now(),
            'resolved_at' => now(),
            'resolution_note' => 'personal note',
        ]);
        SupervisionAlert::create([
            'learner_id' => $user->id,
            'rule_key' => 'ephemeral',
            'rule_version' => 1,
            'fingerprint' => 'open-alert',
            'severity' => 'low',
            'evidence' => ['response' => 'personal text'],
            'state' => 'open',
            'detected_at' => now(),
        ]);
        OperationsAudit::create([
            'actor_id' => $user->id,
            'action' => 'test',
            'occurred_at' => now(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'account-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertNoContent();

        $anonymized = $user->fresh();
        $this->assertNotNull($anonymized);
        $this->assertSame('Deleted user', $anonymized->name);
        $this->assertStringEndsWith('@example.invalid', $anonymized->email);
        $this->assertNull($anonymized->role_id);
        $this->assertNull($anonymized->email_verified_at);
        $this->assertNull($anonymized->remember_token);
        $this->assertModelExists($review);
        $this->assertDatabaseMissing('learning_sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('supervision_alerts', ['fingerprint' => 'open-alert']);
        $this->assertSame(['anonymized' => true], $resolvedAlert->fresh()->evidence);
        $this->assertNull($resolvedAlert->fresh()->resolution_note);
        $this->assertNull(OperationsAudit::where('action', 'test')->firstOrFail()->actor_id);
    }

    public function test_final_super_admin_cannot_delete_account(): void
    {
        $this->seed();
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
        ]);

        $this->actingAs($superAdmin)
            ->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertTrue($superAdmin->fresh()->hasRole('super_admin'));
    }
}
