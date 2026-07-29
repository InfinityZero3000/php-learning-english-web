<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WriteQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-content') === true;
    }

    public function rules(): array
    {
        return [
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'passing_score' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.content' => ['required', 'string', 'max:5000'],
            'questions.*.explanation' => ['nullable', 'string', 'max:5000'],
            'questions.*.answers' => ['required', 'array', 'min:2'],
            'questions.*.answers.*.content' => ['required', 'string', 'max:2000'],
            'questions.*.answers.*.is_correct' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('questions', []) as $index => $question) {
                if (! collect($question['answers'] ?? [])->contains(fn ($answer) => filter_var($answer['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))) {
                    $validator->errors()->add("questions.{$index}.answers", 'Each question must have at least one correct answer.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, [
            'errors' => $validator->errors(),
        ]));
    }
}
