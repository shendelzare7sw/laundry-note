<?php

namespace App\Models;

use Database\Factories\LaundryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item laundry yang dibayar terpisah (selimut, sprei, bed cover, dll.) dan tidak masuk kuota.
 */
#[Fillable([
    'laundry_id',
    'nama',
    'jumlah',
    'berat_kg',
    'biaya',
    'urutan',
])]
class LaundryItem extends Model
{
    /** @use HasFactory<LaundryItemFactory> */
    use HasFactory;

    /**
     * Pilihan cepat item terpisah pada formulir.
     *
     * @var array<int, string>
     */
    public const PILIHAN_CEPAT = ['Selimut', 'Sprei', 'Bed cover', 'Lainnya'];

    public const MAKS_PER_CATATAN = 10;

    protected $table = 'laundry_item';

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'berat_kg' => 'decimal:2',
            'biaya' => 'integer',
            'urutan' => 'integer',
        ];
    }

    public function laundry(): BelongsTo
    {
        return $this->belongsTo(Laundry::class, 'laundry_id');
    }
}
