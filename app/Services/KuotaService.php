<?php

namespace App\Services;

use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\PenyesuaianLewatKuota;
use App\Models\Properti;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KuotaService
{
    public function __construct(
        private readonly PengaturanService $pengaturanService,
        private readonly HargaService $hargaService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Kembalikan rentang awal/akhir bulan kalender (Asia/Jakarta) dan format YYYY-MM.
     *
     * @return array{bulan: string, awal: string, akhir: string, label: string}
     */
    public function rentangBulan(?string $bulan = null): array
    {
        $acuan = $bulan !== null && preg_match('/^\d{4}-\d{2}$/', $bulan)
            ? CarbonImmutable::createFromFormat('Y-m-d', "{$bulan}-01", 'Asia/Jakarta')
            : now('Asia/Jakarta')->toImmutable()->startOfMonth();

        return [
            'bulan' => $acuan->format('Y-m'),
            'awal' => $acuan->startOfMonth()->toDateString(),
            'akhir' => $acuan->endOfMonth()->toDateString(),
            'label' => $acuan->locale('id')->translatedFormat('F Y'),
        ];
    }

    /**
     * Hitung status kuota seorang penghuni pada bulan YYYY-MM tertentu.
     * Kuota selalu dihitung per penghuni_id dalam bulan kalender, tanpa filter kamar_id.
     *
     * @return array{
     *     penghuni_id: int,
     *     bulan: string,
     *     label_bulan: string,
     *     mode_kuota: string,
     *     terpakai_centi: int,
     *     terpakai_kg: string,
     *     kuota_centi: int,
     *     kuota_kg: string,
     *     sisa_centi: int,
     *     sisa_kg: string,
     *     kelebihan_centi: int,
     *     kelebihan_kg: string,
     *     persen: int,
     *     persen_bar: int,
     *     ambang_mendekati_persen: int,
     *     toleransi_centi: int,
     *     toleransi_kg: string,
     *     tarif_per_kg: int,
     *     pembulatan: int,
     *     status: string,
     *     di_bawah_toleransi: bool,
     *     jumlah_catatan: int,
     *     total_biaya_utama: int,
     *     bayar_terpisah: int,
     *     tagihan_otomatis: int,
     *     nominal_penyesuaian: ?int,
     *     catatan_penyesuaian: ?string,
     *     tagihan_lewat_kuota: int,
     *     total_tagihan: int
     * }
     */
    public function statusBulan(Penghuni|int $penghuni, ?string $bulan = null): array
    {
        $penghuniModel = $penghuni instanceof Penghuni
            ? $penghuni
            : Penghuni::query()->findOrFail($penghuni);

        $rentang = $this->rentangBulan($bulan);
        $aturan = $this->aturanBulan($penghuniModel->properti_id, $rentang['akhir']);

        $agregat = Laundry::query()
            ->where('properti_id', $penghuniModel->properti_id)
            ->where('jenis', 'penghuni')
            ->where('penghuni_id', $penghuniModel->id)
            ->whereBetween('tanggal', [$rentang['awal'], $rentang['akhir']])
            ->selectRaw('COALESCE(SUM(berat_kg), 0) AS total_kg')
            ->selectRaw('COALESCE(SUM(biaya_utama), 0) AS total_biaya_utama')
            ->selectRaw('COALESCE(SUM(item_biaya), 0) AS total_terpisah')
            ->selectRaw('COUNT(*) AS jumlah_catatan')
            ->first();

        $penyesuaian = PenyesuaianLewatKuota::query()
            ->where('penghuni_id', $penghuniModel->id)
            ->where('bulan', $rentang['bulan'])
            ->first();

        return $this->susunStatus(
            penghuniId: $penghuniModel->id,
            rentang: $rentang,
            aturan: $aturan,
            terpakaiCenti: $this->hargaService->keCentiKg((string) ($agregat?->total_kg ?? '0')),
            totalBiayaUtama: (int) ($agregat?->total_biaya_utama ?? 0),
            bayarTerpisah: (int) ($agregat?->total_terpisah ?? 0),
            jumlahCatatan: (int) ($agregat?->jumlah_catatan ?? 0),
            penyesuaian: $penyesuaian,
        );
    }

    /**
     * Ringkas kuota seluruh penghuni dalam satu properti untuk bulan tertentu menggunakan 1 query GROUP BY.
     *
     * @param  array<int, int>|null  $penghuniIds
     * @return Collection<int, array<string, mixed>>
     */
    public function ringkasanSemua(
        Properti|int $properti,
        ?string $bulan = null,
        ?array $penghuniIds = null
    ): Collection {
        $propertiId = $properti instanceof Properti ? $properti->id : $properti;
        $rentang = $this->rentangBulan($bulan);
        $aturan = $this->aturanBulan($propertiId, $rentang['akhir']);

        if ($penghuniIds === []) {
            return collect();
        }

        $queryLaundry = Laundry::query()
            ->where('properti_id', $propertiId)
            ->where('jenis', 'penghuni')
            ->whereNotNull('penghuni_id')
            ->whereBetween('tanggal', [$rentang['awal'], $rentang['akhir']])
            ->when($penghuniIds !== null, fn ($q) => $q->whereIn('penghuni_id', $penghuniIds))
            ->groupBy('penghuni_id')
            ->select('penghuni_id')
            ->selectRaw('COALESCE(SUM(berat_kg), 0) AS total_kg')
            ->selectRaw('COALESCE(SUM(biaya_utama), 0) AS total_biaya_utama')
            ->selectRaw('COALESCE(SUM(item_biaya), 0) AS total_terpisah')
            ->selectRaw('COUNT(*) AS jumlah_catatan')
            ->get()
            ->keyBy('penghuni_id');

        $daftarPenyesuaian = PenyesuaianLewatKuota::query()
            ->where('properti_id', $propertiId)
            ->where('bulan', $rentang['bulan'])
            ->when($penghuniIds !== null, fn ($q) => $q->whereIn('penghuni_id', $penghuniIds))
            ->get()
            ->keyBy('penghuni_id');

        $targetIds = $penghuniIds ?? $queryLaundry->keys()->merge($daftarPenyesuaian->keys())->unique()->values()->all();

        $hasil = collect();

        foreach ($targetIds as $penghuniId) {
            $row = $queryLaundry->get($penghuniId);
            $penyesuaian = $daftarPenyesuaian->get($penghuniId);

            $hasil->put((int) $penghuniId, $this->susunStatus(
                penghuniId: (int) $penghuniId,
                rentang: $rentang,
                aturan: $aturan,
                terpakaiCenti: $this->hargaService->keCentiKg((string) ($row?->total_kg ?? '0')),
                totalBiayaUtama: (int) ($row?->total_biaya_utama ?? 0),
                bayarTerpisah: (int) ($row?->total_terpisah ?? 0),
                jumlahCatatan: (int) ($row?->jumlah_catatan ?? 0),
                penyesuaian: $penyesuaian,
            ));
        }

        return $hasil;
    }

    /**
     * Ambil riwayat kuota beberapa bulan terakhir untuk seorang penghuni secara efisien.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function riwayatBulanan(Penghuni $penghuni, int $jumlahBulan = 6, ?string $sampaiBulan = null): Collection
    {
        $akhir = $sampaiBulan !== null && preg_match('/^\d{4}-\d{2}$/', $sampaiBulan)
            ? CarbonImmutable::createFromFormat('Y-m-d', "{$sampaiBulan}-01", 'Asia/Jakarta')
            : now('Asia/Jakarta')->toImmutable()->startOfMonth();

        $awal = $akhir->subMonths($jumlahBulan - 1)->startOfMonth();

        $rows = Laundry::query()
            ->where('properti_id', $penghuni->properti_id)
            ->where('jenis', 'penghuni')
            ->where('penghuni_id', $penghuni->id)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->endOfMonth()->toDateString()])
            ->selectRaw("DATE_FORMAT(tanggal, '%Y-%m') AS bulan")
            ->selectRaw('COALESCE(SUM(berat_kg), 0) AS total_kg')
            ->selectRaw('COALESCE(SUM(biaya_utama), 0) AS total_biaya_utama')
            ->selectRaw('COALESCE(SUM(item_biaya), 0) AS total_terpisah')
            ->selectRaw('COUNT(*) AS jumlah_catatan')
            ->groupByRaw("DATE_FORMAT(tanggal, '%Y-%m')")
            ->get()
            ->keyBy('bulan');

        $penyesuaianPerBulan = PenyesuaianLewatKuota::query()
            ->where('penghuni_id', $penghuni->id)
            ->whereBetween('bulan', [$awal->format('Y-m'), $akhir->format('Y-m')])
            ->get()
            ->keyBy('bulan');

        $daftar = collect();

        for ($i = 0; $i < $jumlahBulan; $i++) {
            $bulanIni = $akhir->subMonths($i);
            $kodeBulan = $bulanIni->format('Y-m');
            $rentang = $this->rentangBulan($kodeBulan);
            $aturan = $this->aturanBulan($penghuni->properti_id, $rentang['akhir']);
            $row = $rows->get($kodeBulan);

            $daftar->push($this->susunStatus(
                penghuniId: $penghuni->id,
                rentang: $rentang,
                aturan: $aturan,
                terpakaiCenti: $this->hargaService->keCentiKg((string) ($row?->total_kg ?? '0')),
                totalBiayaUtama: (int) ($row?->total_biaya_utama ?? 0),
                bayarTerpisah: (int) ($row?->total_terpisah ?? 0),
                jumlahCatatan: (int) ($row?->jumlah_catatan ?? 0),
                penyesuaian: $penyesuaianPerBulan->get($kodeBulan),
            ));
        }

        return $daftar;
    }

    /**
     * Simpan atau hapus penyesuaian manual tagihan lewat kuota per penghuni per bulan.
     */
    public function simpanPenyesuaian(
        Penghuni $penghuni,
        string $bulan,
        ?int $nominal,
        ?string $catatan,
        User $aktor
    ): ?PenyesuaianLewatKuota {
        return DB::transaction(function () use ($penghuni, $bulan, $nominal, $catatan, $aktor): ?PenyesuaianLewatKuota {
            $sebelumModel = PenyesuaianLewatKuota::query()
                ->where('penghuni_id', $penghuni->id)
                ->where('bulan', $bulan)
                ->lockForUpdate()
                ->first();

            $sebelum = $sebelumModel?->only(['nominal', 'catatan', 'oleh']);

            if ($nominal === null) {
                if ($sebelumModel !== null) {
                    $sebelumModel->delete();

                    $this->auditService->catat(
                        propertiId: $penghuni->properti_id,
                        aktor: $aktor,
                        aksi: 'kuota_hapus_penyesuaian',
                        subjek: $penghuni,
                        sebelum: ['bulan' => $bulan, ...($sebelum ?? [])],
                        sesudah: ['bulan' => $bulan, 'nominal' => null],
                    );
                }

                return null;
            }

            $penyesuaian = PenyesuaianLewatKuota::query()->updateOrCreate(
                [
                    'penghuni_id' => $penghuni->id,
                    'bulan' => $bulan,
                ],
                [
                    'properti_id' => $penghuni->properti_id,
                    'nominal' => $nominal,
                    'catatan' => $catatan,
                    'oleh' => $aktor->id,
                ]
            );

            $this->auditService->catat(
                propertiId: $penghuni->properti_id,
                aktor: $aktor,
                aksi: 'kuota_simpan_penyesuaian',
                subjek: $penghuni,
                sebelum: $sebelum !== null ? ['bulan' => $bulan, ...$sebelum] : null,
                sesudah: [
                    'bulan' => $bulan,
                    'nominal' => $penyesuaian->nominal,
                    'catatan' => $penyesuaian->catatan,
                ],
            );

            return $penyesuaian;
        });
    }

    /**
     * @return array{
     *     mode_kuota: string,
     *     kuota_centi: int,
     *     ambang_persen: int,
     *     toleransi_centi: int,
     *     tarif_per_kg: int,
     *     pembulatan: int
     * }
     */
    private function aturanBulan(int $propertiId, string $tanggalAcuan): array
    {
        $semua = $this->pengaturanService->semuaPada($propertiId, $tanggalAcuan);

        return [
            'mode_kuota' => $semua['mode_kuota'] ?? 'penghuni',
            'kuota_centi' => $this->hargaService->keCentiKg($semua['kuota_kg'] ?? '20'),
            'ambang_persen' => (int) ($semua['ambang_mendekati_persen'] ?? 75),
            'toleransi_centi' => $this->hargaService->keCentiKg($semua['toleransi_kg'] ?? '1'),
            'tarif_per_kg' => (int) ($semua['tarif_per_kg'] ?? 8000),
            'pembulatan' => (int) ($semua['pembulatan'] ?? 1000),
        ];
    }

    /**
     * @param  array{bulan: string, awal: string, akhir: string, label: string}  $rentang
     * @param  array{mode_kuota: string, kuota_centi: int, ambang_persen: int, toleransi_centi: int, tarif_per_kg: int, pembulatan: int}  $aturan
     * @return array<string, mixed>
     */
    private function susunStatus(
        int $penghuniId,
        array $rentang,
        array $aturan,
        int $terpakaiCenti,
        int $totalBiayaUtama,
        int $bayarTerpisah,
        int $jumlahCatatan,
        ?PenyesuaianLewatKuota $penyesuaian
    ): array {
        $kuotaCenti = $aturan['kuota_centi'];
        $modeKuota = $aturan['mode_kuota'];

        if ($modeKuota === 'tanpa_kuota') {
            return [
                'penghuni_id' => $penghuniId,
                'bulan' => $rentang['bulan'],
                'label_bulan' => $rentang['label'],
                'mode_kuota' => $modeKuota,
                'terpakai_centi' => $terpakaiCenti,
                'terpakai_kg' => $this->hargaService->dariCentiKg($terpakaiCenti, true),
                'kuota_centi' => 0,
                'kuota_kg' => '0',
                'sisa_centi' => 0,
                'sisa_kg' => '0',
                'kelebihan_centi' => 0,
                'kelebihan_kg' => '0',
                'persen' => 0,
                'persen_bar' => 0,
                'ambang_mendekati_persen' => $aturan['ambang_persen'],
                'toleransi_centi' => $aturan['toleransi_centi'],
                'toleransi_kg' => $this->hargaService->dariCentiKg($aturan['toleransi_centi'], true),
                'tarif_per_kg' => $aturan['tarif_per_kg'],
                'pembulatan' => $aturan['pembulatan'],
                'status' => 'normal',
                'di_bawah_toleransi' => false,
                'jumlah_catatan' => $jumlahCatatan,
                'total_biaya_utama' => $totalBiayaUtama,
                'bayar_terpisah' => $bayarTerpisah,
                'tagihan_otomatis' => 0,
                'nominal_penyesuaian' => null,
                'catatan_penyesuaian' => null,
                'tagihan_lewat_kuota' => 0,
                'total_tagihan' => $bayarTerpisah,
            ];
        }

        $sisaCenti = max(0, $kuotaCenti - $terpakaiCenti);
        $kelebihanCenti = max(0, $terpakaiCenti - $kuotaCenti);
        $persen = $kuotaCenti > 0
            ? intdiv(($terpakaiCenti * 100) + intdiv($kuotaCenti, 2), $kuotaCenti)
            : 0;
        $ambangCenti = $kuotaCenti > 0
            ? intdiv($kuotaCenti * $aturan['ambang_persen'], 100)
            : 0;

        $status = match (true) {
            $terpakaiCenti > $kuotaCenti => 'lewat',
            $kuotaCenti > 0 && $terpakaiCenti >= $ambangCenti => 'mendekati',
            default => 'normal',
        };

        $diBawahToleransi = $kelebihanCenti > 0 && $kelebihanCenti < $aturan['toleransi_centi'];

        $tagihanOtomatis = $this->hargaService->hitungTagihanLewatKuota(
            kelebihanCenti: $kelebihanCenti,
            tarifPerKg: $aturan['tarif_per_kg'],
            toleransiCenti: $aturan['toleransi_centi'],
            pembulatan: $aturan['pembulatan'],
        );

        $nominalPenyesuaian = $penyesuaian?->nominal;
        $tagihanLewatKuota = $nominalPenyesuaian ?? $tagihanOtomatis;

        return [
            'penghuni_id' => $penghuniId,
            'bulan' => $rentang['bulan'],
            'label_bulan' => $rentang['label'],
            'mode_kuota' => $modeKuota,
            'terpakai_centi' => $terpakaiCenti,
            'terpakai_kg' => $this->hargaService->dariCentiKg($terpakaiCenti, true),
            'kuota_centi' => $kuotaCenti,
            'kuota_kg' => $this->hargaService->dariCentiKg($kuotaCenti, true),
            'sisa_centi' => $sisaCenti,
            'sisa_kg' => $this->hargaService->dariCentiKg($sisaCenti, true),
            'kelebihan_centi' => $kelebihanCenti,
            'kelebihan_kg' => $this->hargaService->dariCentiKg($kelebihanCenti, true),
            'persen' => $persen,
            'persen_bar' => min(100, max(0, $persen)),
            'ambang_mendekati_persen' => $aturan['ambang_persen'],
            'toleransi_centi' => $aturan['toleransi_centi'],
            'toleransi_kg' => $this->hargaService->dariCentiKg($aturan['toleransi_centi'], true),
            'tarif_per_kg' => $aturan['tarif_per_kg'],
            'pembulatan' => $aturan['pembulatan'],
            'status' => $status,
            'di_bawah_toleransi' => $diBawahToleransi,
            'jumlah_catatan' => $jumlahCatatan,
            'total_biaya_utama' => $totalBiayaUtama,
            'bayar_terpisah' => $bayarTerpisah,
            'tagihan_otomatis' => $tagihanOtomatis,
            'nominal_penyesuaian' => $nominalPenyesuaian,
            'catatan_penyesuaian' => $penyesuaian?->catatan,
            'tagihan_lewat_kuota' => $tagihanLewatKuota,
            'total_tagihan' => $tagihanLewatKuota + $bayarTerpisah,
        ];
    }
}
