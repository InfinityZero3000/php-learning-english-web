<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'quiz_id' => $this->quiz_id, 'score' => $this->score, 'total_questions' => $this->total_questions, 'correct_answers' => $this->correct_answers, 'started_at' => $this->started_at, 'finished_at' => $this->finished_at, 'status' => $this->status];
    }
}
