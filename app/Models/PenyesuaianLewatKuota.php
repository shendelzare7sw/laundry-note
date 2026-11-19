<?php

namespace App\Models;

use App\Models\Concerns\MilikProperti;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'properti_id',
    'penghuni_id',
    'bulan',
    'nominal',
    'catatan',
    'oleh',
])]
class PenyesuaianLewatKuota extends Model
{
    use HasFactory, MilikProperti;

    protected $table = 'penyesuaian_lewat_kuota';

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
        ];
    }

    public function penghuni(): BelongsTo
    {
        return $this->belongsTo(Penghuni::class, 'penghuni_id');
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
