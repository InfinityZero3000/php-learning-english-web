<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewFsrsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'card_id' => [
                'required',
                'integer',
                Rule::exists('user_vocabularies', 'id')
                    ->where(fn (Builder $query) => $query->where('user_id', $this->user()->id)),
            ],
            'base_revision' => ['required', 'integer', 'min:0'],
        ];
    }
}
