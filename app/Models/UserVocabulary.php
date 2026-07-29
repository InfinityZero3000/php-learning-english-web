<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserVocabulary extends Model
{
    protected $attributes = ['state' => 'learning', 'step' => 0, 'revision' => 0];

    protected $fillable = [
        'user_id', 'vocabulary_id', 'due_at', 'state', 'stability',
        'difficulty', 'scheduled_days', 'elapsed_days', 'repetitions', 'lapses',
        'last_reviewed_at', 'step', 'algorithm', 'algorithm_version', 'revision',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
            'stability' => 'float',
            'difficulty' => 'float',
            'revision' => 'integer',
            'step' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VocabularyReview::class);
    }
}
