<?php

namespace App\Http\Controllers;

use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\HargaService;
use App\Services\KuotaService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BerandaController extends Controller
{
    public function __construct(
        private readonly KuotaService $kuotaService,
        private readonly HargaService $hargaService,
        private readonly WhatsAppService $waService,
    ) {}

    public function index(Request $request): View
    {
        $propertiAktif = app()->bound('properti.aktif')
            ? app('properti.aktif')
            : null;

        $properti = $propertiAktif instanceof Properti ? $propertiAktif : null;
        $rentangBulan = $this->kuotaService->rentangBulan();
        $daftarPeringatan = collect();
        $totalBeratKg = '0';
        $jumlahPenghuniMencatat = 0;
        $totalBiayaUtama = 0;
        $totalBayarTerpisah = 0;

        if ($properti !== null) {
            $penghuniAktif = Penghuni::query()
                ->where('properti_id', $properti->id)
                ->whereIn('status', ['aktif', 'booking'])
                ->with('penempatanAktif.kamar')
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

            $totalBeratKg = $this->hargaService->dariCentiKg(
                $this->hargaService->keCentiKg((string) ($agregatBulanan?->total_kg ?? '0')),
                true,
            );
            $jumlahPenghuniMencatat = (int) ($agregatBulanan?->jumlah_penghuni ?? 0);
            $totalBiayaUtama = (int) ($agregatBulanan?->total_biaya_utama ?? 0);
            $totalBayarTerpisah = (int) ($agregatBulanan?->total_terpisah ?? 0);

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
                        'penghuni' => $penghuni,
                        'kamar' => $penghuni->penempatanAktif?->kamar?->nomor ?? '-',
                        'kuota' => $kuota,
                        'wa_url' => $waPeringatan['url'],
                    ];
                })
                ->filter()
                ->sortByDesc(fn (array $item): int => ($item['kuota']['status'] === 'lewat' ? 100000 : 0) + (int) $item['kuota']['persen'])
                ->values();
        }

        return view('beranda.index', [
            'propertiAktif' => $properti,
            'rentangBulan' => $rentangBulan,
            'totalBeratKg' => $totalBeratKg,
            'jumlahPenghuniMencatat' => $jumlahPenghuniMencatat,
            'totalBiayaUtama' => $totalBiayaUtama,
            'totalBayarTerpisah' => $totalBayarTerpisah,
            'daftarPeringatan' => $daftarPeringatan,
        ]);
    }
}
