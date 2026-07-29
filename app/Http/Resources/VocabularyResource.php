<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VocabularyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'vocabulary',
            'id' => $this->id,
            'external_id' => $this->external_id,
            'word' => $this->word,
            'meaning' => $this->meaning,
            'definition' => $this->definition,
            'translation' => $this->translation,
            'pronunciation' => $this->pronunciation,
            'part_of_speech' => $this->part_of_speech,
            'difficulty_level' => $this->difficulty_level,
            'tags' => $this->tags,
            'external_audio_url' => $this->external_audio_url,
            'example' => $this->example,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'audio_url' => $this->audio_path ? Storage::disk('public')->url($this->audio_path) : null,
            'topic' => $this->whenLoaded('topic', fn () => $this->topic ? [
                'id' => $this->topic->id,
                'name' => $this->topic->name,
            ] : null),
            'lesson' => $this->whenLoaded('lesson', fn () => $this->lesson ? [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
            ] : null),
            'is_bookmarked' => (bool) ($this->is_bookmarked ?? false),
        ];
    }
}
