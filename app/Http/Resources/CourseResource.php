<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'course',
            'id' => $this->id,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'language' => $this->language,
            'thumbnail_url' => $this->thumbnail_url,
            'estimated_duration' => $this->estimated_duration,
            'total_xp' => $this->total_xp,
            'level' => new LevelResource($this->whenLoaded('level')),
            'category' => new CourseCategoryResource($this->whenLoaded('category')),
            'topics' => TopicResource::collection($this->whenLoaded('topics')),
            'lessons_count' => $this->whenCounted('lessons'),
            'units_count' => $this->whenCounted('units'),
        ];
    }
}
