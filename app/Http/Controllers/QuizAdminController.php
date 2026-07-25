<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuizAdminController extends Controller
{
    public function import(Request $request, Quiz $quiz)
    {
        abort_unless($request->user()?->role?->slug === 'admin', 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,text/plain,doc,docx'],
        ]);

        $path = $request->file('file')->store('imports');
        $fullPath = Storage::path($path);
        $content = $this->readFile($fullPath);

        $rows = $this->parseCsv($content);
        if (empty($rows)) {
            return back()->withErrors(['file' => 'Không thể đọc nội dung file. Vui lòng kiểm tra định dạng CSV hoặc file văn bản.']);
        }

        $imported = 0;
        foreach ($rows as $row) {
            if (empty($row['question'] ?? '')) {
                continue;
            }

            $question = $quiz->questions()->create([
                'content' => trim($row['question']),
                'type' => 'multiple_choice',
                'sort_order' => $quiz->questions()->count() + 1,
            ]);

            $options = [
                $row['option_a'] ?? null,
                $row['option_b'] ?? null,
                $row['option_c'] ?? null,
                $row['option_d'] ?? null,
            ];

            $correctAnswer = trim((string) ($row['correct_answer'] ?? ''));
            foreach ($options as $index => $option) {
                if (empty($option)) {
                    continue;
                }

                $answer = new Answer([
                    'content' => trim($option),
                    'is_correct' => Str::lower(trim($option)) === Str::lower($correctAnswer) || ($index + 1) === (int) $correctAnswer,
                    'explanation' => trim((string) ($row['explanation'] ?? '')),
                ]);

                $question->answers()->save($answer);
            }

            $imported++;
        }

        return back()->with('success', 'Đã import thành công ' . $imported . ' câu hỏi vào quiz.');
    }

    protected function readFile(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            return file_get_contents($path) ?: '';
        }

        if ($extension === 'doc') {
            return strip_tags(file_get_contents($path) ?: '');
        }

        if ($extension === 'docx') {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    return strip_tags(preg_replace('/<w:br\s*\/?>/i', "\n", $xml));
                }
            }
        }

        return '';
    }

    protected function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $parts = str_getcsv($line);
            if (count($parts) < 2) {
                continue;
            }

            $rows[] = [
                'question' => $parts[0] ?? '',
                'option_a' => $parts[1] ?? '',
                'option_b' => $parts[2] ?? '',
                'option_c' => $parts[3] ?? '',
                'option_d' => $parts[4] ?? '',
                'correct_answer' => $parts[5] ?? '',
                'explanation' => $parts[6] ?? '',
            ];
        }

        return $rows;
    }
}
