<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LexiLingoImportFailure extends Model
{
    protected $table = 'lexilingo_import_failures';

    protected $fillable = ['entity', 'external_id', 'payload', 'errors'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'errors' => 'array',
        ];
    }
}
