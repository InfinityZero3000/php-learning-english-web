<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = [
        'course_id', 'source_system', 'external_id', 'source_fingerprint',
        'source_snapshot', 'local_override_at', 'last_synced_at',
        'catalog_revision', 'title', 'description', 'sort_order',
        'icon_url', 'background_color', 'status',
    ];

    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'local_override_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'catalog_revision' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
