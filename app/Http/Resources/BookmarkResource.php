<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bookmark_type' => $this->bookmark_type,
            'vocabulary' => new VocabularyResource($this->whenLoaded('vocabulary')),
            'lesson' => new LessonResource($this->whenLoaded('lesson')),
            'created_at' => $this->created_at,
        ];
    }
}
