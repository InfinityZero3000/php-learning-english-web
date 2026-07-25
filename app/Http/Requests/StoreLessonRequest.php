<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'cefr' => ['required', Rule::in(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'])],
            'order' => ['nullable', 'integer', 'min:0'], 'is_published' => ['boolean'],
        ];
    }
}
