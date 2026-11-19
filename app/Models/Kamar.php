<?php

namespace App\Models;

use App\Models\Concerns\MilikProperti;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['properti_id', 'nomor', 'lantai', 'aktif'])]
class Kamar extends Model
{
    use HasFactory, MilikProperti;

    protected $table = 'kamar';

    protected function casts(): array
    {
        return [
            'lantai' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function penempatan(): HasMany
    {
        return $this->hasMany(Penempatan::class, 'kamar_id');
    }

    public function penempatanAktif(): HasOne
    {
        return $this->hasOne(Penempatan::class, 'kamar_id')->whereNull('selesai');
    }

    public function laundry(): HasMany
    {
        return $this->hasMany(Laundry::class, 'kamar_id');
    }
}
