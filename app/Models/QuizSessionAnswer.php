<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizSessionAnswer extends Model
{
    protected $fillable = [
        'quiz_session_id', 'vocabulary_id', 'correct', 'response_ms',
    ];

    protected $casts = [
        'correct' => 'boolean',
    ];

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class);
    }
}