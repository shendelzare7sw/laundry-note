<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\Properti;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PengaturanService
{
    /**
     * @var array<int, string>
     */
    public const KUNCI_ATURAN = [
        'nama_properti',
        'tarif_per_kg',
        'min_kg',
        'kuota_kg',
        'ambang_mendekati_persen',
        'toleransi_kg',
        'pembulatan',
        'mode_kuota',
        'kuota_prorata_bulan_masuk',
    ];

    /**
     * @var array<int, string>
     */
    public const KUNCI_IZIN_PENJAGA = [
        'jam_edit_penjaga',
        'penjaga_kelola_kontrak',
        'penjaga_ubah_catatan_lain',
    ];

    /**
     * @var array<int, string>
     */
    public const KUNCI_TEMPLATE_WA = [
        'template_wa_tagihan',
        'template_wa_peringatan',
        'template_wa_rekap',
    ];

    public function __construct(
        private readonly HargaService $hargaService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Ambil seluruh nilai pengaturan yang berlaku untuk suatu properti pada tanggal tertentu.
     *
     * @return array<string, string>
     */
    public function semuaPada(Properti|int $properti, CarbonInterface|string|null $tanggal = null): array
    {
        $propertiId = $properti instanceof Properti ? $properti->id : $properti;
        $tanggalStr = $this->normalisasiTanggal($tanggal);
        $versi = $this->versiCache($propertiId);
        $cacheKey = "pengaturan:p{$propertiId}:v{$versi}:d{$tanggalStr}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($properti, $propertiId, $tanggalStr): array {
            $namaProperti = $properti instanceof Properti
                ? $properti->nama_properti_laporan
                : Properti::query()->whereKey($propertiId)->value('nama_properti_laporan');

            $hasil = PengaturanSeeder::nilaiBawaan(is_string($namaProperti) ? $namaProperti : null);

            $globalRows = Pengaturan::query()
                ->whereNull('properti_id')
                ->whereDate('berlaku_mulai', '<=', $tanggalStr)
                ->orderBy('berlaku_mulai')
                ->orderBy('id')
                ->get(['kunci', 'nilai']);

            foreach ($globalRows as $row) {
                $hasil[$row->kunci] = (string) $row->nilai;
            }

            $propertiRows = Pengaturan::query()
                ->where('properti_id', $propertiId)
                ->whereDate('berlaku_mulai', '<=', $tanggalStr)
                ->orderBy('berlaku_mulai')
                ->orderBy('id')
                ->get(['kunci', 'nilai']);

            foreach ($propertiRows as $row) {
                $hasil[$row->kunci] = (string) $row->nilai;
            }

            return $hasil;
        });
    }

    public function nilai(
        Properti|int $properti,
        string $kunci,
        CarbonInterface|string|null $tanggal = null,
        ?string $default = null
    ): ?string {
        $semua = $this->semuaPada($properti, $tanggal);

        return $semua[$kunci] ?? $default;
    }

    public function tarifPerKg(Properti|int $properti, CarbonInterface|string|null $tanggal = null): int
    {
        return (int) ($this->nilai($properti, 'tarif_per_kg', $tanggal, '8000') ?? 8000);
    }

    public function minKgCenti(Properti|int $properti, CarbonInterface|string|null $tanggal = null): int
    {
        return $this->hargaService->keCentiKg($this->nilai($properti, 'min_kg', $tanggal, '2') ?? '2');
    }

    public function kuotaKgCenti(Properti|int $properti, CarbonInterface|string|null $tanggal = null): int
    {
        return $this->hargaService->keCentiKg($this->nilai($properti, 'kuota_kg', $tanggal, '20') ?? '20');
    }

    public function toleransiKgCenti(Properti|int $properti, CarbonInterface|string|null $tanggal = null): int
    {
        return $this->hargaService->keCentiKg($this->nilai($properti, 'toleransi_kg', $tanggal, '1') ?? '1');
    }

    public function ambangMendekatiPersen(Properti|int $properti, CarbonInterface|string|null $tanggal = null): int
    {
        return (int) ($this->nilai($properti, 'ambang_mendekati_persen', $tanggal, '75') ?? 75);
    }

    public function pembulatan(Properti|int $properti, CarbonInterface|string|null $tanggal = null): int
    {
        return (int) ($this->nilai($properti, 'pembulatan', $tanggal, '1000') ?? 1000);
    }

    public function penjagaBolehKelolaKontrak(Properti|int $properti, CarbonInterface|string|null $tanggal = null): bool
    {
        return ($this->nilai($properti, 'penjaga_kelola_kontrak', $tanggal, '0') ?? '0') === '1';
    }

    public function penjagaBolehUbahCatatanLain(Properti|int $properti, CarbonInterface|string|null $tanggal = null): bool
    {
        return ($this->nilai($properti, 'penjaga_ubah_catatan_lain', $tanggal, '0') ?? '0') === '1';
    }

    /**
     * Simpan aturan tarif/kuota berversi pada tanggal berlaku_mulai tertentu tanpa mengubah catatan lama.
     *
     * @param  array<string, string>  $nilaiBaru
     */
    public function simpanAturanBerversi(
        Properti $properti,
        array $nilaiBaru,
        string $berlakuMulai,
        User $aktor
    ): void {
        $tanggalBerlaku = $this->normalisasiTanggal($berlakuMulai);
        $sebelum = array_intersect_key($this->semuaPada($properti, $tanggalBerlaku), array_flip(self::KUNCI_ATURAN));

        DB::transaction(function () use ($properti, $nilaiBaru, $tanggalBerlaku, $aktor, $sebelum): void {
            $sesudah = [];

            foreach (self::KUNCI_ATURAN as $kunci) {
                if (! array_key_exists($kunci, $nilaiBaru)) {
                    continue;
                }

                $nilaiStr = trim((string) $nilaiBaru[$kunci]);

                Pengaturan::query()->updateOrCreate(
                    [
                        'properti_id' => $properti->id,
                        'kunci' => $kunci,
                        'berlaku_mulai' => $tanggalBerlaku,
                    ],
                    [
                        'nilai' => $nilaiStr,
                        'diubah_oleh' => $aktor->id,
                    ]
                );

                $sesudah[$kunci] = $nilaiStr;
            }

            if (isset($sesudah['nama_properti']) && $sesudah['nama_properti'] !== '' && $tanggalBerlaku <= now('Asia/Jakarta')->toDateString()) {
                $properti->forceFill(['nama_properti_laporan' => $sesudah['nama_properti']])->save();
            }

            $this->auditService->catat(
                propertiId: $properti->id,
                aktor: $aktor,
                aksi: 'pengaturan_aturan_ubah',
                subjek: $properti,
                sebelum: ['berlaku_mulai' => $tanggalBerlaku, ...$sebelum],
                sesudah: ['berlaku_mulai' => $tanggalBerlaku, ...$sesudah],
            );
        });

        $this->bersihkanCache($properti);
    }

    /**
     * Simpan saklar izin dan batas jam edit penjaga yang langsung berlaku untuk properti ini.
     *
     * @param  array<string, string>  $nilaiBaru
     */
    public function simpanIzinPenjaga(Properti $properti, array $nilaiBaru, User $aktor): void
    {
        $hariIni = now('Asia/Jakarta')->toDateString();
        $sebelum = array_intersect_key($this->semuaPada($properti, $hariIni), array_flip(self::KUNCI_IZIN_PENJAGA));

        DB::transaction(function () use ($properti, $nilaiBaru, $aktor, $sebelum): void {
            $sesudah = [];

            foreach (self::KUNCI_IZIN_PENJAGA as $kunci) {
                if (! array_key_exists($kunci, $nilaiBaru)) {
                    continue;
                }

                $nilaiStr = trim((string) $nilaiBaru[$kunci]);

                // Hapus versi lama di atas TANGGAL_BAWAAN agar saklar izin langsung konsisten pada seluruh tanggal aktif.
                Pengaturan::query()
                    ->where('properti_id', $properti->id)
                    ->where('kunci', $kunci)
                    ->whereDate('berlaku_mulai', '>', PengaturanSeeder::TANGGAL_BAWAAN)
                    ->delete();

                Pengaturan::query()->updateOrCreate(
                    [
                        'properti_id' => $properti->id,
                        'kunci' => $kunci,
                        'berlaku_mulai' => PengaturanSeeder::TANGGAL_BAWAAN,
                    ],
                    [
                        'nilai' => $nilaiStr,
                        'diubah_oleh' => $aktor->id,
                    ]
                );

                $sesudah[$kunci] = $nilaiStr;
            }

            $this->auditService->catat(
                propertiId: $properti->id,
                aktor: $aktor,
                aksi: 'pengaturan_izin_penjaga_ubah',
                subjek: $properti,
                sebelum: $sebelum,
                sesudah: $sesudah,
            );
        });

        $this->bersihkanCache($properti);
    }

    /**
     * Simpan template pesan WhatsApp properti.
     *
     * @param  array<string, string>  $templates
     */
    public function simpanTemplateWa(Properti $properti, array $templates, User $aktor): void
    {
        $hariIni = now('Asia/Jakarta')->toDateString();
        $sebelum = array_intersect_key($this->semuaPada($properti, $hariIni), array_flip(self::KUNCI_TEMPLATE_WA));

        DB::transaction(function () use ($properti, $templates, $aktor, $sebelum): void {
            $sesudah = [];

            foreach (self::KUNCI_TEMPLATE_WA as $kunci) {
                if (! array_key_exists($kunci, $templates)) {
                    continue;
                }

                $isi = trim(str_replace("\r\n", "\n", (string) $templates[$kunci]));

                Pengaturan::query()
                    ->where('properti_id', $properti->id)
                    ->where('kunci', $kunci)
                    ->whereDate('berlaku_mulai', '>', PengaturanSeeder::TANGGAL_BAWAAN)
                    ->delete();

                Pengaturan::query()->updateOrCreate(
                    [
                        'properti_id' => $properti->id,
                        'kunci' => $kunci,
                        'berlaku_mulai' => PengaturanSeeder::TANGGAL_BAWAAN,
                    ],
                    [
                        'nilai' => $isi,
                        'diubah_oleh' => $aktor->id,
                    ]
                );

                $sesudah[$kunci] = $isi;
            }

            $this->auditService->catat(
                propertiId: $properti->id,
                aktor: $aktor,
                aksi: 'pengaturan_template_wa_ubah',
                subjek: $properti,
                sebelum: $sebelum,
                sesudah: $sesudah,
            );
        });

        $this->bersihkanCache($properti);
    }

    /**
     * Reset satu atau seluruh template WhatsApp properti ke bawaan awal.
     */
    public function resetTemplateWa(Properti $properti, ?string $kunci, User $aktor): void
    {
        $bawaan = PengaturanSeeder::nilaiBawaan($properti->nama_properti_laporan);
        $target = $kunci !== null && in_array($kunci, self::KUNCI_TEMPLATE_WA, true)
            ? [$kunci]
            : self::KUNCI_TEMPLATE_WA;

        $nilaiReset = [];

        foreach ($target as $item) {
            $nilaiReset[$item] = $bawaan[$item];
        }

        $this->simpanTemplateWa($properti, $nilaiReset, $aktor);
    }

    public function bersihkanCache(Properti|int|null $properti = null): void
    {
        $propertiId = $properti instanceof Properti ? $properti->id : (int) ($properti ?? 0);
        $versiKunci = $this->kunciVersi($propertiId);
        $versiSekarang = (int) Cache::get($versiKunci, 1);

        Cache::forever($versiKunci, $versiSekarang + 1);
    }

    private function normalisasiTanggal(CarbonInterface|string|null $tanggal): string
    {
        if ($tanggal === null || $tanggal === '') {
            return now('Asia/Jakarta')->toDateString();
        }

        if ($tanggal instanceof CarbonInterface) {
            return $tanggal->setTimezone('Asia/Jakarta')->toDateString();
        }

        return CarbonImmutable::parse($tanggal, 'Asia/Jakarta')->toDateString();
    }

    private function versiCache(int $propertiId): int
    {
        return (int) Cache::rememberForever($this->kunciVersi($propertiId), fn (): int => 1);
    }

    private function kunciVersi(int $propertiId): string
    {
        return "pengaturan:versi:{$propertiId}";
    }
}
