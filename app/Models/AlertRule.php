<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    protected $fillable = [
        'rule_key', 'version', 'enabled', 'parameters', 'created_by', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'parameters' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(SupervisionAlert::class);
    }
}
