<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        ];
    }
}
