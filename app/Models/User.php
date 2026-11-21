<?php

namespace App\Models;

use App\Enums\Peran;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'properti_id', 'aktif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'role' => Peran::class,
            'aktif' => 'boolean',
        ];
    }

    public function properti(): BelongsTo
    {
        return $this->belongsTo(Properti::class, 'properti_id');
    }

    public function propertiDimiliki(): HasMany
    {
        return $this->hasMany(Properti::class, 'owner_id');
    }

    public function isDevAdmin(): bool
    {
        return $this->role === Peran::DevAdmin;
    }

    public function isOwner(): bool
    {
        return $this->role === Peran::Owner;
    }

    public function isAdmin(): bool
    {
        return $this->role === Peran::Admin;
    }

    public function isPenjaga(): bool
    {
        return $this->role === Peran::Penjaga;
    }

    public function bisaAksesProperti(Properti|int $properti): bool
    {
        $propertiId = $properti instanceof Properti ? $properti->id : $properti;

        if ($this->isDevAdmin()) {
            return true;
        }

        if ($this->isOwner()) {
            if ($properti instanceof Properti) {
                return $properti->owner_id === $this->id;
            }

            return $this->propertiDimiliki()->whereKey($propertiId)->exists();
        }

        return $this->properti_id !== null && $this->properti_id === $propertiId;
    }
}
