<?php

namespace App\Services;

use App\Models\Laundry;
use App\Models\LaundryItem;
use App\Models\Penempatan;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaundryService
{
    public function __construct(
        private readonly HargaService $hargaService,
        private readonly PengaturanService $pengaturanService,
        private readonly KuotaService $kuotaService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Catat laundry baru. Idempoten terhadap `ref`: kiriman ulang dengan ref sama mengembalikan catatan lama.
     *
     * @param  array{
     *     jenis: string,
     *     tanggal: string,
     *     penghuni_id?: int|string|null,
     *     berat_kg?: string|null,
     *     biaya_utama?: int|string|null,
     *     catatan?: string|null,
     *     ref?: string|null,
     *     item?: array<int, array{nama: string, jumlah?: int|string|null, berat_kg?: string|null, biaya?: int|string|null}>
     * }  $data
     */
    public function catat(Properti $properti, array $data, User $aktor): Laundry
    {
        $ref = $this->teksAtauNull($data['ref'] ?? null);

        if ($ref !== null && ($lama = $this->cariRef($properti, $ref)) !== null) {
            return $lama;
        }

        try {
            return DB::transaction(function () use ($properti, $data, $aktor, $ref): Laundry {
                $atribut = $this->susunAtribut($properti, $data);

                $laundry = Laundry::query()->create([
                    ...$atribut,
                    'properti_id' => $properti->id,
                    'ref' => $ref,
                    'dicatat_oleh' => $aktor->id,
                ]);

                $this->sinkronItem($laundry, $data['item'] ?? []);

                $this->auditService->catat(
                    propertiId: $properti->id,
                    aktor: $aktor,
                    aksi: 'laundry_tambah',
                    subjek: $laundry,
                    sesudah: $this->potret($laundry),
                );

                return $laundry->fresh(['item', 'penghuni', 'kamar']);
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($ref !== null && ($lama = $this->cariRef($properti, $ref)) !== null) {
                return $lama;
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function ubah(Laundry $laundry, array $data, User $aktor): Laundry
    {
        return DB::transaction(function () use ($laundry, $data, $aktor): Laundry {
            /** @var Laundry $terkunci */
            $terkunci = Laundry::query()->with('item')->lockForUpdate()->findOrFail($laundry->id);
            $properti = Properti::query()->findOrFail($terkunci->properti_id);
            $sebelum = $this->potret($terkunci);

            $terkunci->fill($this->susunAtribut($properti, $data, $terkunci->id))->save();
            $this->sinkronItem($terkunci, $data['item'] ?? []);

            $this->auditService->catat(
                propertiId: $terkunci->properti_id,
                aktor: $aktor,
                aksi: 'laundry_ubah',
                subjek: $terkunci,
                sebelum: $sebelum,
                sesudah: $this->potret($terkunci),
            );

            return $terkunci->fresh(['item', 'penghuni', 'kamar']);
        });
    }

    public function hapus(Laundry $laundry, User $aktor): void
    {
        DB::transaction(function () use ($laundry, $aktor): void {
            $terkunci = Laundry::query()->with('item')->lockForUpdate()->findOrFail($laundry->id);
            $sebelum = $this->potret($terkunci);
            $terkunci->delete();

            $this->auditService->catat(
                propertiId: $terkunci->properti_id,
                aktor: $aktor,
                aksi: 'laundry_hapus',
                subjek: $terkunci,
                sebelum: $sebelum,
            );
        });
    }

    public function pulihkan(Laundry $laundry, User $aktor): Laundry
    {
        return DB::transaction(function () use ($laundry, $aktor): Laundry {
            /** @var Laundry $terkunci */
            $terkunci = Laundry::query()->onlyTrashed()->lockForUpdate()->findOrFail($laundry->id);

            if ($terkunci->jenis === 'penghuni' && $terkunci->penghuni_id !== null) {
                $this->pastikanTidakDuplikatTanggal(
                    $terkunci->properti_id,
                    $terkunci->penghuni_id,
                    $terkunci->tanggal->toDateString(),
                    $terkunci->id,
                    $terkunci->penghuni?->nama ?? 'Penghuni',
                );
            }

            $terkunci->restore();

            $this->auditService->catat(
                propertiId: $terkunci->properti_id,
                aktor: $aktor,
                aksi: 'laundry_pulihkan',
                subjek: $terkunci,
                sesudah: $this->potret($terkunci->load('item')),
            );

            return $terkunci;
        });
    }

    /**
     * Tetapkan catatan "tanpa nama" ke seorang penghuni; kuota penghuni ikut bertambah.
     */
    public function tetapkanPemilik(Laundry $laundry, Penghuni $penghuni, User $aktor): Laundry
    {
        return DB::transaction(function () use ($laundry, $penghuni, $aktor): Laundry {
            /** @var Laundry $terkunci */
            $terkunci = Laundry::query()->with('item')->lockForUpdate()->findOrFail($laundry->id);

            if ($terkunci->jenis !== 'tanpa_nama') {
                throw ValidationException::withMessages([
                    'penghuni_id' => 'Hanya catatan tanpa nama yang dapat ditetapkan ke penghuni.',
                ]);
            }

            $penempatan = $this->pastikanPenempatan($terkunci->properti_id, $penghuni->id, $terkunci->tanggal->toDateString());
            $this->pastikanTidakDuplikatTanggal(
                $terkunci->properti_id,
                $penghuni->id,
                $terkunci->tanggal->toDateString(),
                $terkunci->id,
                $penghuni->nama,
            );
            $sebelum = $this->potret($terkunci);

            $terkunci->fill([
                'jenis' => 'penghuni',
                'penghuni_id' => $penghuni->id,
                'kamar_id' => $penempatan->kamar_id,
            ])->save();

            $this->auditService->catat(
                propertiId: $terkunci->properti_id,
                aktor: $aktor,
                aksi: 'laundry_tetapkan_pemilik',
                subjek: $terkunci,
                sebelum: $sebelum,
                sesudah: $this->potret($terkunci),
            );

            return $terkunci->fresh(['item', 'penghuni', 'kamar']);
        });
    }

    /**
     * Pratinjau catatan berberat yang biaya utamanya kosong/0 beserta biaya otomatis hasil hitung ulang.
     *
     * @return array{jumlah: int, total: int, daftar: Collection<int, array{id: int, tanggal: string, berat_kg: string, biaya_baru: int}>}
     */
    public function pratinjauLengkapiHarga(Properti $properti): array
    {
        $daftar = $this->kueriHargaKosong($properti)
            ->get(['id', 'properti_id', 'tanggal', 'berat_kg'])
            ->map(fn (Laundry $laundry): array => [
                'id' => $laundry->id,
                'tanggal' => $laundry->tanggal->toDateString(),
                'berat_kg' => (string) $laundry->berat_kg,
                'biaya_baru' => $this->biayaOtomatis($properti->id, $laundry->tanggal->toDateString(), (string) $laundry->berat_kg),
            ]);

        return [
            'jumlah' => $daftar->count(),
            'total' => (int) $daftar->sum('biaya_baru'),
            'daftar' => $daftar,
        ];
    }

    /**
     * Isi biaya utama otomatis hanya untuk catatan yang biayanya kosong atau 0. Mengembalikan jumlah yang diisi.
     */
    public function lengkapiHarga(Properti $properti, User $aktor): int
    {
        return DB::transaction(function () use ($properti, $aktor): int {
            $daftar = $this->kueriHargaKosong($properti)->lockForUpdate()->get();
            $rincian = [];

            foreach ($daftar as $laundry) {
                $biaya = $this->biayaOtomatis($properti->id, $laundry->tanggal->toDateString(), (string) $laundry->berat_kg);
                $laundry->forceFill(['biaya_utama' => $biaya, 'biaya_manual' => false])->save();
                $rincian[] = ['id' => $laundry->id, 'biaya_utama' => $biaya];
            }

            if ($rincian !== []) {
                $this->auditService->catat(
                    propertiId: $properti->id,
                    aktor: $aktor,
                    aksi: 'laundry_lengkapi_harga',
                    subjek: Laundry::class,
                    sesudah: ['jumlah' => count($rincian), 'rincian' => $rincian],
                );
            }

            return count($rincian);
        });
    }

    /**
     * Biaya utama otomatis `max(berat, min_kg) × tarif` memakai aturan berversi pada tanggal catatan.
     */
    public function biayaOtomatis(int $propertiId, string $tanggal, string $beratKg): int
    {
        $aturan = $this->pengaturanService->semuaPada($propertiId, $tanggal);

        return $this->hargaService->hitungBiayaUtama(
            $beratKg,
            (int) ($aturan['tarif_per_kg'] ?? 8000),
            (string) ($aturan['min_kg'] ?? '2'),
        );
    }

    /**
     * Data teks bantu formulir: kamar pada tanggal itu, biaya otomatis, dan kuota sebelum/sesudah catatan.
     *
     * @return array<string, mixed>
     */
    public function pratinjau(
        Properti $properti,
        string $tanggal,
        ?Penghuni $penghuni,
        ?string $beratKg,
        ?Laundry $kecuali = null
    ): array {
        $aturan = $this->pengaturanService->semuaPada($properti->id, $tanggal);
        $beratCenti = $this->beratKeCenti($beratKg);
        $hasil = [
            'tarif_per_kg' => (int) ($aturan['tarif_per_kg'] ?? 8000),
            'min_kg' => (string) ($aturan['min_kg'] ?? '2'),
            'biaya_otomatis' => $beratCenti > 0
                ? $this->biayaOtomatis($properti->id, $tanggal, $this->hargaService->dariCentiKg($beratCenti))
                : null,
            'kamar' => null,
            'kuota' => null,
            'galat' => null,
            'duplikat' => null,
        ];

        if ($penghuni === null) {
            return $hasil;
        }

        $penempatan = $this->penempatanPada($properti->id, $penghuni->id, $tanggal);

        if ($penempatan === null) {
            $hasil['galat'] = "{$penghuni->nama} tidak tercatat menempati kamar pada tanggal ini.";

            return $hasil;
        }

        $hasil['kamar'] = $penempatan->kamar?->nomor;

        $duplikat = $this->cariDuplikatTanggal($properti->id, $penghuni->id, $tanggal, $kecuali?->id);

        if ($duplikat !== null) {
            $tglTampil = CarbonImmutable::parse($tanggal, 'Asia/Jakarta')->format('d/m/Y');
            $hasil['duplikat'] = [
                'id' => $duplikat->id,
                'url_ubah' => route('laundry.edit', $duplikat),
            ];
            $hasil['galat'] = "{$penghuni->nama} sudah memiliki catatan laundry pada {$tglTampil}. Satu penghuni hanya 1 catatan per tanggal; silakan ubah catatan yang sudah ada.";
        }

        $bulan = CarbonImmutable::parse($tanggal, 'Asia/Jakarta')->format('Y-m');
        $status = $this->kuotaService->statusBulan($penghuni, $bulan);

        if ($status['mode_kuota'] === 'tanpa_kuota') {
            $hasil['kuota'] = ['mode_kuota' => 'tanpa_kuota', 'label_bulan' => $status['label_bulan']];

            return $hasil;
        }

        $sebelumCenti = $status['terpakai_centi'];

        if ($kecuali !== null
            && $kecuali->jenis === 'penghuni'
            && $kecuali->penghuni_id === $penghuni->id
            && $kecuali->tanggal->format('Y-m') === $bulan
            && $kecuali->berat_kg !== null) {
            $sebelumCenti -= $this->hargaService->keCentiKg((string) $kecuali->berat_kg);
        }

        $sesudahCenti = $sebelumCenti + $beratCenti;
        $kuotaCenti = $status['kuota_centi'];
        $ambangCenti = intdiv($kuotaCenti * $status['ambang_mendekati_persen'], 100);

        $hasil['kuota'] = [
            'mode_kuota' => 'penghuni',
            'label_bulan' => $status['label_bulan'],
            'kuota_kg' => $status['kuota_kg'],
            'sebelum_kg' => $this->hargaService->dariCentiKg($sebelumCenti, true),
            'sesudah_kg' => $this->hargaService->dariCentiKg($sesudahCenti, true),
            'sisa_kg' => $this->hargaService->dariCentiKg(max(0, $kuotaCenti - $sesudahCenti), true),
            'lewat_kg' => $this->hargaService->dariCentiKg(max(0, $sesudahCenti - $kuotaCenti), true),
            'status' => match (true) {
                $sesudahCenti > $kuotaCenti => 'lewat',
                $kuotaCenti > 0 && $sesudahCenti >= $ambangCenti => 'mendekati',
                default => 'normal',
            },
        ];

        return $hasil;
    }

    /**
     * Penempatan kamar penghuni yang berlaku pada tanggal tertentu (mulai <= tanggal <= selesai).
     */
    public function penempatanPada(int $propertiId, int $penghuniId, string $tanggal): ?Penempatan
    {
        return Penempatan::query()
            ->with('kamar')
            ->where('properti_id', $propertiId)
            ->where('penghuni_id', $penghuniId)
            ->whereDate('mulai', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('selesai')->orWhereDate('selesai', '>=', $tanggal))
            ->orderByDesc('mulai')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function susunAtribut(Properti $properti, array $data, ?int $kecualiId = null): array
    {
        $jenis = (string) ($data['jenis'] ?? 'penghuni');
        $tanggal = CarbonImmutable::parse((string) $data['tanggal'], 'Asia/Jakarta')->toDateString();
        $beratCenti = $this->beratKeCenti($data['berat_kg'] ?? null);
        $daftarItem = $data['item'] ?? [];

        if ($beratCenti <= 0 && $daftarItem === []) {
            throw ValidationException::withMessages([
                'berat_kg' => 'Isi berat cucian utama atau tambahkan minimal satu item terpisah.',
            ]);
        }

        $penghuniId = null;
        $kamarId = null;

        if ($jenis === 'penghuni') {
            $penghuniId = (int) ($data['penghuni_id'] ?? 0);
            $penghuni = Penghuni::query()
                ->where('properti_id', $properti->id)
                ->lockForUpdate()
                ->find($penghuniId);

            if ($penghuni === null) {
                throw ValidationException::withMessages(['penghuni_id' => 'Penghuni belum dipilih atau tidak ditemukan di properti ini.']);
            }

            if ($penghuni->status === 'booking') {
                throw ValidationException::withMessages([
                    'penghuni_id' => "{$penghuni->nama} masih berstatus Booking. Laundry baru bisa dicatat setelah kontraknya Aktif.",
                ]);
            }

            $kamarId = $this->pastikanPenempatan($properti->id, $penghuni->id, $tanggal)->kamar_id;
            $this->pastikanTidakDuplikatTanggal($properti->id, $penghuni->id, $tanggal, $kecualiId, $penghuni->nama);
        }

        $biayaUtama = null;
        $biayaManual = false;
        $beratKg = null;

        if ($beratCenti > 0) {
            $beratKg = $this->hargaService->dariCentiKg($beratCenti);
            $otomatis = $this->biayaOtomatis($properti->id, $tanggal, $beratKg);
            $input = $data['biaya_utama'] ?? null;

            if ($input === null || $input === '') {
                $biayaUtama = $otomatis;
            } else {
                $biayaUtama = (int) $input;
                $biayaManual = $biayaUtama !== $otomatis;
            }
        }

        return [
            'tanggal' => $tanggal,
            'jenis' => $jenis,
            'penghuni_id' => $penghuniId,
            'kamar_id' => $kamarId,
            'berat_kg' => $beratKg,
            'biaya_utama' => $biayaUtama,
            'biaya_manual' => $biayaManual,
            'catatan' => $this->teksAtauNull($data['catatan'] ?? null),
        ];
    }

    private function pastikanPenempatan(int $propertiId, int $penghuniId, string $tanggal): Penempatan
    {
        $penempatan = $this->penempatanPada($propertiId, $penghuniId, $tanggal);

        if ($penempatan === null) {
            $tglTampil = CarbonImmutable::parse($tanggal, 'Asia/Jakarta')->format('d/m/Y');

            throw ValidationException::withMessages([
                'tanggal' => "Penghuni tidak menempati kamar mana pun pada {$tglTampil}. Periksa tanggal masuk/keluar penghuni.",
            ]);
        }

        return $penempatan;
    }

    private function cariDuplikatTanggal(int $propertiId, int $penghuniId, string $tanggal, ?int $kecualiId = null): ?Laundry
    {
        return Laundry::query()
            ->where('properti_id', $propertiId)
            ->where('jenis', 'penghuni')
            ->where('penghuni_id', $penghuniId)
            ->whereDate('tanggal', $tanggal)
            ->when($kecualiId !== null, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->first();
    }

    private function pastikanTidakDuplikatTanggal(
        int $propertiId,
        int $penghuniId,
        string $tanggal,
        ?int $kecualiId,
        string $namaPenghuni
    ): void {
        if ($this->cariDuplikatTanggal($propertiId, $penghuniId, $tanggal, $kecualiId) === null) {
            return;
        }

        $tglTampil = CarbonImmutable::parse($tanggal, 'Asia/Jakarta')->format('d/m/Y');

        throw ValidationException::withMessages([
            'tanggal' => "{$namaPenghuni} sudah memiliki catatan laundry pada {$tglTampil}. Satu penghuni hanya boleh memiliki 1 catatan per tanggal; silakan ubah catatan yang sudah ada.",
        ]);
    }

    /**
     * Ganti seluruh item terpisah dan sinkronkan laundry.item_biaya sebagai total bayar terpisah.
     *
     * @param  array<int, array<string, mixed>>  $daftarItem
     */
    private function sinkronItem(Laundry $laundry, array $daftarItem): void
    {
        $laundry->item()->delete();
        $total = 0;

        foreach (array_values($daftarItem) as $urutan => $item) {
            $biaya = (int) ($item['biaya'] ?? 0);
            $beratCenti = $this->beratKeCenti($item['berat_kg'] ?? null);

            $laundry->item()->create([
                'nama' => trim((string) $item['nama']),
                'jumlah' => max(1, (int) ($item['jumlah'] ?? 1)),
                'berat_kg' => $beratCenti > 0 ? $this->hargaService->dariCentiKg($beratCenti) : null,
                'biaya' => $biaya,
                'urutan' => $urutan,
            ]);

            $total += $biaya;
        }

        $laundry->forceFill(['item_biaya' => $total])->save();
        $laundry->unsetRelation('item');
    }

    private function kueriHargaKosong(Properti $properti)
    {
        return Laundry::query()
            ->where('properti_id', $properti->id)
            ->whereNotNull('berat_kg')
            ->where('berat_kg', '>', 0)
            ->where(fn ($q) => $q->whereNull('biaya_utama')->orWhere('biaya_utama', 0))
            ->orderBy('tanggal')
            ->orderBy('id');
    }

    private function cariRef(Properti $properti, string $ref): ?Laundry
    {
        return Laundry::query()
            ->withTrashed()
            ->with(['item', 'penghuni', 'kamar'])
            ->where('properti_id', $properti->id)
            ->where('ref', $ref)
            ->first();
    }

    private function beratKeCenti(mixed $berat): int
    {
        if ($berat === null || trim((string) $berat) === '') {
            return 0;
        }

        return max(0, $this->hargaService->keCentiKg((string) $berat));
    }

    private function teksAtauNull(mixed $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $teks = trim((string) $nilai);

        return $teks !== '' ? $teks : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function potret(Laundry $laundry): array
    {
        $laundry->loadMissing('item');

        return [
            'tanggal' => $laundry->tanggal?->toDateString(),
            'jenis' => $laundry->jenis,
            'penghuni_id' => $laundry->penghuni_id,
            'kamar_id' => $laundry->kamar_id,
            'berat_kg' => $laundry->berat_kg !== null ? (string) $laundry->berat_kg : null,
            'biaya_utama' => $laundry->biaya_utama,
            'biaya_manual' => $laundry->biaya_manual,
            'item_biaya' => $laundry->item_biaya,
            'catatan' => $laundry->catatan,
            'item' => $laundry->item
                ->map(fn (LaundryItem $item): array => [
                    'nama' => $item->nama,
                    'jumlah' => $item->jumlah,
                    'berat_kg' => $item->berat_kg !== null ? (string) $item->berat_kg : null,
                    'biaya' => $item->biaya,
                ])
                ->values()
                ->all(),
        ];
    }
}
