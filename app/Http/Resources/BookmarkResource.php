<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'note' => $this->note, 'created_at' => $this->created_at, 'vocabulary' => ['id' => $this->vocabulary_id, 'word' => $this->word, 'meaning' => $this->meaning, 'topic_id' => $this->topic_id]];
    }
}
