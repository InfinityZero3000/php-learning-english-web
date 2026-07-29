<?php

namespace App\Services;

use App\Models\LearningAssistanceResult;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Support\ExternalSubject;
use App\Support\LexiLingoClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class LexiLingoTraceCag
{
    public function __construct(
        private readonly LexiLingoClient $client,
        private readonly ExternalSubject $subjects,
    ) {}

    public function analyze(User $user, array $input, string $requestId): array
    {
        $session = LearningSession::query()->with(['course.level', 'lesson.vocabularies'])
            ->whereKey($input['session_id'])->where('user_id', $user->id)->first();
        if (! $session) {
            throw new HttpException(403, 'Learning session is not available to this learner.');
        }

        $fingerprint = hash_hmac('sha256', json_encode($input, JSON_THROW_ON_ERROR), config('app.key'));
        $existing = LearningAssistanceResult::query()->where('request_id', $requestId)->first();
        if ($existing) {
            if ($existing->metadata['fingerprint'] !== $fingerprint || $existing->event->user_id !== $user->id) {
                throw new ConflictHttpException('Request ID was already used with a different payload.');
            }

            return $this->payload($existing);
        }

        $external = $this->externalPayload($user, $session, $input);
        try {
            $response = $this->client->traceCag()
                ->withHeader('X-Request-ID', $requestId)
                ->post('/api/v1/integrations/trace-cag/v1/analyze', $external)
                ->throw()
                ->json();
            if (! is_array($response)) {
                throw new RuntimeException('TraceCAG returned invalid JSON.');
            }
            $result = $response;
            $source = 'trace_cag';
            $status = 'completed';
        } catch (Throwable $exception) {
            report_if(! $exception instanceof RequestException, $exception);
            $result = $this->fallback($requestId, $input);
            $source = 'local_fallback';
            $status = 'degraded';
        }

        return DB::transaction(function () use ($user, $session, $input, $requestId, $fingerprint, $result, $source, $status): array {
            $event = LearningEvent::create([
                'learning_session_id' => $session->id,
                'user_id' => $user->id,
                'event_type' => 'ai_assistance',
                'request_id' => $requestId,
                'response' => $input['text'],
                'occurred_at' => now('UTC'),
                'metadata' => ['activity_id' => $input['activity_id'], 'input_type' => $input['input_type']],
            ]);
            $assistance = LearningAssistanceResult::create([
                'learning_event_id' => $event->id,
                'request_id' => $requestId,
                'source' => $source,
                'status' => $status,
                'schema_version' => $result['schema_version'] ?? '1.0',
                'diagnosis' => $result['diagnosis'] ?? ['codes' => [], 'summary' => ''],
                'feedback' => $result['feedback'] ?? $result['message'],
                'hints' => $result['hints'] ?? [],
                'degraded_reason' => $result['degraded'] ?? false ? 'trace_cag_unavailable' : null,
                'metadata' => [
                    'fingerprint' => $fingerprint,
                    'recommended_action' => $result['recommended_action'] ?? 'continue',
                    'message' => $result['message'] ?? '',
                ],
            ]);

            return $this->payload($assistance);
        });
    }

    private function externalPayload(User $user, LearningSession $session, array $input): array
    {
        $vocabulary = $session->lesson?->vocabularies->firstWhere('id', $this->activityVocabularyId($input['activity_id']))
            ?? $session->lesson?->vocabularies->first();
        $concepts = UserVocabulary::query()->with('vocabulary')->where('user_id', $user->id)
            ->orderByDesc('last_reviewed_at')->limit(50)->get()
            ->map(fn (UserVocabulary $card): array => [
                'code' => 'vocabulary:'.$card->vocabulary_id,
                'mastery' => min(1, max(0, ((float) $card->stability) / 30)),
            ])->all();

        return [
            'subject' => $this->subjects->for($user),
            'session_id' => (string) $session->id,
            'input_type' => $input['input_type'],
            'learner_snapshot' => [
                'learning_goal' => $session->course->title,
                'cefr_level' => in_array(strtoupper((string) $session->course->level?->slug), ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'], true)
                    ? strtoupper($session->course->level->slug) : 'A1',
                'concepts' => $concepts,
                'recent_errors' => [],
            ],
            'exercise_context' => [
                'type' => $input['input_type'] === 'pronunciation' ? 'pronunciation' : 'flashcard',
                'prompt' => $vocabulary?->word ?? $session->lesson?->title ?? 'English practice',
                'expected_answer' => $vocabulary?->meaning,
                'concept_codes' => $vocabulary ? ['vocabulary:'.$vocabulary->id] : [],
            ],
            'text' => $input['text'],
        ];
    }

    private function fallback(string $requestId, array $input): array
    {
        $empty = trim($input['text']) === '';

        return [
            'schema_version' => '1.0',
            'request_id' => $requestId,
            'diagnosis' => ['codes' => $empty ? ['empty_answer'] : ['needs_review'], 'summary' => $empty ? 'No answer was provided.' : 'Answer saved for review.'],
            'hints' => [['level' => 1, 'text' => 'Review the prompt and try once more.']],
            'feedback' => $empty ? 'Please enter an answer.' : 'Your answer was saved. Compare it with the lesson and retry.',
            'recommended_action' => 'retry',
            'message' => 'AI feedback is temporarily unavailable; deterministic guidance is shown.',
            'degraded' => true,
            'model' => null,
        ];
    }

    private function payload(LearningAssistanceResult $result): array
    {
        return [
            'assistance' => [
                'schema_version' => $result->schema_version,
                'diagnosis' => $result->diagnosis,
                'hints' => $result->hints,
                'feedback' => $result->feedback,
                'recommended_action' => $result->metadata['recommended_action'],
                'message' => $result->metadata['message'],
                'degraded' => $result->status === 'degraded',
            ],
        ];
    }

    private function activityVocabularyId(string $activityId): ?int
    {
        return str_starts_with($activityId, 'vocabulary:') ? (int) substr($activityId, 11) : null;
    }
}
