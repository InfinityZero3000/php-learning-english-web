<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportJob extends Model
{
    protected $fillable = [
        'user_id', 'source_type', 'file_name', 'target_set_name',
        'status', 'total_rows', 'created', 'skipped', 'failed',
        'rows', 'errors',
    ];

    protected $casts = [
        'rows' => 'array',
        'errors' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}