<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminImportItem extends Model
{
    protected $attributes = ['source_system' => 'lexilingo'];

    protected $fillable = [
        'admin_import_run_id', 'parent_item_id', 'entity', 'source_system',
        'external_id', 'natural_key', 'normalized_fingerprint', 'candidate_payload',
        'target_id', 'base_revision', 'base_fingerprint', 'base_snapshot',
        'base_override_at', 'classification', 'selected_action', 'dependencies',
        'validation_errors', 'reviewed_by', 'reviewed_at', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'candidate_payload' => 'array',
            'base_snapshot' => 'array',
            'dependencies' => 'array',
            'validation_errors' => 'array',
            'base_override_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AdminImportRun::class, 'admin_import_run_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
