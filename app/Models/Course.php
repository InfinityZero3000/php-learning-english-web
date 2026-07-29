<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasCatalogOwnership;

    protected $fillable = [
        'level_id', 'category_id', 'source_system', 'external_id',
        'source_fingerprint', 'source_snapshot', 'local_override_at',
        'last_synced_at', 'catalog_revision', 'title', 'slug',
        'description', 'status', 'language', 'thumbnail_url',
        'estimated_duration', 'total_xp',
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

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
