<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vocabulary extends Model
{
    protected $fillable = [
        'lesson_id', 'topic_id', 'external_id', 'import_fingerprint', 'word', 'meaning', 'definition',
        'translation', 'pronunciation', 'part_of_speech', 'difficulty_level',
        'tags', 'example', 'image_path', 'audio_path', 'external_audio_url',
    ];

    protected function casts(): array
    {
        return [
            'translation' => 'array',
            'tags' => 'array',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function userVocabularies(): HasMany
    {
        return $this->hasMany(UserVocabulary::class);
    }

    public function decks(): BelongsToMany
    {
        return $this->belongsToMany(
            VocabularyDeck::class,
            'vocabulary_deck_vocabulary',
            'vocabulary_id',
            'vocabulary_deck_id',
        )->withPivot('sort_order');
    }
}
