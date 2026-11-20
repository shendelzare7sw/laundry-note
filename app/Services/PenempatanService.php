<?php

namespace App\Services;

use App\Models\Kamar;
use App\Models\Laundry;
use App\Models\Penempatan;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenempatanService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Normalisasi nomor WhatsApp ke format 62xxx (hanya digit).
     */
    public function normalisasiNomorWa(?string $nomor): string
    {
        $digit = preg_replace('/\D+/', '', trim((string) $nomor)) ?? '';

        if ($digit === '') {
            return '';
        }

        if (str_starts_with($digit, '0')) {
            return '62'.substr($digit, 1);
        }

        if (str_starts_with($digit, '8')) {
            return '62'.$digit;
        }

        return $digit;
    }

    /**
     * Kembalikan pesan peringatan bila panjang nomor WhatsApp (format 62) tidak lazim, atau null jika normal.
     */
    public function peringatanNomorWa(string $nomorNormal): ?string
    {
        if ($nomorNormal === '') {
            return null;
        }

        $panjang = strlen($nomorNormal);

        if ($panjang < 11 || $panjang > 14) {
            return "Panjang nomor WhatsApp tidak lazim ({$panjang} digit dalam format 62). Pastikan nomor bisa dihubungi.";
        }

        return null;
    }

    /**
     * Daftarkan penghuni baru sekaligus membuka penempatan kamar pertamanya secara atomik.
     *
     * @param  array{
     *     nama: string,
     *     alias?: ?string,
     *     no_wa?: ?string,
     *     kamar_id: int,
     *     tanggal_masuk: string,
     *     tanggal_masuk_perkiraan?: bool,
     *     catatan?: ?string
     * }  $data
     * @return array{penghuni: Penghuni, penempatan: Penempatan, peringatan: ?string}
     */
    public function masuk(Properti $properti, array $data, User $aktor): array
    {
        return DB::transaction(function () use ($properti, $data, $aktor): array {
            $kamar = Kamar::query()
                ->where('properti_id', $properti->id)
                ->whereKey((int) $data['kamar_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $kamar->aktif) {
                throw ValidationException::withMessages([
                    'kamar_id' => "Kamar {$kamar->nomor} sedang nonaktif dan tidak dapat ditempati.",
                ]);
            }

            $tanggalMasuk = CarbonImmutable::parse($data['tanggal_masuk'], 'Asia/Jakarta')->toDateString();

            $adaBentrok = Penempatan::query()
                ->where('kamar_id', $kamar->id)
                ->where(function ($query) use ($tanggalMasuk): void {
                    $query->whereNull('selesai')
                        ->orWhereDate('selesai', '>=', $tanggalMasuk);
                })
                ->lockForUpdate()
                ->exists();

            if ($adaBentrok) {
                throw ValidationException::withMessages([
                    'kamar_id' => "Kamar {$kamar->nomor} sedang ditempati atau memiliki jadwal bertabrakan pada tanggal tersebut.",
                ]);
            }

            $noWa = $this->normalisasiNomorWa($data['no_wa'] ?? null);
            $peringatan = $this->peringatanNomorWa($noWa);

            $penghuni = Penghuni::query()->create([
                'properti_id' => $properti->id,
                'nama' => trim($data['nama']),
                'alias' => $this->trimNullable($data['alias'] ?? null),
                'no_wa' => $noWa !== '' ? $noWa : null,
                'status' => 'aktif',
                'tanggal_masuk' => $tanggalMasuk,
                'tanggal_masuk_perkiraan' => (bool) ($data['tanggal_masuk_perkiraan'] ?? false),
                'catatan' => $this->trimNullable($data['catatan'] ?? null),
            ]);

            $penempatan = Penempatan::query()->create([
                'properti_id' => $properti->id,
                'penghuni_id' => $penghuni->id,
                'kamar_id' => $kamar->id,
                'mulai' => $tanggalMasuk,
                'selesai' => null,
                'sebab' => 'masuk',
                'dibuat_oleh' => $aktor->id,
            ]);

            $this->auditService->catat(
                propertiId: $properti->id,
                aktor: $aktor,
                aksi: 'penghuni_masuk',
                subjek: $penghuni,
                sesudah: [
                    'nama' => $penghuni->nama,
                    'kamar' => $kamar->nomor,
                    'no_wa' => $penghuni->no_wa,
                    'tanggal_masuk' => $tanggalMasuk,
                ],
            );

            return [
                'penghuni' => $penghuni,
                'penempatan' => $penempatan,
                'peringatan' => $peringatan,
            ];
        });
    }

    /**
     * Pindahkan penghuni aktif ke kamar lain tanpa mereset kuota bulan berjalan.
     */
    public function pindahKamar(
        Penghuni $penghuni,
        Kamar $kamarTujuan,
        string $tanggalPindah,
        User $aktor
    ): Penempatan {
        return DB::transaction(function () use ($penghuni, $kamarTujuan, $tanggalPindah, $aktor): Penempatan {
            $penghuniTerkunci = Penghuni::query()
                ->whereKey($penghuni->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($penghuniTerkunci->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'tanggal_pindah' => 'Hanya penghuni berstatus aktif yang dapat dipindahkan kamarnya.',
                ]);
            }

            $kamarTerkunci = Kamar::query()
                ->where('properti_id', $penghuniTerkunci->properti_id)
                ->whereKey($kamarTujuan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $kamarTerkunci->aktif) {
                throw ValidationException::withMessages([
                    'kamar_tujuan_id' => "Kamar tujuan {$kamarTerkunci->nomor} sedang nonaktif.",
                ]);
            }

            $penempatanAktif = Penempatan::query()
                ->with('kamar')
                ->where('penghuni_id', $penghuniTerkunci->id)
                ->whereNull('selesai')
                ->lockForUpdate()
                ->first();

            if ($penempatanAktif === null) {
                throw ValidationException::withMessages([
                    'kamar_tujuan_id' => 'Penghuni tidak memiliki penempatan kamar aktif.',
                ]);
            }

            if ($penempatanAktif->kamar_id === $kamarTerkunci->id) {
                throw ValidationException::withMessages([
                    'kamar_tujuan_id' => "Penghuni sudah menempati kamar {$kamarTerkunci->nomor}.",
                ]);
            }

            $tglPindah = CarbonImmutable::parse($tanggalPindah, 'Asia/Jakarta')->startOfDay();
            $mulaiSaatIni = CarbonImmutable::parse($penempatanAktif->mulai->toDateString(), 'Asia/Jakarta')->startOfDay();

            if ($tglPindah->lte($mulaiSaatIni)) {
                throw ValidationException::withMessages([
                    'tanggal_pindah' => 'Tanggal pindah harus setelah tanggal mulai penempatan saat ini ('.$mulaiSaatIni->format('d/m/Y').').',
                ]);
            }

            if ($penghuniTerkunci->tanggal_keluar !== null) {
                $tglKeluar = CarbonImmutable::parse($penghuniTerkunci->tanggal_keluar->toDateString(), 'Asia/Jakarta')->startOfDay();

                if ($tglPindah->gt($tglKeluar)) {
                    throw ValidationException::withMessages([
                        'tanggal_pindah' => 'Tanggal pindah tidak boleh melewati tanggal keluar.',
                    ]);
                }
            }

            $tglPindahStr = $tglPindah->toDateString();

            $tujuanBentrok = Penempatan::query()
                ->where('kamar_id', $kamarTerkunci->id)
                ->where(function ($query) use ($tglPindahStr): void {
                    $query->whereNull('selesai')
                        ->orWhereDate('selesai', '>=', $tglPindahStr);
                })
                ->lockForUpdate()
                ->exists();

            if ($tujuanBentrok) {
                throw ValidationException::withMessages([
                    'kamar_tujuan_id' => "Kamar {$kamarTerkunci->nomor} sedang ditempati atau memiliki jadwal bertabrakan pada tanggal {$tglPindah->format('d/m/Y')}.",
                ]);
            }

            $kamarAsalNomor = $penempatanAktif->kamar?->nomor;
            $selesaiLama = $tglPindah->subDay()->toDateString();

            $penempatanAktif->update([
                'selesai' => $selesaiLama,
            ]);

            $penempatanBaru = Penempatan::query()->create([
                'properti_id' => $penghuniTerkunci->properti_id,
                'penghuni_id' => $penghuniTerkunci->id,
                'kamar_id' => $kamarTerkunci->id,
                'mulai' => $tglPindahStr,
                'selesai' => null,
                'sebab' => 'pindah',
                'dibuat_oleh' => $aktor->id,
            ]);

            $this->auditService->catat(
                propertiId: $penghuniTerkunci->properti_id,
                aktor: $aktor,
                aksi: 'penghuni_pindah_kamar',
                subjek: $penghuniTerkunci,
                sebelum: [
                    'kamar' => $kamarAsalNomor,
                    'mulai' => $mulaiSaatIni->toDateString(),
                    'selesai_lama' => $selesaiLama,
                ],
                sesudah: [
                    'kamar' => $kamarTerkunci->nomor,
                    'mulai' => $tglPindahStr,
                ],
            );

            return $penempatanBaru;
        });
    }

    /**
     * Batalkan perpindahan kamar terakhir apabila belum ada catatan laundry di kamar baru pada/setelah tanggal pindah.
     */
    public function batalkanPindah(Penghuni $penghuni, User $aktor): Penempatan
    {
        return DB::transaction(function () use ($penghuni, $aktor): Penempatan {
            $penghuniTerkunci = Penghuni::query()
                ->whereKey($penghuni->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($penghuniTerkunci->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'batalkan_pindah' => 'Perpindahan kamar hanya dapat dibatalkan untuk penghuni aktif.',
                ]);
            }

            $daftarPenempatan = Penempatan::query()
                ->with('kamar')
                ->where('penghuni_id', $penghuniTerkunci->id)
                ->orderByDesc('mulai')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            $terbaru = $daftarPenempatan->get(0);
            $sebelumnya = $daftarPenempatan->get(1);

            if ($terbaru === null || $terbaru->selesai !== null || $terbaru->sebab !== 'pindah' || $sebelumnya === null) {
                throw ValidationException::withMessages([
                    'batalkan_pindah' => 'Tidak ada perpindahan kamar aktif yang dapat dibatalkan.',
                ]);
            }

            $mulaiBaruStr = $terbaru->mulai->toDateString();

            $adaLaundryBaru = Laundry::query()
                ->where('properti_id', $penghuniTerkunci->properti_id)
                ->where('penghuni_id', $penghuniTerkunci->id)
                ->where('kamar_id', $terbaru->kamar_id)
                ->whereDate('tanggal', '>=', $mulaiBaruStr)
                ->exists();

            if ($adaLaundryBaru) {
                throw ValidationException::withMessages([
                    'batalkan_pindah' => "Sudah ada catatan laundry di kamar {$terbaru->kamar?->nomor} sejak tanggal {$terbaru->mulai->format('d/m/Y')}.",
                ]);
            }

            $kamarSebelumnya = Kamar::query()
                ->whereKey($sebelumnya->kamar_id)
                ->lockForUpdate()
                ->firstOrFail();

            $kamarSebelumnyaTerpakai = Penempatan::query()
                ->where('kamar_id', $kamarSebelumnya->id)
                ->whereKeyNot($sebelumnya->id)
                ->where(function ($query) use ($sebelumnya): void {
                    $query->whereNull('selesai')
                        ->orWhereDate('selesai', '>=', $sebelumnya->mulai->toDateString());
                })
                ->lockForUpdate()
                ->exists();

            if ($kamarSebelumnyaTerpakai) {
                throw ValidationException::withMessages([
                    'batalkan_pindah' => "Kamar asal ({$kamarSebelumnya->nomor}) sudah ditempati penghuni lain.",
                ]);
            }

            $infoTerhapus = [
                'kamar' => $terbaru->kamar?->nomor,
                'mulai' => $mulaiBaruStr,
            ];

            $terbaru->delete();
            $sebelumnya->update(['selesai' => null]);

            $this->auditService->catat(
                propertiId: $penghuniTerkunci->properti_id,
                aktor: $aktor,
                aksi: 'penghuni_batalkan_pindah',
                subjek: $penghuniTerkunci,
                sebelum: $infoTerhapus,
                sesudah: [
                    'kamar_dipulihkan' => $kamarSebelumnya->nomor,
                    'mulai' => $sebelumnya->mulai->toDateString(),
                ],
            );

            return $sebelumnya->fresh('kamar');
        });
    }

    /**
     * Akhiri masa tinggal penghuni (keluar) dan tutup penempatan kamar aktif.
     */
    public function keluar(
        Penghuni $penghuni,
        string $tanggalKeluar,
        ?string $alasanKeluar,
        User $aktor
    ): Penghuni {
        return DB::transaction(function () use ($penghuni, $tanggalKeluar, $alasanKeluar, $aktor): Penghuni {
            $penghuniTerkunci = Penghuni::query()
                ->whereKey($penghuni->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($penghuniTerkunci->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'tanggal_keluar' => 'Penghuni ini sudah berstatus keluar.',
                ]);
            }

            $tglKeluar = CarbonImmutable::parse($tanggalKeluar, 'Asia/Jakarta')->startOfDay();
            $tglMasuk = CarbonImmutable::parse($penghuniTerkunci->tanggal_masuk->toDateString(), 'Asia/Jakarta')->startOfDay();

            if ($tglKeluar->lt($tglMasuk)) {
                throw ValidationException::withMessages([
                    'tanggal_keluar' => 'Tanggal keluar tidak boleh sebelum tanggal masuk penghuni ('.$tglMasuk->format('d/m/Y').').',
                ]);
            }

            $penempatanAktif = Penempatan::query()
                ->with('kamar')
                ->where('penghuni_id', $penghuniTerkunci->id)
                ->whereNull('selesai')
                ->lockForUpdate()
                ->first();

            if ($penempatanAktif !== null) {
                $mulaiPenempatan = CarbonImmutable::parse($penempatanAktif->mulai->toDateString(), 'Asia/Jakarta')->startOfDay();

                if ($tglKeluar->lt($mulaiPenempatan)) {
                    throw ValidationException::withMessages([
                        'tanggal_keluar' => 'Tanggal keluar tidak boleh sebelum tanggal mulai penempatan aktif ('.$mulaiPenempatan->format('d/m/Y').').',
                    ]);
                }

                $penempatanAktif->update([
                    'selesai' => $tglKeluar->toDateString(),
                ]);
            }

            $penghuniTerkunci->update([
                'status' => 'keluar',
                'tanggal_keluar' => $tglKeluar->toDateString(),
                'alasan_keluar' => $this->trimNullable($alasanKeluar),
            ]);

            $this->auditService->catat(
                propertiId: $penghuniTerkunci->properti_id,
                aktor: $aktor,
                aksi: 'penghuni_keluar',
                subjek: $penghuniTerkunci,
                sebelum: [
                    'status' => 'aktif',
                    'kamar' => $penempatanAktif?->kamar?->nomor,
                ],
                sesudah: [
                    'status' => 'keluar',
                    'tanggal_keluar' => $tglKeluar->toDateString(),
                    'alasan_keluar' => $penghuniTerkunci->alasan_keluar,
                ],
            );

            return $penghuniTerkunci->fresh();
        });
    }

    /**
     * Batalkan status keluar penghuni dan buka kembali penempatan kamar terakhir jika kamar masih tersedia.
     */
    public function batalkanKeluar(Penghuni $penghuni, User $aktor): Penghuni
    {
        return DB::transaction(function () use ($penghuni, $aktor): Penghuni {
            $penghuniTerkunci = Penghuni::query()
                ->whereKey($penghuni->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($penghuniTerkunci->status !== 'keluar') {
                throw ValidationException::withMessages([
                    'batalkan_keluar' => 'Penghuni ini masih berstatus aktif.',
                ]);
            }

            $penempatanTerakhir = Penempatan::query()
                ->with('kamar')
                ->where('penghuni_id', $penghuniTerkunci->id)
                ->orderByDesc('selesai')
                ->orderByDesc('mulai')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($penempatanTerakhir === null) {
                throw ValidationException::withMessages([
                    'batalkan_keluar' => 'Riwayat penempatan kamar tidak ditemukan untuk penghuni ini.',
                ]);
            }

            $kamar = Kamar::query()
                ->whereKey($penempatanTerakhir->kamar_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $kamar->aktif) {
                throw ValidationException::withMessages([
                    'batalkan_keluar' => "Kamar {$kamar->nomor} sedang nonaktif, status keluar tidak bisa dibatalkan otomatis.",
                ]);
            }

            $kamarBentrok = Penempatan::query()
                ->where('kamar_id', $kamar->id)
                ->whereKeyNot($penempatanTerakhir->id)
                ->where(function ($query) use ($penempatanTerakhir): void {
                    $query->whereNull('selesai')
                        ->orWhereDate('selesai', '>=', $penempatanTerakhir->mulai->toDateString());
                })
                ->lockForUpdate()
                ->exists();

            if ($kamarBentrok) {
                throw ValidationException::withMessages([
                    'batalkan_keluar' => "Kamar {$kamar->nomor} sudah ditempati penghuni lain, tidak bisa membatalkan status keluar.",
                ]);
            }

            $sebelum = [
                'status' => 'keluar',
                'tanggal_keluar' => $penghuniTerkunci->tanggal_keluar?->toDateString(),
                'alasan_keluar' => $penghuniTerkunci->alasan_keluar,
            ];

            $penempatanTerakhir->update([
                'selesai' => null,
            ]);

            $penghuniTerkunci->update([
                'status' => 'aktif',
                'tanggal_keluar' => null,
                'alasan_keluar' => null,
            ]);

            $this->auditService->catat(
                propertiId: $penghuniTerkunci->properti_id,
                aktor: $aktor,
                aksi: 'penghuni_batalkan_keluar',
                subjek: $penghuniTerkunci,
                sebelum: $sebelum,
                sesudah: [
                    'status' => 'aktif',
                    'kamar' => $kamar->nomor,
                ],
            );

            return $penghuniTerkunci->fresh();
        });
    }

    /**
     * Perbarui nomor WhatsApp penghuni dengan normalisasi otomatis ke format 62.
     *
     * @return array{penghuni: Penghuni, peringatan: ?string}
     */
    public function gantiNomor(Penghuni $penghuni, ?string $noWaBaru, User $aktor): array
    {
        return DB::transaction(function () use ($penghuni, $noWaBaru, $aktor): array {
            $penghuniTerkunci = Penghuni::query()
                ->whereKey($penghuni->id)
                ->lockForUpdate()
                ->firstOrFail();

            $sebelum = $penghuniTerkunci->no_wa;
            $noWa = $this->normalisasiNomorWa($noWaBaru);
            $peringatan = $this->peringatanNomorWa($noWa);

            $penghuniTerkunci->update([
                'no_wa' => $noWa !== '' ? $noWa : null,
            ]);

            $this->auditService->catat(
                propertiId: $penghuniTerkunci->properti_id,
                aktor: $aktor,
                aksi: 'penghuni_ganti_nomor',
                subjek: $penghuniTerkunci,
                sebelum: ['no_wa' => $sebelum],
                sesudah: ['no_wa' => $penghuniTerkunci->no_wa],
            );

            return [
                'penghuni' => $penghuniTerkunci,
                'peringatan' => $peringatan,
            ];
        });
    }

    /**
     * Perbarui nama, alias, catatan, atau penanda tanggal masuk perkiraan penghuni.
     *
     * @param  array{
     *     nama: string,
     *     alias?: ?string,
     *     catatan?: ?string,
     *     tanggal_masuk_perkiraan?: bool
     * }  $data
     */
    public function ubahData(Penghuni $penghuni, array $data, User $aktor): Penghuni
    {
        return DB::transaction(function () use ($penghuni, $data, $aktor): Penghuni {
            $penghuniTerkunci = Penghuni::query()
                ->whereKey($penghuni->id)
                ->lockForUpdate()
                ->firstOrFail();

            $sebelum = $penghuniTerkunci->only(['nama', 'alias', 'catatan', 'tanggal_masuk_perkiraan']);

            $penghuniTerkunci->update([
                'nama' => trim($data['nama']),
                'alias' => $this->trimNullable($data['alias'] ?? null),
                'catatan' => $this->trimNullable($data['catatan'] ?? null),
                'tanggal_masuk_perkiraan' => array_key_exists('tanggal_masuk_perkiraan', $data)
                    ? (bool) $data['tanggal_masuk_perkiraan']
                    : $penghuniTerkunci->tanggal_masuk_perkiraan,
            ]);

            $this->auditService->catat(
                propertiId: $penghuniTerkunci->properti_id,
                aktor: $aktor,
                aksi: 'penghuni_ubah_data',
                subjek: $penghuniTerkunci,
                sebelum: $sebelum,
                sesudah: $penghuniTerkunci->only(['nama', 'alias', 'catatan', 'tanggal_masuk_perkiraan']),
            );

            return $penghuniTerkunci;
        });
    }

    /**
     * Periksa apakah tanggal berada dalam rentang masa tinggal penghuni (masuk <= tanggal <= keluar).
     */
    public function validasiTanggalDalamMasaTinggal(Penghuni $penghuni, CarbonInterface|string $tanggal): bool
    {
        $tgl = $tanggal instanceof CarbonInterface
            ? CarbonImmutable::instance($tanggal)->setTimezone('Asia/Jakarta')->startOfDay()
            : CarbonImmutable::parse($tanggal, 'Asia/Jakarta')->startOfDay();

        $tglMasuk = CarbonImmutable::parse($penghuni->tanggal_masuk->toDateString(), 'Asia/Jakarta')->startOfDay();

        if ($tgl->lt($tglMasuk)) {
            return false;
        }

        if ($penghuni->tanggal_keluar !== null) {
            $tglKeluar = CarbonImmutable::parse($penghuni->tanggal_keluar->toDateString(), 'Asia/Jakarta')->startOfDay();

            if ($tgl->gt($tglKeluar)) {
                return false;
            }
        }

        return true;
    }

    private function trimNullable(?string $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $trimmed = trim($nilai);

        return $trimmed !== '' ? $trimmed : null;
    }
}
