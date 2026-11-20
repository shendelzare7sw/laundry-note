<?php

namespace App\Services;

use App\Enums\Peran;
use App\Models\AuditLog;
use App\Models\Kamar;
use App\Models\Pengaturan;
use App\Models\Properti;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Support\Facades\DB;

class PropertiWizardService
{
    /**
     * Salin nilai pengaturan bawaan (global atau seeder) ke properti baru secara idempoten.
     */
    public function salinPengaturanBawaan(Properti $properti, ?User $aktor = null): void
    {
        $tanggal = now('Asia/Jakarta')->toDateString();

        $bawaanGlobal = Pengaturan::query()
            ->whereNull('properti_id')
            ->orderByDesc('berlaku_mulai')
            ->get()
            ->unique('kunci')
            ->pluck('nilai', 'kunci')
            ->all();

        $nilaiAwal = array_merge(
            PengaturanSeeder::nilaiBawaan($properti->nama_properti_laporan),
            $bawaanGlobal,
            ['nama_properti' => $properti->nama_properti_laporan]
        );

        DB::transaction(function () use ($properti, $nilaiAwal, $tanggal, $aktor): void {
            foreach ($nilaiAwal as $kunci => $nilai) {
                Pengaturan::query()->updateOrCreate(
                    [
                        'properti_id' => $properti->id,
                        'kunci' => $kunci,
                        'berlaku_mulai' => $tanggal,
                    ],
                    [
                        'nilai' => (string) $nilai,
                        'diubah_oleh' => $aktor?->id,
                    ]
                );
            }
        });

        app(PengaturanService::class)->bersihkanCache($properti);
    }

    /**
     * Hasilkan daftar kamar per lantai secara idempoten.
     *
     * @param  array<int, array{lantai: int, dari: int, sampai: int}>  $daftarLantai
     */
    public function generateKamarPerLantai(Properti $properti, array $daftarLantai, ?User $aktor = null): int
    {
        $jumlahDibuat = 0;

        DB::transaction(function () use ($properti, $daftarLantai, $aktor, &$jumlahDibuat): void {
            foreach ($daftarLantai as $rentang) {
                $lantai = (int) $rentang['lantai'];
                $dari = (int) $rentang['dari'];
                $sampai = (int) $rentang['sampai'];

                for ($nomor = $dari; $nomor <= $sampai; $nomor++) {
                    $kamar = Kamar::query()->firstOrCreate(
                        [
                            'properti_id' => $properti->id,
                            'nomor' => (string) $nomor,
                        ],
                        [
                            'lantai' => $lantai,
                            'aktif' => true,
                        ]
                    );

                    if ($kamar->wasRecentlyCreated) {
                        $jumlahDibuat++;
                    }
                }
            }

            AuditLog::query()->create([
                'properti_id' => $properti->id,
                'user_id' => $aktor?->id,
                'aksi' => 'wizard_generate_kamar',
                'subjek_type' => Properti::class,
                'subjek_id' => $properti->id,
                'sesudah' => [
                    'jumlah_dibuat' => $jumlahDibuat,
                    'rentang' => $daftarLantai,
                ],
            ]);
        });

        return $jumlahDibuat;
    }

    /**
     * Simpan parameter aturan laundry awal atau pembaruan untuk properti.
     *
     * @param  array<string, scalar>  $aturan
     */
    public function simpanAturan(Properti $properti, array $aturan, User $aktor, ?string $berlakuMulai = null): void
    {
        $tanggal = $berlakuMulai ?? now('Asia/Jakarta')->toDateString();

        DB::transaction(function () use ($properti, $aturan, $aktor, $tanggal): void {
            foreach ($aturan as $kunci => $nilai) {
                Pengaturan::query()->updateOrCreate(
                    [
                        'properti_id' => $properti->id,
                        'kunci' => $kunci,
                        'berlaku_mulai' => $tanggal,
                    ],
                    [
                        'nilai' => (string) $nilai,
                        'diubah_oleh' => $aktor->id,
                    ]
                );
            }

            AuditLog::query()->create([
                'properti_id' => $properti->id,
                'user_id' => $aktor->id,
                'aksi' => 'wizard_simpan_aturan',
                'subjek_type' => Properti::class,
                'subjek_id' => $properti->id,
                'sesudah' => [
                    'berlaku_mulai' => $tanggal,
                    'aturan' => $aturan,
                ],
            ]);
        });

        app(PengaturanService::class)->bersihkanCache($properti);
    }

    /**
     * Periksa kesiapan minimum properti (minimal 1 kamar aktif dan 1 admin aktif) lalu aktifkan.
     *
     * @return array{berhasil: bool, alasan: array<int, string>}
     */
    public function aktifkanProperti(Properti $properti, ?User $aktor = null): array
    {
        $alasan = [];

        $punyaKamar = $properti->kamar()->where('aktif', true)->exists();
        if (! $punyaKamar) {
            $alasan[] = 'Properti harus memiliki minimal 1 kamar aktif sebelum diaktifkan.';
        }

        $punyaAdmin = $properti->pengguna()
            ->where('role', Peran::Admin->value)
            ->where('aktif', true)
            ->exists();

        if (! $punyaAdmin) {
            $alasan[] = 'Properti harus memiliki 1 admin properti aktif sebelum diaktifkan.';
        }

        if ($alasan !== []) {
            return ['berhasil' => false, 'alasan' => $alasan];
        }

        DB::transaction(function () use ($properti, $aktor): void {
            $sebelum = ['status' => $properti->status];
            $properti->update(['status' => 'aktif']);

            AuditLog::query()->create([
                'properti_id' => $properti->id,
                'user_id' => $aktor?->id,
                'aksi' => 'wizard_aktifkan_properti',
                'subjek_type' => Properti::class,
                'subjek_id' => $properti->id,
                'sebelum' => $sebelum,
                'sesudah' => ['status' => 'aktif'],
            ]);
        });

        return ['berhasil' => true, 'alasan' => []];
    }

