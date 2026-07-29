<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_system', 'external_id', 'source_fingerprint', 'source_snapshot',
        'local_override_at', 'last_synced_at', 'catalog_revision', 'name', 'slug',
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

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }

    public function vocabularies(): HasMany
    {
        return $this->hasMany(Vocabulary::class);
    }
}
