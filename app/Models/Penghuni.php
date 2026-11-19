<?php

namespace App\Models;

use App\Models\Concerns\MilikProperti;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'properti_id',
    'nama',
    'alias',
    'no_wa',
    'status',
    'tanggal_masuk',
    'tanggal_masuk_perkiraan',
    'tanggal_keluar',
    'alasan_keluar',
    'catatan',
])]
class Penghuni extends Model
{
    use HasFactory, MilikProperti, SoftDeletes;

    protected $table = 'penghuni';

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_masuk_perkiraan' => 'boolean',
            'tanggal_keluar' => 'date',
        ];
    }

    public function penempatan(): HasMany
    {
        return $this->hasMany(Penempatan::class, 'penghuni_id')->orderBy('mulai');
    }

    public function penempatanAktif(): HasOne
    {
        return $this->hasOne(Penempatan::class, 'penghuni_id')->whereNull('selesai');
    }

    public function laundry(): HasMany
    {
        return $this->hasMany(Laundry::class, 'penghuni_id');
    }

    public function penyesuaianLewatKuota(): HasMany
    {
        return $this->hasMany(PenyesuaianLewatKuota::class, 'penghuni_id');
    }
}
