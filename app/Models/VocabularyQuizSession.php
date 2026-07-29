<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VocabularyQuizSession extends Model
{
    protected $fillable = ['user_id', 'quiz_type', 'word_ids', 'answers', 'correct_count', 'incorrect_count', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['word_ids' => 'array', 'answers' => 'array', 'completed_at' => 'datetime'];
    }
}
