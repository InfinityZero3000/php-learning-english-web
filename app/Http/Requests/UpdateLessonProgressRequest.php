<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])], 'duration_seconds' => ['nullable', 'integer', 'min:0']];
    }
}
