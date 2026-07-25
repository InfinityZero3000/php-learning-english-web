<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStreak extends Model
{
    protected $fillable = [
        'user_id', 'current_streak', 'longest_streak', 'last_activity_date',
        'last_check_in_date', 'total_check_ins',
    ];

    protected $casts = [
        'last_activity_date' => 'date',
        'last_check_in_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}