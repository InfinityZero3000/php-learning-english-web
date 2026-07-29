<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Models\SupervisionAlert;
use App\Models\User;
use App\Models\UserVocabulary;

class SupervisionAlertService
{
    public function evaluate(User $learner): array
    {
        $now = now('UTC');
        $events = LearningEvent::query()->where('user_id', $learner->id)
            ->where('occurred_at', '>=', $now->copy()->subDays(28))->latest('occurred_at')->get();
        $signals = [];

        $recent = $events->where('occurred_at', '>=', $now->copy()->subDays(7));
        $byConcept = $recent->filter(fn ($event) => $event->is_correct !== null)
            ->groupBy(fn ($event) => data_get($event->metadata, 'vocabulary_id', 'global'));
        foreach ($byConcept as $concept => $graded) {
            $lastFive = $graded->take(5);
            if ($lastFive->where('is_correct', false)->count() >= 3) {
                $signals[] = ['repeated_concept_error', (string) $concept, 'warning', ['incorrect' => $lastFive->where('is_correct', false)->count(), 'sample' => $lastFive->count()]];
            }
        }

        $current = $events->where('occurred_at', '>=', $now->copy()->subDays(14))->whereNotNull('is_correct');
        $previous = $events->whereBetween('occurred_at', [$now->copy()->subDays(28), $now->copy()->subDays(14)])->whereNotNull('is_correct');
        if ($current->count() >= 20) {
            $currentRate = $current->where('is_correct', true)->count() / $current->count();
            $previousRate = $previous->isEmpty() ? 1 : $previous->where('is_correct', true)->count() / $previous->count();
            if ($currentRate < .7 && $previousRate - $currentRate >= .1) {
                $signals[] = ['retention_drop', 'global', 'critical', ['current' => $currentRate, 'previous' => $previousRate, 'reviews' => $current->count()]];
            }
        }

        $due = UserVocabulary::query()->where('user_id', $learner->id)->where('due_at', '<=', $now)->count();
        $overdueAssignment = Assignment::query()->where('learner_id', $learner->id)->whereIn('status', ['pending', 'in_progress'])
            ->where('due_at', '<=', $now->copy()->subDays(7))->exists();
        if ($due >= 20 || $overdueAssignment) {
            $signals[] = ['overdue_work', 'global', $overdueAssignment ? 'critical' : 'warning', ['due_cards' => $due, 'overdue_assignment' => $overdueAssignment]];
        }

        if (Enrollment::query()->where('user_id', $learner->id)->where('status', 'active')->exists()
            && ! $events->where('occurred_at', '>=', $now->copy()->subDays(7))->where('event_type', 'session_completed')->count()) {
            $signals[] = ['inactivity', 'global', 'warning', ['days' => 7]];
        }

        $pronunciation = $recent->whereNotNull('pronunciation_score');
        if ($pronunciation->count() >= 3 && $pronunciation->avg('pronunciation_score') < 60) {
            $signals[] = ['weak_pronunciation', 'global', 'warning', ['mean' => round($pronunciation->avg('pronunciation_score'), 2), 'attempts' => $pronunciation->count()]];
        }

        $graded = $recent->whereNotNull('is_correct');
        $hinted = $graded->filter(fn ($event) => (int) $event->hint_level > 0)->count();
        if ($graded->count() >= 10 && $hinted / $graded->count() >= .5) {
            $signals[] = ['excessive_hint_use', 'global', 'warning', ['hinted' => $hinted, 'graded' => $graded->count()]];
        }

        return collect($signals)->map(fn (array $signal) => $this->record($learner, ...$signal))->all();
    }

    private function record(User $learner, string $key, string $subject, string $severity, array $snapshot): SupervisionAlert
    {
        $rule = AlertRule::firstOrCreate(
            ['rule_key' => $key, 'version' => 1],
            ['enabled' => true, 'parameters' => [], 'activated_at' => now('UTC')],
        );
        $fingerprint = hash('sha256', "{$learner->id}|1|{$key}|{$subject}");
        $alert = SupervisionAlert::firstOrNew(['active_fingerprint' => $fingerprint]);
        $evidence = $alert->exists ? $alert->evidence : [];
        $evidence[] = ['observed_at' => now('UTC')->toISOString(), ...$snapshot];
        $alert->fill([
            'learner_id' => $learner->id,
            'alert_rule_id' => $rule->id,
            'rule_key' => $key,
            'rule_version' => 1,
            'fingerprint' => $fingerprint,
            'active_fingerprint' => $fingerprint,
            'severity' => $severity,
            'evidence' => array_slice($evidence, -20),
            'state' => 'open',
            'detected_at' => $alert->detected_at ?? now('UTC'),
        ])->save();

        return $alert;
    }
}