    /**
     * Simpan data properti (Langkah 1), aturan laundry (Langkah 3), dan akun staf baru opsional (Langkah 4) sekaligus.
     *
     * @param  array<string, mixed>  $data
     * @return array{staf_baru: ?User, hasil_aktivasi: ?array{berhasil: bool, alasan: array<int, string>}}
     */
    public function simpanSemuaSetup(Properti $properti, array $data, User $aktor, bool $aktifkan = false): array
    {
        $stafBaru = null;
        $tanggal = now('Asia/Jakarta')->toDateString();

        $kunciAturan = [
            'tarif_per_kg',
            'min_kg',
            'kuota_kg',
            'ambang_mendekati_persen',
            'toleransi_kg',
            'pembulatan',
            'mode_kuota',
            'jam_edit_penjaga',
            'penjaga_kelola_kontrak',
        ];

        $aturan = [
            'nama_properti' => (string) $data['nama_properti_laporan'],
        ];

        foreach ($kunciAturan as $kunci) {
            if (array_key_exists($kunci, $data)) {
                $aturan[$kunci] = $data[$kunci];
            }
        }

        DB::transaction(function () use ($properti, $data, $aturan, $tanggal, $aktor, &$stafBaru): void {
            $properti->update([
                'nama' => (string) $data['nama'],
                'nama_properti_laporan' => (string) $data['nama_properti_laporan'],
                'alamat' => $data['alamat'] ?? null,
            ]);

            foreach ($aturan as $kunci => $nilai) {
                Pengaturan::query()->updateOrCreate(
                    [
                        'properti_id' => $properti->id,
                        'kunci' => $kunci,
                        'berlaku_mulai' => $tanggal,
                    ],
                    [
                        'nilai' => (string) $nilai,
                        'diubah_oleh' => $aktor->id,
                    ]
                );
            }

            if (! empty($data['name']) && ! empty($data['username']) && ! empty($data['password'])) {
                $role = Peran::from((string) $data['role']);

                $stafBaru = User::query()->create([
                    'name' => (string) $data['name'],
                    'username' => (string) $data['username'],
                    'password' => (string) $data['password'],
                    'role' => $role,
                    'properti_id' => $properti->id,
                    'aktif' => true,
                ]);
            }

            AuditLog::query()->create([
                'properti_id' => $properti->id,
                'user_id' => $aktor->id,
                'aksi' => 'wizard_simpan_semua',
                'subjek_type' => Properti::class,
                'subjek_id' => $properti->id,
                'sesudah' => [
                    'nama' => $properti->nama,
                    'nama_properti_laporan' => $properti->nama_properti_laporan,
                    'aturan' => $aturan,
                    'staf_baru' => $stafBaru?->username,
                ],
            ]);
        });

        app(PengaturanService::class)->bersihkanCache($properti);

        $statusDiminta = $aktifkan
            ? 'aktif'
            : (string) ($data['status'] ?? $properti->status);

        $hasilAktivasi = $this->ubahStatusProperti(
            properti: $properti->fresh(),
            statusBaru: $statusDiminta,
            aktor: $aktor,
            wajibSyaratAktivasi: $statusDiminta === 'aktif',
        );

        return [
            'staf_baru' => $stafBaru,
            'hasil_aktivasi' => $hasilAktivasi['alasan'] !== [] || ($aktifkan && $statusDiminta === 'aktif') ? $hasilAktivasi : null,
        ];
    }

    /**
     * Ubah status properti (aktif/draf/arsip) sambil mencabut sesi aktif staf saat dinonaktifkan.
     *
     * @return array{berhasil: bool, alasan: array<int, string>}
     */
    public function ubahStatusProperti(
        Properti $properti,
        string $statusBaru,
        ?User $aktor = null,
        bool $wajibSyaratAktivasi = false
    ): array {
        $statusLama = $properti->status;

        if ($statusBaru === $statusLama) {
            return ['berhasil' => true, 'alasan' => []];
        }

        if ($statusBaru === 'aktif' && $wajibSyaratAktivasi) {
            return $this->aktifkanProperti($properti, $aktor);
        }

        DB::transaction(function () use ($properti, $statusLama, $statusBaru, $aktor): void {
            $properti->update(['status' => $statusBaru]);

            if ($statusBaru !== 'aktif') {
                $stafIds = $properti->pengguna()->pluck('id');

                if ($stafIds->isNotEmpty()) {
                    DB::table('sessions')->whereIn('user_id', $stafIds)->delete();
                }
            }

            AuditLog::query()->create([
                'properti_id' => $properti->id,
                'user_id' => $aktor?->id,
                'aksi' => 'properti_ubah_status',
                'subjek_type' => Properti::class,
                'subjek_id' => $properti->id,
                'sebelum' => ['status' => $statusLama],
                'sesudah' => ['status' => $statusBaru],
            ]);
        });

        return ['berhasil' => true, 'alasan' => []];
    }
}
