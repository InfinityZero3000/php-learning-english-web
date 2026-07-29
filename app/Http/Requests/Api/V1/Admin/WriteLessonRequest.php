<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class WriteLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-content') === true;
    }

    public function rules(): array
    {
        $lesson = $this->route('lesson');

        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('lessons')->where('course_id', $this->integer('course_id'))->ignore($lesson)],
            'content' => ['nullable', 'string', 'max:50000'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, [
            'errors' => $validator->errors(),
        ]));
    }
}
