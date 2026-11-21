<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Properti;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Properti
 */
class PropertiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'nama_properti_laporan' => $this->nama_properti_laporan,
            'alamat' => $this->alamat,
            'no_wa_admin' => $this->no_wa_admin,
            'deskripsi' => $this->deskripsi,
            'status' => $this->status,
            'jumlah_kamar' => $this->whenCounted('kamar'),
            'jumlah_penghuni_aktif' => $this->whenCounted('penghuniAktif'),
            'admin_aktif_nama' => $this->relationLoaded('adminAktif') ? $this->adminAktif?->name : null,
            'boleh_ubah' => $user !== null && $user->can('update', $this->resource),
        ];
    }
}
