<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordOfTheDay extends Model
{
    protected $table = 'word_of_the_day';

    protected $fillable = [
        'vocabulary_id', 'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class);
    }
}