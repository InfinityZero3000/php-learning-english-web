<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vocabulary extends Model
{
    protected $fillable = [
        'lesson_id', 'topic_id', 'word', 'meaning',
        'translation', 'pronunciation', 'category', 'difficulty',
        'cefr_level', 'part_of_speech', 'example', 'image_path', 'image_url',
        'audio_path', 'audio_url', 'enrichment_status', 'synonyms', 'antonyms',
    ];

    protected $casts = [
        'difficulty' => 'string',
        'cefr_level' => 'string',
    ];

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

    public function vocabularySets(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\VocabularySet::class, 'vocabulary_set_word');
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(\App\Models\UserWordProgress::class);
    }

    public function isBookmarkedBy(\App\Models\User $user): bool
    {
        return $this->bookmarks()->where('user_id', $user->id)->exists();
    }
}