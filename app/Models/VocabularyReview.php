<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VocabularyReview extends Model
{
    protected $fillable = [
        'user_vocabulary_id', 'rating', 'response_time_ms', 'stability',
        'difficulty', 'scheduled_days', 'elapsed_days', 'reviewed_at',
        'request_id', 'algorithm', 'algorithm_version', 'base_revision',
        'resulting_revision', 'before_state', 'after_state', 'before_due_at',
        'after_due_at', 'before_stability', 'after_stability',
        'before_difficulty', 'after_difficulty', 'before_scheduled_days',
        'after_scheduled_days', 'before_last_reviewed_at',
        'after_last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'before_due_at' => 'datetime',
            'after_due_at' => 'datetime',
            'before_last_reviewed_at' => 'datetime',
            'after_last_reviewed_at' => 'datetime',
            'before_stability' => 'float',
            'after_stability' => 'float',
            'before_difficulty' => 'float',
            'after_difficulty' => 'float',
            'base_revision' => 'integer',
            'resulting_revision' => 'integer',
        ];
    }

    public function userVocabulary(): BelongsTo
    {
        return $this->belongsTo(UserVocabulary::class);
    }
}
