<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VocabularyReview extends Model
{
    protected $fillable = [
        'user_vocabulary_id', 'rating', 'response_time_ms', 'stability',
        'difficulty', 'scheduled_days', 'elapsed_days', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function userVocabulary(): BelongsTo
    {
        return $this->belongsTo(UserVocabulary::class);
    }
}
