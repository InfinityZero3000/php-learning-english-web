<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagedItem extends Model
{
    protected $fillable = [
        'admin_import_run_id', 'entity', 'external_id', 'classification',
        'incoming_snapshot', 'existing_snapshot', 'errors', 'status',
    ];

    protected function casts(): array
    {
        return [
            'incoming_snapshot' => 'array',
            'existing_snapshot' => 'array',
            'errors' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AdminImportRun::class, 'admin_import_run_id');
    }
}
