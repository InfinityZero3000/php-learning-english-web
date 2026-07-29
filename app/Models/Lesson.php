<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = [
        'course_id', 'unit_id', 'source_system', 'external_id',
        'source_fingerprint', 'source_snapshot', 'local_override_at',
        'last_synced_at', 'catalog_revision', 'title', 'slug', 'content',
        'sort_order', 'status', 'lesson_type', 'estimated_minutes',
        'xp_reward', 'pass_threshold',
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

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function vocabularies(): HasMany
    {
        return $this->hasMany(Vocabulary::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'lesson_prerequisites',
            'lesson_id',
            'prerequisite_lesson_id',
        );
    }

    public function dependentLessons(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'lesson_prerequisites',
            'prerequisite_lesson_id',
            'lesson_id',
        );
    }

    public function progress(): HasMany
    {
        return $this->hasMany(Progress::class);
    }
}
