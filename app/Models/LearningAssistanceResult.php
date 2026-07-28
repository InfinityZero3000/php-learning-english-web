<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningAssistanceResult extends Model
{
    protected $fillable = [
        'learning_event_id', 'request_id', 'source', 'status', 'schema_version',
        'diagnosis', 'feedback', 'hints', 'degraded_reason', 'metadata',
    ];

    protected function casts(): array
    {
        return ['diagnosis' => 'array', 'hints' => 'array', 'metadata' => 'array'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LearningEvent::class, 'learning_event_id');
    }
}
