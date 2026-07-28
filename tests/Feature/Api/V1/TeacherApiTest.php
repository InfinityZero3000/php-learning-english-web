<?php

namespace Tests\Feature\Api\V1;

use App\Models\AlertRule;
use App\Models\Role;
use App\Models\SupervisionAlert;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_only_sees_assigned_learners_and_can_resolve_their_alert(): void
    {
        $this->seed();
        $teacher = $this->user('teacher');
        $learner = $this->user('learner');
        $other = $this->user('learner');
        TeacherAssignment::create(['teacher_id' => $teacher->id, 'learner_id' => $learner->id, 'assigned_at' => now()]);
        $rule = AlertRule::create(['rule_key' => 'inactivity', 'version' => 1, 'enabled' => true, 'parameters' => []]);
        $alert = SupervisionAlert::create([
            'learner_id' => $learner->id, 'alert_rule_id' => $rule->id, 'rule_key' => 'inactivity',
            'rule_version' => 1, 'fingerprint' => 'one', 'active_fingerprint' => 'one',
            'severity' => 'warning', 'evidence' => [['days' => 7]], 'state' => 'open', 'detected_at' => now(),
        ]);

        $this->actingAs($teacher)->getJson('/api/v1/teacher/learners')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $learner->id);
        $this->getJson("/api/v1/teacher/learners/{$other->id}/evidence")->assertForbidden();
        $this->postJson("/api/v1/teacher/alerts/{$alert->id}/resolve", [
            'resolution_code' => 'teacher_reviewed',
        ])->assertOk()->assertJsonPath('data.state', 'resolved');
        $this->assertNull($alert->fresh()->active_fingerprint);
    }

    public function test_learner_cannot_use_teacher_api(): void
    {
        $this->seed();
        $this->actingAs($this->user('learner'))->getJson('/api/v1/teacher/learners')->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]);
    }
}
