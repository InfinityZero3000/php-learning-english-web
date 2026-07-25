<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'topic_id' => $this->topic_id, 'title' => $this->title, 'description' => $this->description, 'level' => $this->level, 'cefr' => $this->cefr, 'order' => $this->order, 'is_published' => $this->is_published, 'quizzes' => QuizResource::collection($this->whenLoaded('quizzes'))];
    }
}
