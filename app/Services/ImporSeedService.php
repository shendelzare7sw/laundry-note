<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Kamar;
use App\Models\Penempatan;
use App\Models\Penghuni;
use App\Models\Properti;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImporSeedService
{
    /**
     * Impor data penghuni dan penempatan awal dari berkas CSV secara idempoten.
     *
     * @return array{
     *     dry_run: bool,
     *     total_baris: int,
     *     penghuni_baru: int,
     *     penghuni_diperbarui: int,
     *     penempatan_baru: int,
     *     penempatan_diperbarui: int,
     *     anomali: array<int, string>
     * }
     */
    public function imporPenghuni(Properti $properti, string $pathCsv, bool $dryRun = false): array
    {
        if (! is_file($pathCsv) || ! is_readable($pathCsv)) {
            throw new RuntimeException("Berkas CSV seed tidak ditemukan atau tidak dapat dibaca: {$pathCsv}");
        }

        $baris = $this->bacaCsv($pathCsv);
        $anomali = [];
        $dataTerurai = [];
        $penempatanTertutupPerKamar = [];

        foreach ($baris as $row) {
            $nomorKamar = trim((string) ($row['kamar'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? ''));
            $alias = trim((string) ($row['alias'] ?? ''));
            $catatan = trim((string) ($row['catatan'] ?? ''));
            $tanggalMasukCsv = trim((string) ($row['tanggal_masuk'] ?? ''));
            $penempatanSebelumnyaRaw = trim((string) ($row['penempatan_sebelumnya'] ?? ''));

            $noWa = $this->normalisasiNomorWa((string) ($row['no_wa_wame'] ?? $row['no_wa_asli'] ?? ''));

            if ($noWa !== '' && (strlen($noWa) < 12 || strlen($noWa) > 13)) {
                $anomali[] = "Kamar {$nomorKamar} ({$nama}): panjang nomor WhatsApp tidak lazim (".strlen($noWa).' digit format 62).';
            }

            if ($alias !== '') {
                $anomali[] = "Kamar {$nomorKamar} ({$nama}): memiliki nama alternatif/alias '{$alias}'.";
            }

            $penempatanList = [];

            if ($penempatanSebelumnyaRaw !== '') {
                $bagian = explode('|', $penempatanSebelumnyaRaw);

                if (count($bagian) !== 3) {
                    throw new RuntimeException("Format penempatan_sebelumnya tidak valid untuk {$nama}: {$penempatanSebelumnyaRaw}");
                }

                [$kamarLama, $mulaiLama, $selesaiLama] = array_map('trim', $bagian);

                $penempatanList[] = [
                    'kamar' => $kamarLama,
                    'mulai' => $mulaiLama,
                    'selesai' => $selesaiLama,
                    'sebab' => 'masuk',
                ];

                $penempatanTertutupPerKamar[$kamarLama][] = [
                    'nama' => $nama,
                    'mulai' => $mulaiLama,
                    'selesai' => $selesaiLama,
                ];
            }

            $penempatanList[] = [
                'kamar' => $nomorKamar,
                'mulai' => $tanggalMasukCsv,
                'selesai' => null,
                'sebab' => $penempatanSebelumnyaRaw !== '' ? 'pindah' : 'masuk',
            ];

            $dataTerurai[] = [
                'kamar' => $nomorKamar,
                'nama' => $nama,
                'alias' => $alias !== '' ? $alias : null,
                'no_wa' => $noWa !== '' ? $noWa : null,
                'catatan' => $catatan !== '' ? $catatan : null,
                'penempatan' => $penempatanList,
            ];
        }

        // Pass 2: Deteksi bentrok 1 hari di kamar yang sama (contoh Riko vs Devin di 307) & hitung tanggal_masuk paling awal.
        foreach ($dataTerurai as &$entri) {
            foreach ($entri['penempatan'] as $idx => &$slot) {
                if ($slot['selesai'] !== null) {
                    continue;
                }

                $bentrok = $penempatanTertutupPerKamar[$slot['kamar']] ?? [];

                foreach ($bentrok as $lama) {
                    if ($slot['mulai'] >= $lama['mulai'] && $slot['mulai'] <= $lama['selesai']) {
                        $tanggalLama = $slot['mulai'];
                        $tanggalBaru = CarbonImmutable::parse($lama['selesai'])->addDay()->toDateString();
                        $slot['mulai'] = $tanggalBaru;
                        $anomali[] = "Kamar {$slot['kamar']} ({$entri['nama']}): tanggal masuk dikoreksi dari {$tanggalLama} menjadi {$tanggalBaru} agar tidak bentrok dengan {$lama['nama']} (selesai {$lama['selesai']}).";
                    }
                }
            }
            unset($slot);

            $semuaMulai = array_column($entri['penempatan'], 'mulai');
            sort($semuaMulai);
            $tanggalMasukAwal = $semuaMulai[0];
            $catatanLower = strtolower((string) ($entri['catatan'] ?? ''));

            $entri['tanggal_masuk'] = $tanggalMasukAwal;
            $entri['tanggal_masuk_perkiraan'] = $tanggalMasukAwal === '2026-05-31'
                || str_contains($catatanLower, 'perkiraan');
        }
        unset($entri);

        $kamarMap = Kamar::query()
            ->untukProperti($properti)
            ->get()
            ->keyBy('nomor');

        foreach ($dataTerurai as $entri) {
            foreach ($entri['penempatan'] as $slot) {
                if (! $kamarMap->has($slot['kamar'])) {
                    throw new RuntimeException("Nomor kamar {$slot['kamar']} tidak ditemukan di properti {$properti->nama}.");
                }
            }
        }

        $penghuniBaru = 0;
        $penghuniDiperbarui = 0;
        $penempatanBaru = 0;
        $penempatanDiperbarui = 0;

        $proses = function () use (
            $properti,
            $dataTerurai,
            $kamarMap,
            $dryRun,
            &$penghuniBaru,
            &$penghuniDiperbarui,
            &$penempatanBaru,
            &$penempatanDiperbarui
        ): void {
            foreach ($dataTerurai as $entri) {
                $penghuniSudahAda = Penghuni::query()
                    ->untukProperti($properti)
                    ->where('nama', $entri['nama'])
                    ->first();

                if ($penghuniSudahAda === null) {
                    $penghuniBaru++;
                } else {
                    $penghuniDiperbarui++;
                }

                if ($dryRun) {
                    foreach ($entri['penempatan'] as $slot) {
                        $kamarModel = $kamarMap->get($slot['kamar']);
                        $sudahAda = $penghuniSudahAda !== null
                            && Penempatan::query()
                                ->untukProperti($properti)
                                ->where('penghuni_id', $penghuniSudahAda->id)
                                ->where('kamar_id', $kamarModel->id)
                                ->whereDate('mulai', $slot['mulai'])
                                ->exists();

                        if ($sudahAda) {
                            $penempatanDiperbarui++;
                        } else {
                            $penempatanBaru++;
                        }
                    }

                    continue;
                }

                $penghuni = Penghuni::query()->updateOrCreate(
                    [
                        'properti_id' => $properti->id,
                        'nama' => $entri['nama'],
                    ],
                    [
                        'alias' => $entri['alias'],
                        'no_wa' => $entri['no_wa'],
                        'status' => 'aktif',
                        'tanggal_masuk' => $entri['tanggal_masuk'],
                        'tanggal_masuk_perkiraan' => $entri['tanggal_masuk_perkiraan'],
                        'catatan' => $entri['catatan'],
                    ]
                );

                foreach ($entri['penempatan'] as $slot) {
                    $kamarModel = $kamarMap->get($slot['kamar']);

                    $sudahAda = Penempatan::query()
                        ->untukProperti($properti)
                        ->where('penghuni_id', $penghuni->id)
                        ->where('kamar_id', $kamarModel->id)
                        ->whereDate('mulai', $slot['mulai'])
                        ->first();

                    if ($sudahAda !== null) {
                        $sudahAda->update([
                            'selesai' => $slot['selesai'],
                            'sebab' => $slot['sebab'],
                        ]);
                        $penempatanDiperbarui++;
                    } else {
                        Penempatan::query()->create([
                            'properti_id' => $properti->id,
                            'penghuni_id' => $penghuni->id,
                            'kamar_id' => $kamarModel->id,
                            'mulai' => $slot['mulai'],
                            'selesai' => $slot['selesai'],
                            'sebab' => $slot['sebab'],
                        ]);
                        $penempatanBaru++;
                    }
                }
            }

            if (! $dryRun) {
                AuditLog::query()->create([
                    'properti_id' => $properti->id,
                    'user_id' => auth()->id(),
                    'aksi' => 'impor_seed_penghuni',
                    'subjek_type' => Properti::class,
                    'subjek_id' => $properti->id,
                    'sesudah' => [
                        'total_baris' => count($dataTerurai),
                        'penghuni_baru' => $penghuniBaru,
                        'penghuni_diperbarui' => $penghuniDiperbarui,
                        'penempatan_baru' => $penempatanBaru,
                        'penempatan_diperbarui' => $penempatanDiperbarui,
                    ],
                ]);
            }
        };

        if ($dryRun) {
            $proses();
        } else {
            DB::transaction($proses);
        }

        return [
            'dry_run' => $dryRun,
            'total_baris' => count($dataTerurai),
            'penghuni_baru' => $penghuniBaru,
            'penghuni_diperbarui' => $penghuniDiperbarui,
            'penempatan_baru' => $penempatanBaru,
            'penempatan_diperbarui' => $penempatanDiperbarui,
            'anomali' => $anomali,
        ];
    }

    public function normalisasiNomorWa(string $nomor): string
    {
        $digit = preg_replace('/\D+/', '', $nomor) ?? '';

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
     * @return array<int, array<string, string>>
     */
    private function bacaCsv(string $pathCsv): array
    {
        $handle = fopen($pathCsv, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Gagal membuka berkas CSV: {$pathCsv}");
        }

        try {
            $header = fgetcsv($handle);

            if (! is_array($header)) {
                return [];
            }

            $header = array_map(
                fn ($kolom): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $kolom)),
                $header
            );

            $rows = [];

            while (($data = fgetcsv($handle)) !== false) {
                if ($data === [null] || $data === []) {
                    continue;
                }

                $row = [];

                foreach ($header as $index => $namaKolom) {
                    $row[$namaKolom] = isset($data[$index]) ? (string) $data[$index] : '';
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
