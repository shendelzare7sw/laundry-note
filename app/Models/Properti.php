<?php

namespace App\Models;

use App\Enums\Peran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['owner_id', 'nama', 'alamat', 'no_wa_admin', 'deskripsi', 'nama_properti_laporan', 'status'])]
class Properti extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'properti';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class, 'properti_id');
    }

    public function adminAktif(): HasOne
    {
        return $this->hasOne(User::class, 'properti_id')
            ->where('role', Peran::Admin->value)
            ->where('aktif', true);
    }

    public function penjaga(): HasMany
    {
        return $this->hasMany(User::class, 'properti_id')
            ->where('role', Peran::Penjaga->value);
    }

    public function kamar(): HasMany
    {
        return $this->hasMany(Kamar::class, 'properti_id')->orderBy('nomor');
    }

    public function penghuni(): HasMany
    {
        return $this->hasMany(Penghuni::class, 'properti_id');
    }

    public function penghuniAktif(): HasMany
    {
        return $this->hasMany(Penghuni::class, 'properti_id')->where('status', 'aktif');
    }

    public function penempatan(): HasMany
    {
        return $this->hasMany(Penempatan::class, 'properti_id');
    }

    public function laundry(): HasMany
    {
        return $this->hasMany(Laundry::class, 'properti_id');
    }

    public function pengaturan(): HasMany
    {
        return $this->hasMany(Pengaturan::class, 'properti_id');
    }
}
