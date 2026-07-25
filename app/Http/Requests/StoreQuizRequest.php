<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'], 'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'time_limit' => ['nullable', 'integer', 'min:1'],
            'pass_score' => ['nullable', 'integer', 'between:0,100'],
            'questions' => ['nullable', 'array'], 'questions.*.id' => ['nullable', 'integer', 'exists:questions,id'],
            'questions.*.content' => ['required_with:questions', 'string'],
            'questions.*.type' => ['required_with:questions', Rule::in(['multiple_choice', 'fill_blank', 'listening', 'matching'])],
            'questions.*.image_url' => ['nullable', 'url'], 'questions.*.audio_url' => ['nullable', 'url'],
            'questions.*.order' => ['nullable', 'integer', 'min:0'], 'questions.*.answers' => ['required_with:questions', 'array', 'min:1'],
            'questions.*.answers.*.id' => ['nullable', 'integer', 'exists:answers,id'],
            'questions.*.answers.*.content' => ['required', 'string'], 'questions.*.answers.*.is_correct' => ['required', 'boolean'],
            'questions.*.answers.*.explanation' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach ($this->input('questions', []) as $index => $question) {
                if (($question['type'] ?? null) !== 'matching' && collect($question['answers'] ?? [])->where('is_correct', true)->count() !== 1) {
                    $validator->errors()->add("questions.{$index}.answers", 'Each non-matching question must have exactly one correct answer.');
                }
            }
        }];
    }
}
