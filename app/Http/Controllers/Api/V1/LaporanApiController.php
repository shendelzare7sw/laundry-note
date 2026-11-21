<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\LaporanService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LaporanApiController extends Controller
{
    public function __construct(
        private readonly LaporanService $laporanService,
        private readonly WhatsAppService $waService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $properti = $this->propertiAktif();
        Gate::authorize('viewAny', [Laundry::class, $properti]);

        $periode = $request->string('periode', 'bulan_ini')->toString();

        if (! in_array($periode, ['bulan_ini', 'bulan_lalu', 'rentang'], true)) {
            $periode = 'bulan_ini';
        }

        $laporan = $this->laporanService->susunLaporan($properti, [
            'periode' => $periode,
            'dari' => $request->filled('dari') ? $request->string('dari')->toString() : null,
            'sampai' => $request->filled('sampai') ? $request->string('sampai')->toString() : null,
            'penghuni_id' => $request->filled('penghuni_id') ? $request->string('penghuni_id')->toString() : null,
            'rinci' => $request->boolean('rinci'),
        ]);

        $user = $request->user();

        $kartuPenghuni = $laporan['kartu_penghuni']->map(function (array $kartu) use ($user): array {
            /** @var Penghuni $penghuni */
            $penghuni = $kartu['penghuni'];

            return [
                'penghuni' => [
                    'id' => $penghuni->id,
                    'nama' => $penghuni->nama,
                    'alias' => $penghuni->alias,
                    'no_wa' => $penghuni->no_wa,
                    'status' => $penghuni->status,
                ],
                'label_kamar' => $kartu['label_kamar'],
                'jumlah_catatan' => $kartu['jumlah_catatan'],
                'total_kg_centi' => $kartu['total_kg_centi'],
                'total_kg_label' => $kartu['total_kg_label'],
                'biaya_utama' => $kartu['biaya_utama'],
                'bayar_terpisah' => $kartu['bayar_terpisah'],
                'jumlah_item_terpisah' => $kartu['jumlah_item_terpisah'],
                'tagihan_lewat_kuota' => $kartu['tagihan_lewat_kuota'],
                'kuota' => $kartu['kuota'],
                'tampilkan_periode_ini' => $kartu['tampilkan_periode_ini'],
                'boleh_sesuaikan_kuota' => $user->can('sesuaikanKuota', $penghuni),
                'baris' => $kartu['baris'],
                'wa_tagihan' => $kartu['wa_tagihan'],
            ];
        })->values()->all();

        return response()->json([
            'nama_properti' => $laporan['nama_properti'],
            'rentang' => $laporan['rentang'],
            'filter' => $laporan['filter'],
            'dicetak_label' => $laporan['dicetak_label'],
            'kuota_gratis_centi' => $laporan['kuota_gratis_centi'],
            'kuota_gratis_label' => $laporan['kuota_gratis_label'],
            'ringkasan' => $laporan['ringkasan'],
            'kartu_penghuni' => $kartuPenghuni,
            'kartu_lainnya' => $laporan['kartu_lainnya']->values()->all(),
            'rekap_wa' => $laporan['rekap_wa'],
        ]);
    }

    public function waTagihan(Request $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        abort_if($penghuni->properti_id !== $properti->id, 404, 'Penghuni tidak ditemukan pada properti aktif.');
        Gate::authorize('view', $penghuni);

        $bulan = $request->filled('bulan') ? $request->string('bulan')->toString() : null;

        return response()->json(
            $this->waService->pesanTagihanDariPenghuni($properti, $penghuni, $bulan)
        );
    }

    public function waPeringatan(Request $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        abort_if($penghuni->properti_id !== $properti->id, 404, 'Penghuni tidak ditemukan pada properti aktif.');
        Gate::authorize('view', $penghuni);

        $bulan = $request->filled('bulan') ? $request->string('bulan')->toString() : null;

        return response()->json(
            $this->waService->pesanPeringatanKuota($properti, $penghuni, null, $bulan)
        );
    }

    private function propertiAktif(): Properti
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');

        return $properti;
    }
}
