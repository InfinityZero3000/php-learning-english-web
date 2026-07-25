<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    protected $fillable = [
        'user_id', 'review_reminders_enabled', 'preferred_reminder_time',
        'evening_reminder_enabled', 'evening_reminder_time',
        'comeback_reminder_enabled', 'comeback_reminder_interval_days',
        'evening_remaining_threshold',
    ];

    protected $casts = [
        'review_reminders_enabled' => 'boolean',
        'evening_reminder_enabled' => 'boolean',
        'comeback_reminder_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}