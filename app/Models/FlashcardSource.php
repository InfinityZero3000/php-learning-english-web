<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashcardSource extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
    ];

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }
}