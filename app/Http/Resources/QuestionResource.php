<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'content' => $this->content,
            'explanation' => $this->explanation,
            'sort_order' => $this->sort_order,
            'answers' => AnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
