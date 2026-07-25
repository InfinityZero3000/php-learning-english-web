<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'], 'type' => ['required', Rule::in(['multiple_choice', 'fill_blank', 'listening', 'matching'])],
            'image_url' => ['nullable', 'url'], 'audio_url' => ['nullable', 'url'], 'order' => ['nullable', 'integer', 'min:0'],
            'answers' => ['required', 'array', 'min:1'], 'answers.*.content' => ['required', 'string'],
            'answers.*.is_correct' => ['required', 'boolean'], 'answers.*.explanation' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->has('answers') && $this->input('type') !== 'matching' && collect($this->input('answers', []))->where('is_correct', true)->count() !== 1) {
                $validator->errors()->add('answers', 'Each non-matching question must have exactly one correct answer.');
            }
        }];
    }
}
