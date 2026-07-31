<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminImportRun extends Model
{
    protected $fillable = [
        'request_id', 'entity', 'payload_fingerprint', 'actor_id', 'status',
        'requested_limit', 'reset', 'starting_cursor', 'processed', 'skipped',
        'result_cursor', 'error_code', 'error_message',
    ];

    protected function casts(): array
    {
        return ['reset' => 'boolean'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function stagedItems(): HasMany
    {
        return $this->hasMany(StagedItem::class);
    }
}
