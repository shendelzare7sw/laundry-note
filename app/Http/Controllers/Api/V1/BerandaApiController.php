<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PropertiResource;
use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\HargaService;
use App\Services\KuotaService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BerandaApiController extends Controller
{
    public function __construct(
        private readonly KuotaService $kuotaService,
        private readonly HargaService $hargaService,
        private readonly WhatsAppService $waService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');
        $rentangBulan = $this->kuotaService->rentangBulan(
            $request->filled('bulan') ? $request->string('bulan')->toString() : null
        );

        $penghuniAktif = Penghuni::query()
            ->where('properti_id', $properti->id)
            ->where('status', 'aktif')
            ->with('penempatanAktif.kamar')
            ->orderBy('nama')
            ->get();

        $ringkasanKuota = $this->kuotaService->ringkasanSemua(
            properti: $properti,
            bulan: $rentangBulan['bulan'],
            penghuniIds: $penghuniAktif->pluck('id')->all(),
        );

        $agregatBulanan = Laundry::query()
            ->where('properti_id', $properti->id)
            ->whereBetween('tanggal', [$rentangBulan['awal'], $rentangBulan['akhir']])
            ->selectRaw('COALESCE(SUM(berat_kg), 0) AS total_kg')
            ->selectRaw('COALESCE(SUM(biaya_utama), 0) AS total_biaya_utama')
            ->selectRaw('COALESCE(SUM(item_biaya), 0) AS total_terpisah')
            ->selectRaw('COUNT(DISTINCT penghuni_id) AS jumlah_penghuni')
            ->first();

        $totalBeratCenti = $this->hargaService->keCentiKg((string) ($agregatBulanan?->total_kg ?? '0'));

        $daftarPeringatan = $penghuniAktif
            ->map(function (Penghuni $penghuni) use ($properti, $ringkasanKuota, $rentangBulan): ?array {
                $kuota = $ringkasanKuota->get($penghuni->id);

                if ($kuota === null || ! in_array($kuota['status'], ['lewat', 'mendekati'], true)) {
                    return null;
                }

                $waPeringatan = $this->waService->pesanPeringatanKuota(
                    properti: $properti,
                    penghuni: $penghuni,
                    kuota: $kuota,
                    bulan: $rentangBulan['bulan'],
                );

                return [
                    'penghuni_id' => $penghuni->id,
                    'nama' => $penghuni->nama,
                    'kamar_nomor' => $penghuni->penempatanAktif?->kamar?->nomor ?? '-',
                    'no_wa' => $penghuni->no_wa,
                    'kuota' => $kuota,
                    'wa_url' => $waPeringatan['url'],
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $item): int => ($item['kuota']['status'] === 'lewat' ? 100000 : 0) + (int) $item['kuota']['persen'])
            ->values()
            ->all();

        return response()->json([
            'properti' => (new PropertiResource($properti))->resolve($request),
            'rentang_bulan' => $rentangBulan,
            'ringkasan' => [
                'total_berat_centi' => $totalBeratCenti,
                'total_berat_label' => $this->hargaService->dariCentiKg($totalBeratCenti, true),
                'jumlah_penghuni_mencatat' => (int) ($agregatBulanan?->jumlah_penghuni ?? 0),
                'total_biaya_utama' => (int) ($agregatBulanan?->total_biaya_utama ?? 0),
                'total_bayar_terpisah' => (int) ($agregatBulanan?->total_terpisah ?? 0),
            ],
            'daftar_peringatan' => $daftarPeringatan,
        ]);
    }
}
