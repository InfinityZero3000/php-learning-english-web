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
            'user_vocabulary_id' => ['required', 'integer', 'exists:user_vocabularies,id'],
            'rating' => ['required', 'string', 'in:again,hard,good,easy'],
            'base_revision' => ['required', 'integer', 'min:0'],
            'reviewed_at' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['request_id' => $this->header('X-Request-ID')]);
    }
}
