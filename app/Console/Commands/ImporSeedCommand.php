<?php

namespace App\Console\Commands;

use App\Models\Properti;
use App\Services\ImporSeedService;
use Illuminate\Console\Command;
use Throwable;

class ImporSeedCommand extends Command
{
    protected $signature = 'laundry:impor-seed
        {--file= : Path berkas CSV penghuni}
        {--properti=Dbata Residence : Nama properti target}
        {--dry-run : Simulasi tanpa menyimpan ke database}';

    protected $description = 'Impor data awal penghuni dan penempatan dari CSV seed secara idempoten';

    public function handle(ImporSeedService $service): int
    {
        $namaProperti = (string) $this->option('properti');
        $properti = Properti::query()->where('nama', $namaProperti)->first();

        if ($properti === null) {
            $this->error("Properti '{$namaProperti}' tidak ditemukan.");

            return self::FAILURE;
        }

        $pathCsv = $this->option('file')
            ? (string) $this->option('file')
            : storage_path('app/seed/seed_penghuni_aktif.csv');

        $dryRun = (bool) $this->option('dry-run');

        try {
            $hasil = $service->imporPenghuni($properti, $pathCsv, $dryRun);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $mode = $hasil['dry_run'] ? 'DRY-RUN (Simulasi)' : 'EKSEKUSI';
        $this->info("[{$mode}] Properti: {$properti->nama}");
        $this->line("Total baris dibaca  : {$hasil['total_baris']}");
        $this->line("Penghuni baru/update: {$hasil['penghuni_baru']} / {$hasil['penghuni_diperbarui']}");
        $this->line("Penempatan baru/upd : {$hasil['penempatan_baru']} / {$hasil['penempatan_diperbarui']}");

        if ($hasil['anomali'] !== []) {
            $this->newLine();
            $this->warn('Catatan Anomali Impor:');

            foreach ($hasil['anomali'] as $pesan) {
                $this->line(" - {$pesan}");
            }
        }

        return self::SUCCESS;
    }
}
