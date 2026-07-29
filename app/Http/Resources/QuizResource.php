<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'passing_score' => $this->passing_score,
            'status' => $this->status,
            'questions_count' => $this->whenCounted('questions'),
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
            'lesson' => new LessonResource($this->whenLoaded('lesson')),
        ];
    }
}
