<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Penghuni;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Penghuni
 */
class PenghuniResource extends JsonResource
{
    /**
     * @param  array<string, mixed>|null  $kuota
     */
    public function __construct(
        mixed $resource,
        private readonly ?array $kuota = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kamarTerkini = $this->relationLoaded('penempatanAktif') && $this->penempatanAktif !== null
            ? $this->penempatanAktif->kamar
            : ($this->relationLoaded('penempatan') ? $this->penempatan->first()?->kamar : null);

        return [
            'id' => $this->id,
            'properti_id' => $this->properti_id,
            'nama' => $this->nama,
            'alias' => $this->alias,
            'no_wa' => $this->no_wa,
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'booking' => 'Booking',
                'keluar' => 'Selesai',
                default => 'Aktif',
            },
            'kamar_id' => $kamarTerkini?->id,
            'kamar_nomor' => $kamarTerkini?->nomor,
            'tanggal_masuk' => $this->tanggal_masuk?->toDateString(),
            'tanggal_masuk_perkiraan' => (bool) $this->tanggal_masuk_perkiraan,
            'jangka_waktu_sewa' => $this->jangka_waktu_sewa ?? 'tidak_tentu',
            'jangka_waktu_label' => $this->labelJangkaWaktu(),
            'tanggal_keluar_rencana' => $this->tanggal_keluar_rencana?->toDateString(),
            'tanggal_keluar' => $this->tanggal_keluar?->toDateString(),
            'alasan_keluar' => $this->alasan_keluar,
            'catatan' => $this->catatan,
            'kuota' => $this->kuota,
        ];
    }
}
