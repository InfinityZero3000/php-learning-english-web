<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LexiLingoImportCheckpoint extends Model
{
    protected $table = 'lexilingo_import_checkpoints';

    protected $fillable = ['entity', 'cursor', 'last_synced_at'];

    protected function casts(): array
    {
        return [
            'cursor' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }
}
