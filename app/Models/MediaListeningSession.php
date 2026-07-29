<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaListeningSession extends Model
{
    protected $fillable = [
        'user_id', 'source', 'external_id', 'title', 'channel', 'thumbnail_url',
        'position_seconds', 'duration_seconds', 'watched_percentage',
        'shadowing_attempts', 'best_pronunciation_score', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['best_pronunciation_score' => 'float', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
