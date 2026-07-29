<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogOwnership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCategory extends Model
{
    use HasCatalogOwnership, HasFactory;

    protected $fillable = [
        'source_system', 'external_id', 'source_fingerprint', 'source_snapshot',
        'local_override_at', 'last_synced_at', 'catalog_revision',
        'name', 'slug', 'description', 'icon', 'color',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'source_snapshot' => 'array',
            'local_override_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'catalog_revision' => 'integer',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'category_id');
    }
}
