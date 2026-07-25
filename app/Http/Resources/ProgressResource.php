<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['lesson_id' => $this->lesson_id, 'status' => $this->status, 'progress_percent' => $this->progress_percent, 'completed_at' => $this->completed_at];
    }
}
