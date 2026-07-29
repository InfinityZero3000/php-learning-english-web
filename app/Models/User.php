<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role_id', 'name', 'email', 'password', 'locked_at', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function isAdmin(): bool
    {
        return $this->role?->slug === 'admin';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(Progress::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function userVocabularies(): HasMany
    {
        return $this->hasMany(UserVocabulary::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'locked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
