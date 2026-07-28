<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionNote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'supervision_alert_id', 'assignment_id', 'teacher_id', 'learner_id',
        'note', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SupervisionAlert::class, 'supervision_alert_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }
}
