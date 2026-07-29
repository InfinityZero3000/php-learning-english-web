<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'lesson',
            'id' => $this->id,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'lesson_type' => $this->lesson_type,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'estimated_minutes' => $this->estimated_minutes,
            'xp_reward' => $this->xp_reward,
            'pass_threshold' => $this->pass_threshold,
            'course' => new CourseResource($this->whenLoaded('course')),
            'unit_id' => $this->unit_id,
            'quizzes_count' => $this->whenCounted('quizzes'),
            'quizzes' => $this->whenLoaded('quizzes', fn () => $this->quizzes->map(fn ($quiz) => [
                'id' => $quiz->id, 'title' => $quiz->title, 'status' => $quiz->status,
                'passing_score' => $quiz->passing_score,
            ])),
            'vocabularies_count' => $this->whenCounted('vocabularies'),
            'vocabularies' => VocabularyResource::collection($this->whenLoaded('vocabularies')),
            'content' => $this->when($this->content !== null, $this->content),
        ];
    }
}
