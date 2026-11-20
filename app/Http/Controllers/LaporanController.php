<?php

namespace App\Http\Controllers;

use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\LaporanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    public function __construct(
        private readonly LaporanService $laporanService,
    ) {}

    public function index(Request $request): View
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('viewAny', [Laundry::class, $propertiAktif]);

        $filter = $this->ambilFilter($request);
        $laporan = $this->laporanService->susunLaporan($propertiAktif, $filter);

        $daftarPenghuniFilter = Penghuni::query()
            ->where('properti_id', $propertiAktif->id)
            ->with(['penempatanAktif.kamar', 'penempatan' => fn ($q) => $q->with('kamar')->orderByDesc('mulai')->orderByDesc('id')])
            ->orderBy('nama')
            ->get()
            ->sortBy(function (Penghuni $penghuni): string {
                $kamar = $penghuni->penempatanAktif?->kamar ?? $penghuni->penempatan->first()?->kamar;

                return str_pad($kamar?->nomor ?? 'ZZZ', 6, '0', STR_PAD_LEFT).'-'.$penghuni->nama;
            })
            ->values();

        return view('laporan.index', [
            'propertiAktif' => $propertiAktif,
            'laporan' => $laporan,
            'daftarPenghuniFilter' => $daftarPenghuniFilter,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('viewAny', [Laundry::class, $propertiAktif]);

        $filter = $this->ambilFilter($request);
        $rentang = $this->laporanService->rentangLaporan(
            $filter['periode'],
            $filter['dari'],
            $filter['sampai'],
        );
        $dataCsv = $this->laporanService->dataCsv($propertiAktif, $filter);

        $slugProperti = Str::slug($propertiAktif->nama);
        $namaFile = "laporan-laundry-{$slugProperti}-{$rentang['dari']}-sd-{$rentang['sampai']}.csv";

        return response()->streamDownload(function () use ($dataCsv): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // BOM UTF-8 agar karakter khusus & koma langsung terbaca rapi di Excel Windows
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $dataCsv['header']);

            foreach ($dataCsv['baris'] as $baris) {
                fputcsv($handle, $baris);
            }

            fclose($handle);
        }, $namaFile, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{periode: string, dari: ?string, sampai: ?string, penghuni_id: ?string, rinci: bool}
     */
    private function ambilFilter(Request $request): array
    {
        $periode = $request->string('periode')->toString();

        if (! in_array($periode, ['bulan_ini', 'bulan_lalu', 'rentang'], true)) {
            $periode = 'bulan_ini';
        }

        return [
            'periode' => $periode,
            'dari' => $request->filled('dari') ? $request->string('dari')->toString() : null,
            'sampai' => $request->filled('sampai') ? $request->string('sampai')->toString() : null,
            'penghuni_id' => $request->filled('penghuni_id') ? $request->string('penghuni_id')->toString() : null,
            'rinci' => $request->boolean('rinci'),
        ];
    }

    private function propertiAktif(): Properti
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        abort_unless($properti instanceof Properti, 403, 'Silakan pilih properti aktif terlebih dahulu.');

        return $properti;
    }
}
