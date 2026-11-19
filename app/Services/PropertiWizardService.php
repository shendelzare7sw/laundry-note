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
}
