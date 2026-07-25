<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttemptAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['question_id' => ['required', 'integer', 'exists:questions,id'], 'answer_id' => ['nullable', 'required_without:answer_ids', 'integer', 'exists:answers,id'], 'answer_ids' => ['nullable', 'required_without:answer_id', 'array', 'min:1'], 'answer_ids.*' => ['integer', 'exists:answers,id']];
    }
}
