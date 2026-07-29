<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class AdminImportRun extends Model
{
    public const STATUSES = [
        'fetching', 'validating', 'review_ready', 'approved', 'applying',
        'completed', 'validation_failed', 'apply_failed', 'cancelled',
    ];

    private const LEGACY_STATUSES = ['pending', 'running', 'succeeded', 'failed'];

    protected $attributes = ['initiator_type' => 'admin'];

    protected $fillable = [
        'request_id', 'entity', 'payload_fingerprint', 'actor_id', 'initiator_type', 'status',
        'requested_limit', 'reset', 'starting_cursor', 'processed', 'skipped',
        'result_cursor', 'error_code', 'error_message', 'reviewed_by',
        'reviewed_at', 'approved_by', 'approved_at', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'reset' => 'boolean',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AdminImportRun $run): void {
            if (! in_array($run->initiator_type, ['admin', 'cli'], true)) {
                throw new InvalidArgumentException('Import initiator must be admin or cli.');
            }
            if ($run->initiator_type === 'admin' && $run->actor_id === null) {
                throw new InvalidArgumentException('Admin imports require an actor.');
            }
            if (! in_array($run->status, [...self::STATUSES, ...self::LEGACY_STATUSES], true)) {
                throw new InvalidArgumentException('Unknown import run status.');
            }
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdminImportItem::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
