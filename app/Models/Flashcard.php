<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flashcard extends Model
{
    protected $fillable = [
        'flashcard_source_id', 'word', 'translation', 'definition',
        'example', 'level', 'source', 'source_name', 'topic',
        'front', 'back', 'is_imported',
    ];

    protected $casts = [
        'is_imported' => 'boolean',
    ];

    public function flashcardSource(): BelongsTo
    {
        return $this->belongsTo(FlashcardSource::class);
    }
}