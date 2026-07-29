<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id', 'actor_email', 'actor_role', 'action', 'resource', 'detail', 'ip', 'status',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(
        User $actor,
        string $action,
        string $resource,
        ?string $detail = null,
        string $status = 'SUCCESS',
    ): self {
        return self::create([
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'actor_role' => strtoupper($actor->role?->slug ?? 'unknown'),
            'action' => $action,
            'resource' => $resource,
            'detail' => $detail,
            'ip' => request()?->ip(),
            'status' => $status,
        ]);
    }
}
