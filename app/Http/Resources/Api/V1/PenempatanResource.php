<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Penempatan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Penempatan
 */
class PenempatanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kamar_id' => $this->kamar_id,
            'kamar_nomor' => $this->kamar?->nomor,
            'kamar_lantai' => $this->kamar?->lantai,
            'mulai' => $this->mulai?->toDateString(),
            'selesai' => $this->selesai?->toDateString(),
            'sebab' => $this->sebab,
            'aktif' => $this->selesai === null,
            'dibuat_oleh_nama' => $this->relationLoaded('pembuat') ? $this->pembuat?->name : null,
        ];
    }
}
