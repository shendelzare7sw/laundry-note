<?php

namespace App\Services;

use App\Models\Laundry;
use App\Models\LaundryItem;
use App\Models\Penempatan;
use App\Models\Penghuni;
use App\Models\Properti;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LaundryQueryService
{
    public function __construct(
        private readonly HargaService $hargaService,
        private readonly PengaturanService $pengaturanService,
        private readonly KuotaService $kuotaService,
    ) {}

    /**
     * Terjemahkan pilihan periode filter menjadi rentang tanggal (Asia/Jakarta).
     *
     * @return array{periode: string, dari: ?string, sampai: ?string, label: string}
     */
    public function rentangPeriode(string $periode = 'bulan_ini', ?string $dari = null, ?string $sampai = null): array
    {
        $hariIni = now('Asia/Jakarta')->toImmutable();

        if ($periode === 'rentang') {
            $tglDari = $this->parseTanggal($dari);
            $tglSampai = $this->parseTanggal($sampai);

            if ($tglDari !== null && $tglSampai !== null && $tglDari->gt($tglSampai)) {
                [$tglDari, $tglSampai] = [$tglSampai, $tglDari];
            }

            if ($tglDari !== null || $tglSampai !== null) {
                $label = match (true) {
                    $tglDari !== null && $tglSampai !== null => $tglDari->format('d/m/Y').' – '.$tglSampai->format('d/m/Y'),
                    $tglDari !== null => 'Mulai '.$tglDari->format('d/m/Y'),
                    default => 's.d. '.$tglSampai->format('d/m/Y'),
                };

                return [
                    'periode' => 'rentang',
                    'dari' => $tglDari?->toDateString(),
                    'sampai' => $tglSampai?->toDateString(),
                    'label' => $label,
                ];
            }

            $periode = 'bulan_ini';
        }

        return match ($periode) {
            'hari_ini' => [
                'periode' => 'hari_ini',
                'dari' => $hariIni->toDateString(),
                'sampai' => $hariIni->toDateString(),
                'label' => 'Hari ini',
            ],
            '7_hari' => [
                'periode' => '7_hari',
                'dari' => $hariIni->subDays(6)->toDateString(),
                'sampai' => $hariIni->toDateString(),
                'label' => '7 hari terakhir',
            ],
            'bulan_lalu' => (function () use ($hariIni): array {
                $bulanLalu = $hariIni->subMonthNoOverflow();

                return [
                    'periode' => 'bulan_lalu',
                    'dari' => $bulanLalu->startOfMonth()->toDateString(),
                    'sampai' => $bulanLalu->endOfMonth()->toDateString(),
                    'label' => $bulanLalu->locale('id')->translatedFormat('F Y'),
                ];
            })(),
            'semua' => [
                'periode' => 'semua',
                'dari' => null,
                'sampai' => null,
                'label' => 'Semua waktu',
            ],
            default => [
                'periode' => 'bulan_ini',
                'dari' => $hariIni->startOfMonth()->toDateString(),
                'sampai' => $hariIni->endOfMonth()->toDateString(),
                'label' => $hariIni->locale('id')->translatedFormat('F Y'),
            ],
        };
    }

    /**
     * Ambil daftar catatan laundry terpaginasi, ringkasan agregat, dan penanda merah kuota per baris.
     *
     * @param  array{periode?: string, dari?: ?string, sampai?: ?string, cari?: ?string, jenis?: ?string}  $filter
     * @return array{
     *     rentang: array{periode: string, dari: ?string, sampai: ?string, label: string},
     *     daftar: LengthAwarePaginator,
     *     ringkasan: array<string, mixed>,
     *     metaBaris: array<int, array<string, mixed>>
     * }
     */
    public function daftarDanRingkasan(Properti $properti, array $filter = [], int $perHalaman = 20): array
    {
        $rentang = $this->rentangPeriode(
            (string) ($filter['periode'] ?? 'bulan_ini'),
            $filter['dari'] ?? null,
            $filter['sampai'] ?? null,
        );
        $cari = trim((string) ($filter['cari'] ?? ''));
        $jenis = (string) ($filter['jenis'] ?? '');

        $base = Laundry::query()
            ->where('properti_id', $properti->id)
            ->when($rentang['dari'] !== null, fn ($q) => $q->whereDate('tanggal', '>=', $rentang['dari']))
            ->when($rentang['sampai'] !== null, fn ($q) => $q->whereDate('tanggal', '<=', $rentang['sampai']))
            ->when(
                $jenis !== '' && array_key_exists($jenis, Laundry::DAFTAR_JENIS),
                fn ($q) => $q->where('jenis', $jenis)
            )
            ->when($cari !== '', function ($query) use ($cari): void {
                $pola = '%'.$cari.'%';
                $query->where(function ($subQuery) use ($pola): void {
                    $subQuery->where('catatan', 'like', $pola)
                        ->orWhereHas('penghuni', fn ($qp) => $qp->where('nama', 'like', $pola)->orWhere('alias', 'like', $pola))
                        ->orWhereHas('kamar', fn ($qk) => $qk->where('nomor', 'like', $pola))
                        ->orWhereHas('pencatat', fn ($qu) => $qu->where('name', 'like', $pola))
                        ->orWhereHas('item', fn ($qi) => $qi->where('nama', 'like', $pola));
                });
            });

        $daftar = (clone $base)
            ->with(['penghuni', 'kamar', 'pencatat', 'item'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate($perHalaman);

        $ringkasan = $this->hitungRingkasan($properti, $base);
        $metaBaris = $this->penandaMerahDanKuotaBaris($properti, $daftar->getCollection());

        return [
            'rentang' => $rentang,
            'daftar' => $daftar,
            'ringkasan' => $ringkasan,
            'metaBaris' => $metaBaris,
        ];
    }

    /**
     * Daftar penghuni yang menempati kamar pada tanggal tertentu (selain berstatus Booking).
     *
     * @return Collection<int, array{id: int, nama: string, alias: ?string, kamar: string, status: string, label: string}>
     */
    public function penghuniAktifPadaTanggal(
        Properti $properti,
        string $tanggal,
        ?int $sertakanPenghuniId = null
    ): Collection {
        $penempatan = Penempatan::query()
            ->with(['penghuni', 'kamar'])
            ->where('properti_id', $properti->id)
            ->whereDate('mulai', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('selesai')->orWhereDate('selesai', '>=', $tanggal))
            ->whereHas('penghuni', fn ($q) => $q->where('status', '!=', 'booking'))
            ->get();

        $daftar = $penempatan
            ->filter(fn (Penempatan $p): bool => $p->penghuni !== null)
            ->unique('penghuni_id')
            ->map(function (Penempatan $p): array {
                $nomor = $p->kamar?->nomor ?? '-';

                return [
                    'id' => $p->penghuni->id,
                    'nama' => $p->penghuni->nama,
                    'alias' => $p->penghuni->alias,
                    'kamar' => $nomor,
                    'status' => $p->penghuni->status,
                    'label' => "Kamar {$nomor} · {$p->penghuni->nama}",
                ];
            });

        if ($sertakanPenghuniId !== null && ! $daftar->contains('id', $sertakanPenghuniId)) {
            $tambahan = Penghuni::withTrashed()
                ->where('properti_id', $properti->id)
                ->with('penempatan.kamar')
                ->find($sertakanPenghuniId);

            if ($tambahan !== null && $tambahan->status !== 'booking') {
                $nomor = $tambahan->penempatan->last()?->kamar?->nomor ?? '-';
                $daftar->push([
                    'id' => $tambahan->id,
                    'nama' => $tambahan->nama,
                    'alias' => $tambahan->alias,
                    'kamar' => $nomor,
                    'status' => $tambahan->status,
                    'label' => "Kamar {$nomor} · {$tambahan->nama}",
                ]);
            }
        }

        return $daftar
            ->sortBy(fn (array $item): string => $item['kamar'].' '.$item['nama'], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Daftar penghuni aktif properti ini yang mendekati atau melewati kuota pada bulan kalender berjalan.
     *
     * @return Collection<int, array{penghuni: Penghuni, kamar: string, kuota: array<string, mixed>}>
     */
    public function peringatanKuotaBulanIni(Properti $properti): Collection
    {
        $penghuniAktif = Penghuni::query()
            ->where('properti_id', $properti->id)
            ->where('status', 'aktif')
            ->with('penempatanAktif.kamar')
            ->get();

        if ($penghuniAktif->isEmpty()) {
            return collect();
        }

        $rentang = $this->kuotaService->rentangBulan();
        $ringkasanKuota = $this->kuotaService->ringkasanSemua(
            properti: $properti,
            bulan: $rentang['bulan'],
            penghuniIds: $penghuniAktif->pluck('id')->all(),
        );

        return $penghuniAktif
            ->map(function (Penghuni $penghuni) use ($ringkasanKuota): ?array {
                $kuota = $ringkasanKuota->get($penghuni->id);

                if ($kuota === null || ! in_array($kuota['status'], ['lewat', 'mendekati'], true)) {
                    return null;
                }

                return [
                    'penghuni' => $penghuni,
                    'kamar' => $penghuni->penempatanAktif?->kamar?->nomor ?? '-',
                    'kuota' => $kuota,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $item): int => ($item['kuota']['status'] === 'lewat' ? 100000 : 0) + (int) $item['kuota']['persen'])
            ->values();
    }

    /**
     * Hitung ringkasan atas seluruh baris yang memenuhi filter (tanpa terpotong paginasi).
     *
     * @param  Builder<Laundry>  $base
     * @return array<string, mixed>
     */
    private function hitungRingkasan(Properti $properti, $base): array
    {
        $agregat = (clone $base)
            ->selectRaw('COALESCE(SUM(berat_kg), 0) AS total_kg')
            ->selectRaw('COALESCE(SUM(biaya_utama), 0) AS total_biaya_utama')
            ->selectRaw('COALESCE(SUM(item_biaya), 0) AS total_terpisah')
            ->selectRaw('COUNT(*) AS jumlah_catatan')
            ->selectRaw('COALESCE(SUM(CASE WHEN item_biaya > 0 THEN 1 ELSE 0 END), 0) AS jumlah_catatan_terpisah')
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis = 'tanpa_nama' THEN 1 ELSE 0 END), 0) AS jumlah_tanpa_nama")
            ->first();

        $totalItem = LaundryItem::query()
            ->whereIn('laundry_id', (clone $base)->select('id'))
            ->selectRaw('COALESCE(SUM(jumlah), 0) AS total_item')
            ->value('total_item');

        $perPenghuniBulan = (clone $base)
            ->where('jenis', 'penghuni')
            ->whereNotNull('penghuni_id')
            ->whereNotNull('berat_kg')
            ->selectRaw("penghuni_id, DATE_FORMAT(tanggal, '%Y-%m') AS bulan, COALESCE(SUM(berat_kg), 0) AS total_kg")
            ->groupByRaw("penghuni_id, DATE_FORMAT(tanggal, '%Y-%m')")
            ->get();

        $kuotaGratisCenti = 0;
        $aturanBulan = [];

        foreach ($perPenghuniBulan as $row) {
            $bulan = (string) $row->bulan;

            if (! isset($aturanBulan[$bulan])) {
                $aturanBulan[$bulan] = $this->aturanKuotaBulan($properti->id, $bulan);
            }

            $aturan = $aturanBulan[$bulan];

            if ($aturan['mode_kuota'] !== 'penghuni' || $aturan['kuota_centi'] <= 0) {
                continue;
            }

            $beratCenti = $this->hargaService->keCentiKg((string) $row->total_kg);
            $kuotaGratisCenti += min($beratCenti, $aturan['kuota_centi']);
        }

        $totalBeratCenti = $this->hargaService->keCentiKg((string) ($agregat?->total_kg ?? '0'));

        return [
            'total_berat_centi' => $totalBeratCenti,
            'total_berat_kg' => $this->hargaService->dariCentiKg($totalBeratCenti, true),
            'kuota_gratis_centi' => $kuotaGratisCenti,
            'kuota_gratis_kg' => $this->hargaService->dariCentiKg($kuotaGratisCenti, true),
            'total_biaya_utama' => (int) ($agregat?->total_biaya_utama ?? 0),
            'total_bayar_terpisah' => (int) ($agregat?->total_terpisah ?? 0),
            'jumlah_catatan' => (int) ($agregat?->jumlah_catatan ?? 0),
            'jumlah_catatan_terpisah' => (int) ($agregat?->jumlah_catatan_terpisah ?? 0),
            'jumlah_item_terpisah' => (int) ($totalItem ?? 0),
            'jumlah_tanpa_nama' => (int) ($agregat?->jumlah_tanpa_nama ?? 0),
        ];
    }

    /**
     * Tentukan penanda merah dan ringkasan kuota bulanan untuk setiap baris di halaman aktif
     * menggunakan window function MySQL 8 pada cakupan penuh bulan kalender (bukan terpotong filter periode).
     *
     * @param  Collection<int, Laundry>  $daftarBaris
     * @return array<int, array<string, mixed>>
     */
    public function penandaMerahDanKuotaBaris(Properti $properti, Collection $daftarBaris): array
    {
        $barisPenghuni = $daftarBaris->filter(
            fn (Laundry $l): bool => $l->jenis === 'penghuni' && $l->penghuni_id !== null
        );

        if ($barisPenghuni->isEmpty()) {
            return [];
        }

        $penghuniIds = $barisPenghuni->pluck('penghuni_id')->unique()->map(fn ($id): int => (int) $id)->values()->all();
        $daftarBulan = $barisPenghuni->map(fn (Laundry $l): string => $l->tanggal->format('Y-m'))->unique()->sort()->values();

        $bulanPertama = CarbonImmutable::createFromFormat('!Y-m-d', $daftarBulan->first().'-01', 'Asia/Jakarta');
        $bulanTerakhir = CarbonImmutable::createFromFormat('!Y-m-d', $daftarBulan->last().'-01', 'Asia/Jakarta');

        $placeholders = implode(', ', array_fill(0, count($penghuniIds), '?'));
        $sql = "SELECT id, DATE_FORMAT(tanggal, '%Y-%m') AS bulan,
                       SUM(COALESCE(berat_kg, 0)) OVER (
                           PARTITION BY penghuni_id, DATE_FORMAT(tanggal, '%Y-%m')
                           ORDER BY tanggal ASC, id ASC
                       ) AS kumulatif_kg
                FROM laundry
                WHERE properti_id = ?
                  AND deleted_at IS NULL
                  AND jenis = 'penghuni'
                  AND penghuni_id IN ({$placeholders})
                  AND tanggal BETWEEN ? AND ?";

        $bindings = [
            $properti->id,
            ...$penghuniIds,
            $bulanPertama->startOfMonth()->toDateString(),
            $bulanTerakhir->endOfMonth()->toDateString(),
        ];

        $kumulatifRows = collect(DB::select($sql, $bindings))->keyBy('id');

        $aturanBulan = [];
        $ringkasanBulan = [];

        foreach ($barisPenghuni->groupBy(fn (Laundry $l): string => $l->tanggal->format('Y-m')) as $bulan => $kelompok) {
            $aturanBulan[$bulan] = $this->aturanKuotaBulan($properti->id, $bulan);
            $idsBulan = $kelompok->pluck('penghuni_id')->unique()->map(fn ($id): int => (int) $id)->values()->all();
            $ringkasanBulan[$bulan] = $this->kuotaService->ringkasanSemua($properti, $bulan, $idsBulan);
        }

        $meta = [];

        foreach ($barisPenghuni as $baris) {
            $bulan = $baris->tanggal->format('Y-m');
            $aturan = $aturanBulan[$bulan];
            $kumulatifStr = (string) ($kumulatifRows->get($baris->id)?->kumulatif_kg ?? '0');
            $kumulatifCenti = $this->hargaService->keCentiKg($kumulatifStr);
            $beratCenti = $baris->berat_kg !== null ? $this->hargaService->keCentiKg((string) $baris->berat_kg) : 0;

            // Aturan K4: setiap catatan berberat yang mendorong atau berada di atas kuota ditandai merah,
            // termasuk kelebihan di bawah toleransi_kg; catatan item-terpisah-saja (berat 0) tidak ditandai.
            $lewatKuota = $aturan['mode_kuota'] === 'penghuni'
                && $aturan['kuota_centi'] > 0
                && $beratCenti > 0
                && $kumulatifCenti > $aturan['kuota_centi'];

            $meta[$baris->id] = [
                'lewat_kuota' => $lewatKuota,
                'kumulatif_centi' => $kumulatifCenti,
                'kumulatif_kg' => $this->hargaService->dariCentiKg($kumulatifCenti, true),
                'kuota' => $ringkasanBulan[$bulan]->get($baris->penghuni_id),
            ];
        }

        return $meta;
    }

    /**
     * @return array{mode_kuota: string, kuota_centi: int}
     */
    private function aturanKuotaBulan(int $propertiId, string $bulan): array
    {
        $akhirBulan = CarbonImmutable::createFromFormat('!Y-m-d', "{$bulan}-01", 'Asia/Jakarta')
            ->endOfMonth()
            ->toDateString();
        $semua = $this->pengaturanService->semuaPada($propertiId, $akhirBulan);

        return [
            'mode_kuota' => $semua['mode_kuota'] ?? 'penghuni',
            'kuota_centi' => $this->hargaService->keCentiKg($semua['kuota_kg'] ?? '20'),
        ];
    }

    private function parseTanggal(?string $nilai): ?CarbonImmutable
    {
        if ($nilai === null) {
            return null;
        }

        $bersih = trim($nilai);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $bersih)) {
            return null;
        }

        try {
            $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', $bersih, 'Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }

        return $tanggal !== false && $tanggal->format('Y-m-d') === $bersih ? $tanggal : null;
    }
}
