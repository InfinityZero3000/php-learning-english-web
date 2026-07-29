<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Models\SupervisionAlert;
use App\Models\User;
use App\Services\SupervisionAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupervisionAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_errors_and_inactivity_create_deduplicated_alerts(): void
    {
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'A1', 'slug' => 'a1-'.Str::random(5), 'status' => 'published']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'active']);
        foreach (range(1, 3) as $index) {
            LearningEvent::create([
                'user_id' => $learner->id, 'event_type' => 'answer', 'is_correct' => false,
                'occurred_at' => now()->subMinutes($index), 'metadata' => ['vocabulary_id' => 42],
            ]);
        }

        $service = app(SupervisionAlertService::class);
        $service->evaluate($learner);
        $service->evaluate($learner);

        $this->assertDatabaseHas('supervision_alerts', ['learner_id' => $learner->id, 'rule_key' => 'repeated_concept_error', 'state' => 'open']);
        $this->assertDatabaseHas('supervision_alerts', ['learner_id' => $learner->id, 'rule_key' => 'inactivity', 'state' => 'open']);
        $this->assertDatabaseCount('supervision_alerts', 2);
        $this->assertCount(2, SupervisionAlert::where('rule_key', 'repeated_concept_error')->firstOrFail()->evidence);
    }
}
