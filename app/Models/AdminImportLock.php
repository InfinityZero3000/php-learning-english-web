<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminImportLock extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'entity';

    protected $keyType = 'string';

    protected $fillable = ['entity', 'current_run_id', 'locked_at'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    public function currentRun(): BelongsTo
    {
        return $this->belongsTo(AdminImportRun::class, 'current_run_id');
    }
}
