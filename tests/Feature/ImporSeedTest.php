<?php

namespace Tests\Feature;

use App\Models\Penempatan;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\ImporSeedService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImporSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_tidak_menulis_data_ke_database(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('laundry:impor-seed', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('DRY-RUN');

        $this->assertSame(0, Penghuni::query()->count());
        $this->assertSame(0, Penempatan::query()->count());
    }

    public function test_impor_seed_penghuni_idempoten_dan_mengoreksi_bentrok_kamar_307(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('laundry:impor-seed')->assertSuccessful();
        $this->artisan('laundry:impor-seed')->assertSuccessful();

        $properti = Properti::query()->where('nama', 'Dbata Residence')->firstOrFail();

        $this->assertSame(30, Penghuni::query()->untukProperti($properti)->count());
        $this->assertSame(31, Penempatan::query()->untukProperti($properti)->count());

        // Devin Hong: tanggal_masuk awal 2026-05-31, memiliki 2 penempatan (307 -> 310)
        $devin = Penghuni::query()
            ->untukProperti($properti)
            ->where('nama', 'Devin Hong')
            ->with('penempatan.kamar')
            ->firstOrFail();

        $this->assertSame('2026-05-31', $devin->tanggal_masuk->toDateString());
        $this->assertTrue($devin->tanggal_masuk_perkiraan);
        $this->assertCount(2, $devin->penempatan);
        $this->assertSame('307', $devin->penempatan[0]->kamar->nomor);
        $this->assertSame('2026-11-10', $devin->penempatan[0]->selesai?->toDateString());
        $this->assertSame('310', $devin->penempatan[1]->kamar->nomor);
        $this->assertSame('2026-11-11', $devin->penempatan[1]->mulai->toDateString());
        $this->assertNull($devin->penempatan[1]->selesai);
        $this->assertSame('pindah', $devin->penempatan[1]->sebab);

        // Riko di kamar 307: dikoreksi otomatis ke 2026-11-11 dan menyimpan alias Yeriko
        $riko = Penghuni::query()
            ->untukProperti($properti)
            ->where('nama', 'Riko')
            ->with('penempatan.kamar')
            ->firstOrFail();

        $this->assertSame('Yeriko', $riko->alias);
        $this->assertSame('2026-11-11', $riko->tanggal_masuk->toDateString());
        $this->assertCount(1, $riko->penempatan);
        $this->assertSame('307', $riko->penempatan[0]->kamar->nomor);
        $this->assertSame('2026-11-11', $riko->penempatan[0]->mulai->toDateString());

        // Pastikan hasil anomali mencakup koreksi 307 dan nomor WA Haura/Naufal tanpa menolak
        $service = app(ImporSeedService::class);
        $laporanDryRun = $service->imporPenghuni($properti, storage_path('app/seed/seed_penghuni_aktif.csv'), true);
        $teksAnomali = implode("\n", $laporanDryRun['anomali']);

        $this->assertStringContainsString('Riko', $teksAnomali);
        $this->assertStringContainsString('2026-11-11', $teksAnomali);
        $this->assertStringContainsString('Haura', $teksAnomali);
        $this->assertStringContainsString('Naufal', $teksAnomali);
    }
}
