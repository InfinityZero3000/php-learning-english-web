<?php

namespace App\Http\Requests\Api\V1;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubmitLessonQuizAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['answer_id' => ['required', 'integer', 'exists:answers,id']];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, [
            'errors' => $validator->errors(),
        ]));
    }
}
