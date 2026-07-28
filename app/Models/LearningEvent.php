<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningEvent extends Model
{
    protected $fillable = [
        'learning_session_id', 'user_id', 'attempt_id', 'vocabulary_review_id',
        'event_type', 'request_id', 'response', 'is_correct', 'hint_level',
        'pronunciation_score', 'duration_ms', 'occurred_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'pronunciation_score' => 'float',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'learning_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function vocabularyReview(): BelongsTo
    {
        return $this->belongsTo(VocabularyReview::class);
    }

    public function assistanceResults(): HasMany
    {
        return $this->hasMany(LearningAssistanceResult::class);
    }
}
