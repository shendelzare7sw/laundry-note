<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\Properti;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Support\Facades\Cache;

class PengaturanService
{
    public function __construct(
        private readonly HargaService $hargaService,
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
