<?php

namespace App\Services;

use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class LaporanService
{
    public function __construct(
        private readonly HargaService $hargaService,
        private readonly PengaturanService $pengaturanService,
        private readonly KuotaService $kuotaService,
        private readonly LaundryQueryService $laundryQueryService,
        private readonly WhatsAppService $waService,
    ) {}

    /**
     * Terjemahkan parameter filter laporan menjadi rentang tanggal konkret (Asia/Jakarta).
     *
     * @return array{
     *     periode: string,
     *     dari: string,
     *     sampai: string,
     *     label: string,
     *     bulan_acuan: string,
     *     is_bulan_penuh: bool
     * }
     */
    public function rentangLaporan(string $periode = 'bulan_ini', ?string $dari = null, ?string $sampai = null): array
    {
        $hariIni = now('Asia/Jakarta')->toImmutable();

        if ($periode === 'rentang') {
            $tglDari = $this->parseTanggal($dari);
            $tglSampai = $this->parseTanggal($sampai);

            if ($tglDari !== null && $tglSampai === null) {
                $tglSampai = $tglDari->endOfMonth();
            } elseif ($tglDari === null && $tglSampai !== null) {
                $tglDari = $tglSampai->startOfMonth();
            }

            if ($tglDari !== null && $tglSampai !== null) {
                if ($tglDari->gt($tglSampai)) {
                    [$tglDari, $tglSampai] = [$tglSampai, $tglDari];
                }

                return $this->formatRentang('rentang', $tglDari, $tglSampai);
            }
        }

        if ($periode === 'bulan_lalu') {
            $bulanLalu = $hariIni->subMonthNoOverflow();

            return $this->formatRentang('bulan_lalu', $bulanLalu->startOfMonth(), $bulanLalu->endOfMonth());
        }

        return $this->formatRentang('bulan_ini', $hariIni->startOfMonth(), $hariIni->endOfMonth());
    }

    /**
     * Susun seluruh data laporan bulanan / rentang tanggal siap tampil & cetak A4.
     *
     * @param  array{periode?: string, dari?: ?string, sampai?: ?string, penghuni_id?: int|string|null, rinci?: bool|int|string}  $filter
     * @return array<string, mixed>
     */
    public function susunLaporan(Properti $properti, array $filter = []): array
    {
        $rentang = $this->rentangLaporan(
            (string) ($filter['periode'] ?? 'bulan_ini'),
            isset($filter['dari']) ? (string) $filter['dari'] : null,
            isset($filter['sampai']) ? (string) $filter['sampai'] : null,
        );

        $penghuniId = isset($filter['penghuni_id']) && is_numeric($filter['penghuni_id']) && (int) $filter['penghuni_id'] > 0
            ? (int) $filter['penghuni_id']
            : null;
        $rinci = filter_var($filter['rinci'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $catatan = Laundry::query()
            ->where('properti_id', $properti->id)
            ->whereBetween('tanggal', [$rentang['dari'], $rentang['sampai']])
            ->when($penghuniId !== null, fn ($q) => $q->where('jenis', 'penghuni')->where('penghuni_id', $penghuniId))
            ->with([
                'penghuni.penempatanAktif.kamar',
                'penghuni.penempatan' => fn ($q) => $q->with('kamar')->orderBy('mulai')->orderBy('id'),
                'kamar',
                'item',
            ])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $metaMerah = $this->laundryQueryService->penandaMerahDanKuotaBaris($properti, $catatan);

        $grupPenghuni = $catatan
            ->where('jenis', 'penghuni')
            ->whereNotNull('penghuni_id')
            ->groupBy('penghuni_id');

        $penghuniIds = $grupPenghuni->keys()->map(fn ($id): int => (int) $id)->values()->all();
        $ringkasanKuota = $this->kuotaService->ringkasanSemua($properti, $rentang['bulan_acuan'], $penghuniIds);

        $kartuPenghuni = collect();

        foreach ($grupPenghuni as $pId => $daftarLaundry) {
            /** @var Penghuni|null $penghuni */
            $penghuni = $daftarLaundry->first()?->penghuni;

            if ($penghuni === null) {
                continue;
            }

            $kuota = $ringkasanKuota->get((int) $pId) ?? $this->kuotaService->statusBulan($penghuni, $rentang['bulan_acuan']);
            $kuota['terpakai_label'] = $this->waService->formatKg((int) $kuota['terpakai_centi']);
            $kuota['kuota_label'] = $this->waService->formatKg((int) $kuota['kuota_centi']);
            $kuota['kelebihan_label'] = $this->waService->formatKg((int) $kuota['kelebihan_centi']);

            $labelKamar = $this->jalurKamar($penghuni, $daftarLaundry, $rentang['dari'], $rentang['sampai']);
            $nomorAwal = explode('→', $labelKamar)[0] ?? 'ZZZ';

            $totalKgCenti = 0;
            $biayaUtama = 0;
            $bayarTerpisah = 0;
            $jumlahItemTerpisah = 0;
            $baris = [];

            foreach ($daftarLaundry as $laundry) {
                $beratCenti = $this->hargaService->keCentiKg((string) $laundry->berat_kg);
                $totalKgCenti += $beratCenti;
                $biayaUtama += (int) ($laundry->biaya_utama ?? 0);
                $bayarTerpisah += (int) ($laundry->item_biaya ?? 0);

                $meta = $metaMerah[$laundry->id] ?? null;
                $merah = (bool) ($meta['lewat_kuota'] ?? false);
                $tanggalLabel = CarbonImmutable::parse($laundry->tanggal, 'Asia/Jakarta')
                    ->locale('id')
                    ->translatedFormat('j M Y');

                if ($beratCenti > 0 || $laundry->biaya_utama !== null) {
                    $baris[] = [
                        'tipe' => 'utama',
                        'laundry_id' => $laundry->id,
                        'tanggal' => $laundry->tanggal->toDateString(),
                        'tanggal_label' => $tanggalLabel,
                        'berat_centi' => $beratCenti,
                        'berat_label' => $this->waService->formatKg($beratCenti),
                        'biaya' => $laundry->biaya_utama,
                        'merah' => $merah,
                        'catatan' => $laundry->catatan,
                    ];
                }

                if ($laundry->item->isNotEmpty()) {
                    foreach ($laundry->item as $item) {
                        $jumlahItemTerpisah++;
                        $baris[] = [
                            'tipe' => 'item',
                            'laundry_id' => $laundry->id,
                            'tanggal' => $laundry->tanggal->toDateString(),
                            'tanggal_label' => $tanggalLabel,
                            'nama_item' => $item->nama,
                            'biaya' => (int) $item->biaya,
                            'merah' => false,
                            'catatan' => null,
                        ];
                    }
                } elseif ($laundry->item_nama !== null && $laundry->item_nama !== '') {
                    $jumlahItemTerpisah++;
                    $baris[] = [
                        'tipe' => 'item',
                        'laundry_id' => $laundry->id,
                        'tanggal' => $laundry->tanggal->toDateString(),
                        'tanggal_label' => $tanggalLabel,
                        'nama_item' => $laundry->item_nama,
                        'biaya' => (int) ($laundry->item_biaya ?? 0),
                        'merah' => false,
                        'catatan' => null,
                    ];
                }
            }

            $kartu = [
                'penghuni' => $penghuni,
                'label_kamar' => $labelKamar,
                'urutan_kamar' => str_pad($nomorAwal, 6, '0', STR_PAD_LEFT).'-'.$penghuni->nama,
                'jumlah_catatan' => $daftarLaundry->count(),
                'total_kg_centi' => $totalKgCenti,
                'total_kg_label' => $this->waService->formatKg($totalKgCenti),
                'biaya_utama' => $biayaUtama,
                'bayar_terpisah' => $bayarTerpisah,
                'jumlah_item_terpisah' => $jumlahItemTerpisah,
                'tagihan_lewat_kuota' => (int) $kuota['tagihan_lewat_kuota'],
                'kuota' => $kuota,
                'tampilkan_periode_ini' => ! $rentang['is_bulan_penuh'] || $totalKgCenti !== (int) $kuota['terpakai_centi'],
                'baris' => $baris,
            ];

            $kartu['wa_tagihan'] = $this->waService->pesanTagihan(
                properti: $properti,
                penghuni: $penghuni,
                dataKartu: $kartu,
                labelPeriode: $rentang['label'],
            );

            $kartuPenghuni->push($kartu);
        }

        $kartuPenghuni = $kartuPenghuni->sortBy('urutan_kamar')->values();

        $kartuLainnya = collect();

        if ($penghuniId === null) {
            foreach (['gedung' => 'GDG - Laundry Gedung', 'tanpa_nama' => 'Tanpa Nama'] as $jenisLain => $judulLain) {
                $daftarLain = $catatan->where('jenis', $jenisLain);

                if ($daftarLain->isEmpty()) {
                    continue;
                }

                $totalKgCenti = 0;
                $biayaUtama = 0;
                $bayarTerpisah = 0;
                $jumlahItemTerpisah = 0;
                $baris = [];

                foreach ($daftarLain as $laundry) {
                    $beratCenti = $this->hargaService->keCentiKg((string) $laundry->berat_kg);
                    $totalKgCenti += $beratCenti;
                    $biayaUtama += (int) ($laundry->biaya_utama ?? 0);
                    $bayarTerpisah += (int) ($laundry->item_biaya ?? 0);

                    $tanggalLabel = CarbonImmutable::parse($laundry->tanggal, 'Asia/Jakarta')
                        ->locale('id')
                        ->translatedFormat('j M Y');

                    if ($beratCenti > 0 || $laundry->biaya_utama !== null) {
                        $baris[] = [
                            'tipe' => 'utama',
                            'laundry_id' => $laundry->id,
                            'tanggal' => $laundry->tanggal->toDateString(),
                            'tanggal_label' => $tanggalLabel,
                            'berat_centi' => $beratCenti,
                            'berat_label' => $this->waService->formatKg($beratCenti),
                            'biaya' => $laundry->biaya_utama,
                            'merah' => false,
                            'catatan' => $laundry->catatan,
                        ];
                    }

                    if ($laundry->item->isNotEmpty()) {
                        foreach ($laundry->item as $item) {
                            $jumlahItemTerpisah++;
                            $baris[] = [
                                'tipe' => 'item',
                                'laundry_id' => $laundry->id,
                                'tanggal' => $laundry->tanggal->toDateString(),
                                'tanggal_label' => $tanggalLabel,
                                'nama_item' => $item->nama,
                                'biaya' => (int) $item->biaya,
                                'merah' => false,
                                'catatan' => null,
                            ];
                        }
                    } elseif ($laundry->item_nama !== null && $laundry->item_nama !== '') {
                        $jumlahItemTerpisah++;
                        $baris[] = [
                            'tipe' => 'item',
                            'laundry_id' => $laundry->id,
                            'tanggal' => $laundry->tanggal->toDateString(),
                            'tanggal_label' => $tanggalLabel,
                            'nama_item' => $laundry->item_nama,
                            'biaya' => (int) ($laundry->item_biaya ?? 0),
                            'merah' => false,
                            'catatan' => null,
                        ];
                    }
                }

                $kartuLainnya->push([
                    'jenis' => $jenisLain,
                    'judul' => $judulLain,
                    'jumlah_catatan' => $daftarLain->count(),
                    'total_kg_centi' => $totalKgCenti,
                    'total_kg_label' => $this->waService->formatKg($totalKgCenti),
                    'biaya_utama' => $biayaUtama,
                    'bayar_terpisah' => $bayarTerpisah,
                    'jumlah_item_terpisah' => $jumlahItemTerpisah,
                    'baris' => $baris,
                ]);
            }
        }

        $totalKgSemuaCenti = (int) $kartuPenghuni->sum('total_kg_centi') + (int) $kartuLainnya->sum('total_kg_centi');
        $totalBiayaUtama = (int) $kartuPenghuni->sum('biaya_utama') + (int) $kartuLainnya->sum('biaya_utama');
        $totalTagihanLewat = (int) $kartuPenghuni->sum('tagihan_lewat_kuota');
        $totalBayarTerpisah = (int) $kartuPenghuni->sum('bayar_terpisah') + (int) $kartuLainnya->sum('bayar_terpisah');
        $jumlahMendekatiLewat = $kartuPenghuni->filter(
            fn (array $k): bool => in_array($k['kuota']['status'] ?? 'normal', ['mendekati', 'lewat'], true)
        )->count();

        $kuotaGratisCenti = $this->pengaturanService->kuotaKgCenti($properti, $rentang['sampai']);
        $namaProperti = (string) ($this->pengaturanService->nilai($properti, 'nama_properti', $rentang['sampai']) ?? $properti->nama_properti_laporan);

        $data = [
            'nama_properti' => $namaProperti,
            'rentang' => $rentang,
            'filter' => [
                'periode' => $rentang['periode'],
                'dari' => $rentang['dari'],
                'sampai' => $rentang['sampai'],
                'penghuni_id' => $penghuniId,
                'rinci' => $rinci,
            ],
            'dicetak_label' => now('Asia/Jakarta')->locale('id')->translatedFormat('j M Y'),
            'kuota_gratis_centi' => $kuotaGratisCenti,
            'kuota_gratis_label' => $this->waService->formatKg($kuotaGratisCenti),
            'ringkasan' => [
                'total_kg_centi' => $totalKgSemuaCenti,
                'total_kg_label' => $this->waService->formatKg($totalKgSemuaCenti),
                'total_catatan' => $catatan->count(),
                'jumlah_penghuni' => $kartuPenghuni->count(),
                'jumlah_mendekati_lewat' => $jumlahMendekatiLewat,
                'total_biaya_utama' => $totalBiayaUtama,
                'total_tagihan_lewat' => $totalTagihanLewat,
                'total_bayar_terpisah' => $totalBayarTerpisah,
            ],
            'kartu_penghuni' => $kartuPenghuni,
            'kartu_lainnya' => $kartuLainnya,
        ];

        $data['rekap_wa'] = $this->waService->pesanRekapGedung($properti, $data);

        return $data;
    }

    /**
     * Susun baris data untuk ekspor CSV yang rapi dibuka di Excel.
     *
     * @param  array{periode?: string, dari?: ?string, sampai?: ?string, penghuni_id?: int|string|null, rinci?: bool|int|string}  $filter
     * @return array{header: array<int, string>, baris: array<int, array<int, string|int>>}
     */
    public function dataCsv(Properti $properti, array $filter = []): array
    {
        $laporan = $this->susunLaporan($properti, $filter);

        $header = [
            'Tanggal',
            'Kamar',
            'Penghuni',
            'Status Penghuni',
            'Jenis',
            'Berat (kg)',
            'Biaya Utama (Rp)',
            'Item Terpisah',
            'Biaya Terpisah (Rp)',
            'Lewat Kuota',
            'Status Kuota Bulan',
            'Tagihan Lewat Kuota Bulan (Rp)',
            'Catatan',
        ];

        $barisCsv = [];

        foreach ($laporan['kartu_penghuni'] as $kartu) {
            $statusPenghuni = match ($kartu['penghuni']->status) {
                'booking' => 'Booking',
                'keluar' => 'Keluar',
                default => 'Aktif',
            };
            $statusKuota = match ($kartu['kuota']['status'] ?? 'normal') {
                'lewat' => 'Lewat kuota',
                'mendekati' => 'Mendekati kuota',
                default => 'Normal',
            };

            foreach ($kartu['baris'] as $row) {
                if ($row['tipe'] === 'item') {
                    $barisCsv[] = [
                        $row['tanggal'],
                        $kartu['label_kamar'],
                        $kartu['penghuni']->nama,
                        $statusPenghuni,
                        'Item Terpisah',
                        '0',
                        0,
                        $row['nama_item'],
                        (int) $row['biaya'],
                        'Tidak',
                        $statusKuota,
                        (int) $kartu['tagihan_lewat_kuota'],
                        '',
                    ];
                } else {
                    $barisCsv[] = [
                        $row['tanggal'],
                        $kartu['label_kamar'],
                        $kartu['penghuni']->nama,
                        $statusPenghuni,
                        'Penghuni',
                        $row['berat_label'],
                        (int) ($row['biaya'] ?? 0),
                        '',
                        0,
                        $row['merah'] ? 'Ya' : 'Tidak',
                        $statusKuota,
                        (int) $kartu['tagihan_lewat_kuota'],
                        (string) ($row['catatan'] ?? ''),
                    ];
                }
            }
        }

        foreach ($laporan['kartu_lainnya'] as $kartuLain) {
            foreach ($kartuLain['baris'] as $row) {
                if ($row['tipe'] === 'item') {
                    $barisCsv[] = [
                        $row['tanggal'],
                        '-',
                        $kartuLain['judul'],
                        '-',
                        'Item Terpisah',
                        '0',
                        0,
                        $row['nama_item'],
                        (int) $row['biaya'],
                        'Tidak',
                        '-',
                        0,
                        '',
                    ];
                } else {
                    $barisCsv[] = [
                        $row['tanggal'],
                        '-',
                        $kartuLain['judul'],
                        '-',
                        $kartuLain['judul'],
                        $row['berat_label'],
                        (int) ($row['biaya'] ?? 0),
                        '',
                        0,
                        'Tidak',
                        '-',
                        0,
                        (string) ($row['catatan'] ?? ''),
                    ];
                }
            }
        }

        return [
            'header' => $header,
            'baris' => $barisCsv,
        ];
    }

    /**
     * Susun jalur kamar penghuni secara kronologis (mis. "307→310" bila pindah kamar dalam periode).
     *
     * @param  Collection<int, Laundry>  $daftarLaundry
     */
    private function jalurKamar(Penghuni $penghuni, Collection $daftarLaundry, string $dari, string $sampai): string
    {
        $nomorDariPenempatan = $penghuni->penempatan
            ->filter(function ($penempatan) use ($dari, $sampai): bool {
                $mulai = $penempatan->mulai->toDateString();
                $selesai = $penempatan->selesai?->toDateString();

                return $mulai <= $sampai && ($selesai === null || $selesai >= $dari);
            })
            ->map(fn ($p): ?string => $p->kamar?->nomor)
            ->filter(fn (?string $n): bool => $n !== null && $n !== '')
            ->values();

        $nomorDariLaundry = $daftarLaundry
            ->map(fn (Laundry $l): ?string => $l->kamar?->nomor)
            ->filter(fn (?string $n): bool => $n !== null && $n !== '')
            ->values();

        $urutan = collect();

        $sumber = $nomorDariPenempatan->count() > 1
            ? $nomorDariPenempatan
            : ($nomorDariLaundry->isNotEmpty() ? $nomorDariLaundry : $nomorDariPenempatan);

        foreach ($sumber as $nomor) {
            if ($urutan->last() !== $nomor) {
                $urutan->push($nomor);
            }
        }

        if ($urutan->isEmpty()) {
            $fallback = $penghuni->penempatanAktif?->kamar?->nomor
                ?? $penghuni->penempatan->last()?->kamar?->nomor;

            return $fallback ?? '-';
        }

        return $urutan->implode('→');
    }

    /**
     * @return array{
     *     periode: string,
     *     dari: string,
     *     sampai: string,
     *     label: string,
     *     bulan_acuan: string,
     *     is_bulan_penuh: bool
     * }
     */
    private function formatRentang(string $periode, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $labelDari = $dari->locale('id')->translatedFormat('j M Y');
        $labelSampai = $sampai->locale('id')->translatedFormat('j M Y');
        $isBulanPenuh = $dari->isSameDay($dari->startOfMonth())
            && $sampai->isSameDay($dari->endOfMonth());

        return [
            'periode' => $periode,
            'dari' => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),
            'label' => $labelDari === $labelSampai ? $labelDari : "{$labelDari} - {$labelSampai}",
            'bulan_acuan' => $sampai->format('Y-m'),
            'is_bulan_penuh' => $isBulanPenuh,
        ];
    }

    private function parseTanggal(?string $nilai): ?CarbonImmutable
    {
        if ($nilai === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($nilai))) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', trim($nilai), 'Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }
    }
}
