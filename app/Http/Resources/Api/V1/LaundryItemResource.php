<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LaundryItem;
use App\Services\HargaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LaundryItem
 */
class LaundryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hargaService = app(HargaService::class);
        $beratCenti = $this->berat_kg !== null ? $hargaService->keCentiKg((string) $this->berat_kg) : null;

        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'jumlah' => (int) $this->jumlah,
            'berat_centi' => $beratCenti,
            'berat_label' => $beratCenti !== null ? $hargaService->dariCentiKg($beratCenti, true) : null,
            'biaya' => (int) $this->biaya,
            'urutan' => (int) $this->urutan,
        ];
    }
}
