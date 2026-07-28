<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReviewVocabularyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'learning_session_id' => ['required', 'integer', 'exists:learning_sessions,id'],
            'vocabulary_id' => ['required', 'integer', 'exists:vocabularies,id'],
            'rating' => ['required', 'integer', 'between:1,4'],
            'base_revision' => ['required', 'integer', 'min:0'],
            'response_time_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
        ];
    }
}
