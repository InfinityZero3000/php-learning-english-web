<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LearnerToolsController extends Controller
{
    public function notifications(Request $request): JsonResponse
    {
        $due = UserVocabulary::query()->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '<=', now('UTC')))->count();
        $key = 'fsrs-due:'.now('UTC')->toDateString();
        $items = $due > 0 ? [[
            'id' => $key, 'title' => 'Review ready',
            'message' => "Bạn có {$due} từ cần ôn hôm nay.", 'type' => 'DAILY_DUE_REMINDER',
            'deepLink' => '/flashcards', 'createdAt' => now('UTC')->startOfDay()->toISOString(),
        ]] : [];
        $read = DB::table('learner_notification_reads')->where('user_id', $request->user()->id)
            ->pluck('notification_key')->all();

        return ApiResponse::success(collect($items)->map(fn (array $item) => [
            ...$item, 'isRead' => in_array($item['id'], $read, true),
        ])->values());
    }

    public function readNotification(Request $request, string $key): JsonResponse
    {
        abort_unless($key === 'fsrs-due:'.now('UTC')->toDateString(), 404);
        DB::table('learner_notification_reads')->updateOrInsert(
            ['user_id' => $request->user()->id, 'notification_key' => $key],
            ['read_at' => now('UTC')],
        );

        return ApiResponse::success(['notification_key' => $key, 'read' => true]);
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        DB::table('learner_notification_reads')->updateOrInsert(
            ['user_id' => $request->user()->id, 'notification_key' => 'fsrs-due:'.now('UTC')->toDateString()],
            ['read_at' => now('UTC')],
        );

        return ApiResponse::success(['read' => true]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $validated = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();
        $owned = UserVocabulary::query()->where('user_id', $request->user()->id)->select('vocabulary_id');
        $query = Vocabulary::query()->whereNotIn('id', $owned)->orderBy('word');
        if ($search = trim($validated['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('word', 'like', "%{$search}%")->orWhere('meaning', 'like', "%{$search}%"));
        }

        return ApiResponse::success($query->limit($validated['per_page'] ?? 30)->get()->map(fn (Vocabulary $word) => $this->word($word)));
    }

    public function saveRecommendation(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $state = UserVocabulary::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'vocabulary_id' => $vocabulary->id],
            ['state' => 'learning', 'due_at' => now('UTC')],
        );

        return ApiResponse::success(['id' => $state->id, 'vocabulary_id' => $vocabulary->id], $state->wasRecentlyCreated ? 201 : 200);
    }

    public function importRuns(Request $request): JsonResponse
    {
        $items = DB::table('learner_import_runs')->where('user_id', $request->user()->id)
            ->latest('id')->limit(20)->get()->map(fn ($run) => [
                'id' => $run->id, 'targetSetName' => $run->target_set_name, 'status' => strtoupper($run->status),
                'totalRows' => $run->total_rows, 'createdCount' => $run->created_count, 'skippedCount' => $run->skipped_count,
                'createdAt' => $run->created_at,
            ]);

        return ApiResponse::success($items);
    }

    public function import(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'sourceType' => ['required', 'in:PASTE,FILE'], 'targetSet.name' => ['nullable', 'string', 'max:120'],
            'rows' => ['required', 'array', 'min:1', 'max:200'], 'rows.*.word' => ['required', 'string', 'max:120'],
            'rows.*.translation' => ['required', 'string', 'max:1000'], 'rows.*.pronunciation' => ['nullable', 'string', 'max:255'],
            'rows.*.partOfSpeech' => ['nullable', 'string', 'max:80'], 'rows.*.difficulty' => ['nullable', 'in:BEGINNER,INTERMEDIATE,ADVANCED'],
            'rows.*.category' => ['nullable', 'string', 'max:80'], 'rows.*.cefrLevel' => ['nullable', 'in:A1,A2,B1,B2,C1,C2'],
            'rows.*.topicId' => ['nullable', 'integer', 'exists:topics,id'], 'rows.*.example' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $result = DB::transaction(function () use ($data, $request): array {
            $created = 0;
            $skipped = 0;
            foreach ($data['rows'] as $row) {
                $word = Vocabulary::query()->firstOrCreate(
                    ['word' => trim($row['word'])],
                    ['meaning' => trim($row['translation']), 'pronunciation' => $row['pronunciation'] ?? null,
                        'part_of_speech' => $row['partOfSpeech'] ?? $row['category'] ?? null, 'difficulty_level' => $row['difficulty'] ?? null,
                        'tags' => empty($row['cefrLevel']) ? null : [$row['cefrLevel']],
                        'topic_id' => $row['topicId'] ?? null, 'example' => $row['example'] ?? null],
                );
                $state = UserVocabulary::query()->firstOrCreate(
                    ['user_id' => $request->user()->id, 'vocabulary_id' => $word->id],
                    ['state' => 'learning', 'due_at' => now('UTC')],
                );
                $state->wasRecentlyCreated ? $created++ : $skipped++;
            }
            $id = DB::table('learner_import_runs')->insertGetId([
                'user_id' => $request->user()->id, 'source_type' => $data['sourceType'],
                'target_set_name' => data_get($data, 'targetSet.name'), 'total_rows' => count($data['rows']),
                'created_count' => $created, 'skipped_count' => $skipped, 'status' => 'completed',
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);

            return ['id' => $id, 'createdCount' => $created, 'skippedCount' => $skipped];
        });

        return ApiResponse::success($result, 201);
    }

    public function dictionary(string $word): JsonResponse
    {
        $item = Vocabulary::query()->whereRaw('LOWER(word) = ?', [mb_strtolower(trim($word))])->first();
        if (! $item) {
            return ApiResponse::error('DICTIONARY_WORD_NOT_FOUND', 'Word is not available in the local catalog.', 404);
        }

        return ApiResponse::success([
            'firstDefinition' => $item->meaning, 'firstExample' => $item->example,
            'bestPhonetic' => $item->pronunciation, 'primaryPartOfSpeech' => $item->part_of_speech,
            'bestAudioUrl' => $item->external_audio_url,
        ]);
    }

    public function filters(): JsonResponse
    {
        return ApiResponse::success(Vocabulary::query()->whereNotNull('part_of_speech')->distinct()->orderBy('part_of_speech')->pluck('part_of_speech'));
    }

    private function word(Vocabulary $word): array
    {
        return ['id' => $word->id, 'word' => $word->word, 'translation' => $word->meaning,
            'definition' => $word->definition, 'pronunciation' => $word->pronunciation,
            'partOfSpeech' => $word->part_of_speech, 'difficulty' => $word->difficulty_level, 'example' => $word->example];
    }
}
