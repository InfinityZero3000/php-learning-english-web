<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\OperationsAudit;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class WriteUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-content') === true;
    }

    public function rules(): array
    {
        $unit = $this->route('unit');
        $sortOrder = ['required', 'integer', 'min:0'];
        if (! OperationsAudit::query()->where('request_id', $this->header('X-Request-ID'))->exists()) {
            $sortOrder[] = Rule::unique('units')->where('course_id', $this->integer('course_id'))->ignore($unit);
        }

        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'sort_order' => $sortOrder,
            'icon_url' => ['nullable', 'string', 'max:2048'],
            'background_color' => ['nullable', 'string', 'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', 422, [
            'errors' => $validator->errors(),
        ]));
    }
}
