<?php

namespace App\Models;

use App\Models\Concerns\MilikProperti;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'properti_id',
    'tanggal',
    'jenis',
    'penghuni_id',
    'kamar_id',
    'berat_kg',
    'biaya_utama',
    'biaya_manual',
    'item_nama',
    'item_jumlah',
    'item_berat_kg',
    'item_biaya',
    'catatan',
    'ref',
    'dicatat_oleh',
])]
class Laundry extends Model
{
    use HasFactory, MilikProperti, SoftDeletes;

    protected $table = 'laundry';

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'berat_kg' => 'decimal:2',
            'biaya_utama' => 'integer',
            'biaya_manual' => 'boolean',
            'item_jumlah' => 'integer',
            'item_berat_kg' => 'decimal:2',
            'item_biaya' => 'integer',
        ];
    }

    public function penghuni(): BelongsTo
    {
        return $this->belongsTo(Penghuni::class, 'penghuni_id');
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class, 'kamar_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
