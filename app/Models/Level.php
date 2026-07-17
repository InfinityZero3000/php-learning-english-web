<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
