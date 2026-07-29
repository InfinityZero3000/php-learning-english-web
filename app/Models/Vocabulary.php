<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vocabulary extends Model
{
    use HasCatalogOwnership;

    protected $fillable = [
        'lesson_id', 'topic_id', 'source_system', 'external_id',
        'source_fingerprint', 'source_snapshot', 'local_override_at',
        'last_synced_at', 'catalog_revision', 'import_fingerprint', 'word', 'meaning', 'definition',
        'translation', 'pronunciation', 'part_of_speech', 'difficulty_level',
        'tags', 'example', 'image_path', 'audio_path', 'external_audio_url',
    ];

    protected function casts(): array
    {
        return [
            'translation' => 'array',
            'tags' => 'array',
            'source_snapshot' => 'array',
            'local_override_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'catalog_revision' => 'integer',
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
