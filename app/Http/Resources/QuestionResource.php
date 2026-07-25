<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'content' => $this->content, 'type' => $this->type, 'image_url' => $this->image_url, 'audio_url' => $this->audio_url, 'order' => $this->order, 'answers' => AnswerResource::collection($this->whenLoaded('answers'))];
    }
}
