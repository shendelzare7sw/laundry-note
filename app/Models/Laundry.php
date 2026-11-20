<?php

namespace App\Models;

use App\Models\Concerns\MilikProperti;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catatan laundry. Kolom item_biaya menyimpan total bayar terpisah dari seluruh baris laundry_item
 * (disinkronkan oleh LaundryService) agar agregasi kuota dan laporan tetap satu query tanpa JOIN.
 */
#[Fillable([
    'properti_id',
    'tanggal',
    'jenis',
    'penghuni_id',
    'kamar_id',
    'berat_kg',
    'biaya_utama',
    'biaya_manual',
    'item_biaya',
    'catatan',
    'ref',
    'dicatat_oleh',
])]
class Laundry extends Model
{
    use HasFactory, MilikProperti, SoftDeletes;

    /**
     * @var array<string, string>
     */
    public const DAFTAR_JENIS = [
        'penghuni' => 'Penghuni',
        'gedung' => 'Gedung',
        'tanpa_nama' => 'Tanpa nama',
    ];

    protected $table = 'laundry';

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'berat_kg' => 'decimal:2',
            'biaya_utama' => 'integer',
            'biaya_manual' => 'boolean',
            'item_biaya' => 'integer',
        ];
    }

    public function penghuni(): BelongsTo
    {
        return $this->belongsTo(Penghuni::class, 'penghuni_id')->withTrashed();
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class, 'kamar_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function item(): HasMany
    {
        return $this->hasMany(LaundryItem::class, 'laundry_id')->orderBy('urutan')->orderBy('id');
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis] ?? 'Penghuni';
    }

    /**
     * Ringkasan item terpisah, mis. "Selimut ×1, Bed cover ×2".
     */
    public function ringkasanItem(): string
    {
        return $this->item
            ->map(fn (LaundryItem $item): string => "{$item->nama} ×{$item->jumlah}")
            ->implode(', ');
    }
}
