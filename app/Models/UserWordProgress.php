<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWordProgress extends Model
{
    protected $table = 'user_word_progress';

    protected $fillable = [
        'user_id', 'vocabulary_id', 'mastery', 'correct_count', 'incorrect_count',
        'last_reviewed_at', 'next_review_at', 'stability', 'difficulty',
        'repetitions', 'lapses', 'state', 'due',
    ];

    protected $casts = [
        'last_reviewed_at' => 'datetime',
        'next_review_at' => 'datetime',
        'due' => 'datetime',
        'stability' => 'float',
        'difficulty' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class);
    }
}