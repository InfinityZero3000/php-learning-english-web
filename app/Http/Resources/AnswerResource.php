<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'is_correct' => $this->when(array_key_exists('is_correct', $this->resource->getAttributes()), $this->is_correct),
            'explanation' => $this->when(array_key_exists('explanation', $this->resource->getAttributes()), $this->explanation),
        ];
    }
}
