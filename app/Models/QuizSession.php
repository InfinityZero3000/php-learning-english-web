<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizSession extends Model
{
    protected $fillable = [
        'user_id', 'total_questions', 'correct_answers', 'completed',
        'score', 'type', 'category', 'difficulty', 'topic_id', 'cefr_level', 'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'score' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizSessionAnswer::class);
    }
}