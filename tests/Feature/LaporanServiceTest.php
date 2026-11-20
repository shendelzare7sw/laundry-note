<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Properti;
use App\Models\User;
use App\Services\KuotaService;
use App\Services\LaporanService;
use App\Services\LaundryService;
use App\Services\PenempatanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanServiceTest extends TestCase
{
    use RefreshDatabase;

    private LaporanService $laporanService;

    private PenempatanService $penempatanService;

    private LaundryService $laundryService;

    private KuotaService $kuotaService;

    private Properti $properti;

    private User $admin;

    private Kamar $kamar307;

    private Kamar $kamar310;

    private Kamar $kamar201;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);
        $this->laporanService = app(LaporanService::class);
        $this->penempatanService = app(PenempatanService::class);
        $this->laundryService = app(LaundryService::class);
        $this->kuotaService = app(KuotaService::class);

        $owner = User::factory()->create(['role' => Peran::Owner]);
        $this->properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => "D'bata Residence",
            'nama_properti_laporan' => "D'bata Residence",
            'status' => 'aktif',
        ]);
        $this->admin = User::factory()->create([
            'role' => Peran::Admin,
            'properti_id' => $this->properti->id,
        ]);

        $this->kamar201 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '201', 'lantai' => 2, 'aktif' => true]);
        $this->kamar307 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '307', 'lantai' => 3, 'aktif' => true]);
        $this->kamar310 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '310', 'lantai' => 3, 'aktif' => true]);
    }

    public function test_laporan_menampilkan_jalur_pindah_kamar_penanda_merah_k4_multi_item_dan_penyesuaian(): void
    {
        // Devin masuk 307 lalu pindah 310 di bulan yang sama
        $devin = $this->penempatanService->masuk($this->properti, [
            'nama' => 'Devin',
            'kamar_id' => $this->kamar307->id,
            'tanggal_masuk' => '2026-10-01',
            'no_wa' => '081211112222',
        ], $this->admin)['penghuni'];

        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-05',
            'penghuni_id' => $devin->id,
            'berat_kg' => '12.5',
            'item' => [
                ['nama' => 'Bed cover', 'biaya' => 20000],
                ['nama' => 'Selimut', 'biaya' => 15000],
            ],
        ], $this->admin);

        $this->penempatanService->pindahKamar($devin, $this->kamar310, '2026-10-11', $this->admin);

        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-15',
            'penghuni_id' => $devin->id,
            'berat_kg' => '10.5',
        ], $this->admin);

        // Penyesuaian manual tagihan lewat kuota Devin (3,0 kg default Rp24.000 -> override Rp20.800)
        $this->kuotaService->simpanPenyesuaian($devin, '2026-10', 20800, 'Diskon kesepakatan', $this->admin);

        // Dian lewat kuota 0,5 kg (di bawah toleransi 1,0 kg -> catatan tetap merah K4, tagihan Rp0)
        $dian = $this->penempatanService->masuk($this->properti, [
            'nama' => 'Dian',
            'kamar_id' => $this->kamar201->id,
            'tanggal_masuk' => '2026-10-01',
        ], $this->admin)['penghuni'];

        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-02',
            'penghuni_id' => $dian->id,
            'berat_kg' => '18',
        ], $this->admin);
        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-20',
            'penghuni_id' => $dian->id,
            'berat_kg' => '2.5',
        ], $this->admin);

        $laporan = $this->laporanService->susunLaporan($this->properti, [
            'periode' => 'rentang',
            'dari' => '2026-10-01',
            'sampai' => '2026-10-31',
        ]);

        $this->assertTrue($laporan['rentang']['is_bulan_penuh']);
        $this->assertSame('43,5', $laporan['ringkasan']['total_kg_label']);
        $this->assertSame(2, $laporan['ringkasan']['jumlah_penghuni']);
        $this->assertSame(2, $laporan['ringkasan']['jumlah_mendekati_lewat']);
        $this->assertSame(20800, $laporan['ringkasan']['total_tagihan_lewat']);
        $this->assertSame(35000, $laporan['ringkasan']['total_bayar_terpisah']);

        // Cek kartu Dian (Kamar 201)
        $kartuDian = $laporan['kartu_penghuni']->firstWhere('penghuni.id', $dian->id);
        $this->assertSame('201', $kartuDian['label_kamar']);
        $this->assertSame('lewat', $kartuDian['kuota']['status']);
        $this->assertSame(0, $kartuDian['tagihan_lewat_kuota']);
        $this->assertFalse($kartuDian['baris'][0]['merah']);
        $this->assertTrue($kartuDian['baris'][1]['merah']); // K4: tetap merah meski di bawah toleransi

        // Cek kartu Devin (Jalur 307→310 + 2 item terpisah + override)
        $kartuDevin = $laporan['kartu_penghuni']->firstWhere('penghuni.id', $devin->id);
        $this->assertSame('307→310', $kartuDevin['label_kamar']);
        $this->assertSame(2, $kartuDevin['jumlah_item_terpisah']);
        $this->assertSame(35000, $kartuDevin['bayar_terpisah']);
        $this->assertSame(20800, $kartuDevin['tagihan_lewat_kuota']);
        $this->assertSame(20800, $kartuDevin['kuota']['nominal_penyesuaian']);
        $this->assertCount(4, $kartuDevin['baris']); // 2 utama + 2 item terpisah
        $this->assertNotNull($kartuDevin['wa_tagihan']['url']);
    }

    public function test_periode_parsial_menampilkan_label_periode_ini_dan_csv_lengkap(): void
    {
        $penghuni = $this->penempatanService->masuk($this->properti, [
            'nama' => 'Travis',
            'kamar_id' => $this->kamar201->id,
            'tanggal_masuk' => '2026-10-01',
        ], $this->admin)['penghuni'];

        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-05',
            'penghuni_id' => $penghuni->id,
            'berat_kg' => '5',
        ], $this->admin);
        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-25',
            'penghuni_id' => $penghuni->id,
            'berat_kg' => '4',
        ], $this->admin);

        // Filter hanya paruh pertama bulan (1-15 Okt)
        $laporan = $this->laporanService->susunLaporan($this->properti, [
            'periode' => 'rentang',
            'dari' => '2026-10-01',
            'sampai' => '2026-10-15',
        ]);

        $this->assertFalse($laporan['rentang']['is_bulan_penuh']);
        $kartu = $laporan['kartu_penghuni']->first();
        $this->assertTrue($kartu['tampilkan_periode_ini']);
        $this->assertSame('5,0', $kartu['total_kg_label']);
        $this->assertSame('9,0', $kartu['kuota']['terpakai_label']);

        $csv = $this->laporanService->dataCsv($this->properti, [
            'periode' => 'rentang',
            'dari' => '2026-10-01',
            'sampai' => '2026-10-15',
        ]);

        $this->assertCount(13, $csv['header']);
        $this->assertCount(1, $csv['baris']);
        $this->assertSame('Travis', $csv['baris'][0][2]);
        $this->assertSame('5,0', $csv['baris'][0][5]);
    }
}
