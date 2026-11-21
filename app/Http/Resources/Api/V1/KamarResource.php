<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Kamar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Kamar
 */
class KamarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $penempatanAktif = $this->relationLoaded('penempatanAktif') ? $this->penempatanAktif : null;
        $penghuni = $penempatanAktif?->penghuni;

        return [
            'id' => $this->id,
            'nomor' => $this->nomor,
            'lantai' => (int) $this->lantai,
            'aktif' => (bool) $this->aktif,
            'terisi' => $penempatanAktif !== null,
            'penghuni_id' => $penghuni?->id,
            'penghuni_nama' => $penghuni?->nama,
            'penghuni_status' => $penghuni?->status,
        ];
    }
}
