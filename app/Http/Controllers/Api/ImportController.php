<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Models\Vocabulary;
use App\Models\VocabularySet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    public function importJson(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:json|max:10240',
            'set_name' => 'nullable|string|max:255',
        ]);

        $content = json_decode(file_get_contents($request->file('file')->path()), true);
        if (!$content || !is_array($content)) {
            return response()->json(['message' => 'Invalid JSON format'], 422);
        }

        $job = ImportJob::create([
            'user_id' => $request->user()->id,
            'source_type' => 'json',
            'file_name' => $request->file('file')->getClientOriginalName(),
            'target_set_name' => $request->set_name,
            'status' => 'processing',
            'total_rows' => count($content),
        ]);

        $set = null;
        if ($request->set_name) {
            $set = VocabularySet::firstOrCreate([
                'user_id' => $request->user()->id,
                'name' => $request->set_name,
            ]);
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $rows = [];
        $errors = [];

        foreach ($content as $index => $item) {
            try {
                $validator = Validator::make($item, [
                    'word' => 'required|string|max:255',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = ['row' => $index + 1, 'message' => $validator->errors()->first()];
                    continue;
                }

                $existing = Vocabulary::where('word', $item['word'])->first();
                if ($existing) {
                    $vocab = $existing;
                    $skipped++;
                } else {
                    $vocab = Vocabulary::create([
                        'word' => $item['word'],
                        'translation' => $item['translation'] ?? null,
                        'meaning' => $item['meaning'] ?? $item['translation'] ?? null,
                        'pronunciation' => $item['pronunciation'] ?? null,
                        'category' => $item['category'] ?? null,
                        'difficulty' => $item['difficulty'] ?? null,
                        'cefr_level' => $item['cefr_level'] ?? null,
                        'part_of_speech' => $item['part_of_speech'] ?? null,
                        'example' => $item['example'] ?? null,
                        'topic_id' => $item['topic_id'] ?? null,
                        'lesson_id' => $item['lesson_id'] ?? null,
                    ]);
                    $created++;
                }

                if ($set) {
                    $set->vocabularies()->syncWithoutDetaching([$vocab->id]);
                }

                $rows[] = ['word' => $item['word'], 'status' => 'success', 'id' => $vocab->id];
            } catch (\Exception $e) {
                $failed++;
                $errors[] = ['row' => $index + 1, 'message' => $e->getMessage()];
            }
        }

        $job->update([
            'status' => 'completed',
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'rows' => $rows,
            'errors' => $errors,
        ]);

        return response()->json([
            'job_id' => $job->id,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'set_id' => $set?->id,
        ]);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'set_name' => 'nullable|string|max:255',
        ]);

        $handle = fopen($request->file('file')->path(), 'r');
        $headers = fgetcsv($handle);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($headers, $data);
        }
        fclose($handle);

        $job = ImportJob::create([
            'user_id' => $request->user()->id,
            'source_type' => 'csv',
            'file_name' => $request->file('file')->getClientOriginalName(),
            'target_set_name' => $request->set_name,
            'status' => 'processing',
            'total_rows' => count($rows),
        ]);

        $set = null;
        if ($request->set_name) {
            $set = VocabularySet::firstOrCreate([
                'user_id' => $request->user()->id,
                'name' => $request->set_name,
            ]);
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $resultRows = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                if (empty($row['word'] ?? null)) {
                    $failed++;
                    $errors[] = ['row' => $index + 2, 'message' => 'word is required'];
                    continue;
                }

                $existing = Vocabulary::where('word', $row['word'])->first();
                if ($existing) {
                    $vocab = $existing;
                    $skipped++;
                } else {
                    $vocab = Vocabulary::create([
                        'word' => $row['word'],
                        'translation' => $row['translation'] ?? null,
                        'meaning' => $row['meaning'] ?? $row['translation'] ?? null,
                        'pronunciation' => $row['pronunciation'] ?? null,
                        'category' => $row['category'] ?? null,
                        'difficulty' => $row['difficulty'] ?? null,
                        'cefr_level' => $row['cefr_level'] ?? null,
                        'part_of_speech' => $row['part_of_speech'] ?? null,
                        'example' => $row['example'] ?? null,
                    ]);
                    $created++;
                }

                if ($set) {
                    $set->vocabularies()->syncWithoutDetaching([$vocab->id]);
                }

                $resultRows[] = ['word' => $row['word'], 'status' => 'success'];
            } catch (\Exception $e) {
                $failed++;
                $errors[] = ['row' => $index + 2, 'message' => $e->getMessage()];
            }
        }

        $job->update([
            'status' => 'completed',
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'rows' => $resultRows,
            'errors' => $errors,
        ]);

        return response()->json([
            'job_id' => $job->id,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    public function jobs(Request $request): JsonResponse
    {
        $jobs = ImportJob::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);
        return response()->json($jobs);
    }

    public function jobStatus(ImportJob $importJob): JsonResponse
    {
        if ($importJob->user_id !== request()->user()->id) {
            abort(403);
        }
        return response()->json(['data' => $importJob]);
    }
}