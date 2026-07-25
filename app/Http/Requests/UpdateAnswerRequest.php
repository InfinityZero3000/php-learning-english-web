<?php

namespace App\Http\Requests;

class UpdateAnswerRequest extends StoreAnswerRequest
{
    public function rules(): array
    {
        return array_map(fn (array $rules) => array_merge(['sometimes'], $rules), parent::rules());
    }
}
