<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VocabularyDeck extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_public'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function vocabularies(): BelongsToMany
    {
        return $this->belongsToMany(
            Vocabulary::class,
            'vocabulary_deck_vocabulary',
            'vocabulary_deck_id',
            'vocabulary_id',
        )->withPivot('sort_order')->orderByPivot('sort_order');
    }
}
