<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Laundry;
use App\Services\HargaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Laundry
 */
class LaundryResource extends JsonResource
{
    /**
     * @param  array<string, mixed>|null  $metaBaris
     */
    public function __construct(
        mixed $resource,
        private readonly ?array $metaBaris = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hargaService = app(HargaService::class);
        $beratCenti = $this->berat_kg !== null ? $hargaService->keCentiKg((string) $this->berat_kg) : null;
        $user = $request->user();

        $bolehUbah = $user !== null && $user->can('update', $this->resource);
        $bolehHapus = $user !== null && $user->can('delete', $this->resource);
        $bolehTetapkan = $this->jenis === 'tanpa_nama' && $user !== null && $user->can('tetapkanPemilik', $this->resource);

        return [
            'id' => $this->id,
            'properti_id' => $this->properti_id,
            'tanggal' => $this->tanggal?->toDateString(),
            'jenis' => $this->jenis,
            'jenis_label' => Laundry::DAFTAR_JENIS[$this->jenis] ?? $this->jenis,
            'penghuni_id' => $this->penghuni_id,
            'penghuni_nama' => $this->relationLoaded('penghuni') ? $this->penghuni?->nama : null,
            'kamar_id' => $this->kamar_id,
            'kamar_nomor' => $this->relationLoaded('kamar') ? $this->kamar?->nomor : null,
            'nama_tanpa_nama' => $this->nama_tanpa_nama,
            'berat_centi' => $beratCenti,
            'berat_label' => $beratCenti !== null ? $hargaService->dariCentiKg($beratCenti, true) : null,
            'biaya_utama' => $this->biaya_utama,
            'biaya_manual' => (bool) $this->biaya_manual,
            'item_biaya' => (int) $this->item_biaya,
            'item' => $this->relationLoaded('item')
                ? LaundryItemResource::collection($this->item)->resolve($request)
                : [],
            'catatan' => $this->catatan,
            'ref' => $this->ref,
            'dicatat_oleh' => $this->dicatat_oleh,
            'dicatat_oleh_nama' => $this->relationLoaded('pencatat') ? $this->pencatat?->name : null,
            'lewat_kuota' => (bool) ($this->metaBaris['lewat_kuota'] ?? false),
            'kuota_bulan' => $this->metaBaris['kuota'] ?? null,
            'boleh_ubah' => $bolehUbah,
            'boleh_hapus' => $bolehHapus,
            'boleh_tetapkan_pemilik' => $bolehTetapkan,
            'created_at' => $this->created_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'deleted_at' => $this->deleted_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
        ];
    }
}
