<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use App\Services\KuotaService;
use App\Services\LaundryQueryService;
use App\Services\LaundryService;
use App\Services\PenempatanService;
use Carbon\CarbonImmutable;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaundryQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private Properti $properti;

    private User $admin;

    private User $penjaga;

    private Kamar $kamar307;

    private Kamar $kamar310;

    private LaundryService $laundryService;

    private LaundryQueryService $queryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);

        $owner = User::factory()->create(['role' => Peran::Owner]);
        $this->properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Uji',
            'nama_properti_laporan' => 'Dbata Uji',
            'status' => 'aktif',
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Budi',
            'role' => Peran::Admin,
            'properti_id' => $this->properti->id,
        ]);
        $this->penjaga = User::factory()->create([
            'name' => 'Penjaga Siti',
            'role' => Peran::Penjaga,
            'properti_id' => $this->properti->id,
        ]);

        $this->kamar307 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '307', 'lantai' => 3, 'aktif' => true]);
        $this->kamar310 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '310', 'lantai' => 3, 'aktif' => true]);

        $this->laundryService = app(LaundryService::class);
        $this->queryService = app(LaundryQueryService::class);
    }

    public function test_penanda_merah_sesuai_aturan_k4_dan_item_saja_tidak_merah(): void
    {
        $penghuni = $this->masuk('Devin', $this->kamar307, '2026-10-01');

        $c1 = $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-10-05', 'penghuni_id' => $penghuni->id, 'berat_kg' => '10',
        ], $this->admin);
        $c2 = $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-10-12', 'penghuni_id' => $penghuni->id, 'berat_kg' => '10',
        ], $this->admin);
        $c3 = $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-10-18', 'penghuni_id' => $penghuni->id, 'berat_kg' => '0,5',
        ], $this->admin);
        $c4 = $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-10-20', 'penghuni_id' => $penghuni->id, 'berat_kg' => '2',
        ], $this->admin);
        $c5 = $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-10-21', 'penghuni_id' => $penghuni->id,
            'item' => [['nama' => 'Bed cover', 'jumlah' => 1, 'biaya' => 25000]],
        ], $this->admin);

        // Pastikan total terpakai sebelum c4 tepat 20,5 kg dan di bawah toleransi tagihan = 0 untuk 20,5 kg.
        $statusKuotaSebelum = app(KuotaService::class)->statusBulan($penghuni, '2026-10');
        $this->assertSame('lewat', $statusKuotaSebelum['status']);

        $hasil = $this->queryService->daftarDanRingkasan($this->properti, [
            'periode' => 'rentang',
            'dari' => '2026-10-01',
            'sampai' => '2026-10-31',
        ]);

        $meta = $hasil['metaBaris'];
        $this->assertFalse($meta[$c1->id]['lewat_kuota']);
        $this->assertFalse($meta[$c2->id]['lewat_kuota']);
        $this->assertTrue($meta[$c3->id]['lewat_kuota']);
        $this->assertSame('20,5', $meta[$c3->id]['kumulatif_kg']);
        $this->assertTrue($meta[$c4->id]['lewat_kuota']);
        $this->assertFalse($meta[$c5->id]['lewat_kuota']);
    }

    public function test_penanda_merah_bawah_toleransi_dan_akurat_walau_difilter_hari_ini_atau_7_hari(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00', 'Asia/Jakarta'));

        $penghuni = $this->masuk('Riko', $this->kamar307, '2026-11-01');

        // Catatan awal bulan (di luar rentang 7 hari dan hari ini) sudah memenuhi 20 kg kuota.
        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-02', 'penghuni_id' => $penghuni->id, 'berat_kg' => '20',
        ], $this->admin);

        // Catatan hari ini hanya 0,5 kg (di bawah toleransi 1 kg → tagihan lewat kuota otomatis Rp0), namun tetap merah.
        $hariIni = $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-18', 'penghuni_id' => $penghuni->id, 'berat_kg' => '0,5',
        ], $this->admin);

        $status = app(KuotaService::class)->statusBulan($penghuni, '2026-11');
        $this->assertSame(0, $status['tagihan_otomatis']);
        $this->assertTrue($status['di_bawah_toleransi']);

        foreach (['hari_ini', '7_hari'] as $periode) {
            $hasil = $this->queryService->daftarDanRingkasan($this->properti, ['periode' => $periode]);
            $meta = $hasil['metaBaris'][$hariIni->id];

            $this->assertTrue($meta['lewat_kuota'], "Baris harus tetap merah saat filter {$periode}");
            $this->assertSame('20,5', $meta['kumulatif_kg']);
        }
    }

    public function test_filter_periode_pencarian_jenis_dan_ringkasan_akurat(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'Asia/Jakarta'));

        $devin = $this->masuk('Devin', $this->kamar307, '2026-10-01');
        $syafira = $this->masuk('Syafira', $this->kamar310, '2026-11-01');

        // Bulan lalu (Oktober)
        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-10-15', 'penghuni_id' => $devin->id, 'berat_kg' => '5',
        ], $this->admin);

        // Bulan ini (November): melebihi kuota Devin (23 kg -> kuota gratis 20 kg) + 2 item terpisah
        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-05', 'penghuni_id' => $devin->id, 'berat_kg' => '23',
            'item' => [
                ['nama' => 'Selimut', 'jumlah' => 1, 'biaya' => 15000],
                ['nama' => 'Sprei', 'jumlah' => 2, 'biaya' => 20000],
            ],
        ], $this->admin);

        // Bulan ini: Syafira (10 kg -> kuota gratis 10 kg)
        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-18', 'penghuni_id' => $syafira->id, 'berat_kg' => '10',
        ], $this->penjaga);

        // Hari ini: Gedung (4 kg, tidak masuk kuota gratis) & Tanpa nama (2 kg)
        $this->laundryService->catat($this->properti, [
            'jenis' => 'gedung', 'tanggal' => '2026-11-20', 'berat_kg' => '4', 'catatan' => 'Kain pel lobi',
        ], $this->penjaga);
        $this->laundryService->catat($this->properti, [
            'jenis' => 'tanpa_nama', 'tanggal' => '2026-11-20', 'berat_kg' => '2',
        ], $this->admin);

        // Ringkasan Bulan Ini: total 23+10+4+2 = 39 kg; kuota gratis 20+10 = 30 kg; terpisah 1 catatan, 3 item, Rp35.000
        $bulanIni = $this->queryService->daftarDanRingkasan($this->properti, ['periode' => 'bulan_ini']);
        $this->assertSame(4, $bulanIni['daftar']->total());
        $this->assertSame('39', $bulanIni['ringkasan']['total_berat_kg']);
        $this->assertSame('30', $bulanIni['ringkasan']['kuota_gratis_kg']);
        $this->assertSame(1, $bulanIni['ringkasan']['jumlah_catatan_terpisah']);
        $this->assertSame(3, $bulanIni['ringkasan']['jumlah_item_terpisah']);
        $this->assertSame(35000, $bulanIni['ringkasan']['total_bayar_terpisah']);
        $this->assertSame(1, $bulanIni['ringkasan']['jumlah_tanpa_nama']);

        // Bulan lalu
        $bulanLalu = $this->queryService->daftarDanRingkasan($this->properti, ['periode' => 'bulan_lalu']);
        $this->assertSame(1, $bulanLalu['daftar']->total());

        // Hari ini & 7 hari
        $hariIni = $this->queryService->daftarDanRingkasan($this->properti, ['periode' => 'hari_ini']);
        $this->assertSame(2, $hariIni['daftar']->total());
        $tujuhHari = $this->queryService->daftarDanRingkasan($this->properti, ['periode' => '7_hari']);
        $this->assertSame(3, $tujuhHari['daftar']->total());

        // Semua waktu
        $semua = $this->queryService->daftarDanRingkasan($this->properti, ['periode' => 'semua']);
        $this->assertSame(5, $semua['daftar']->total());

        // Cari nama item terpisah, kamar, dan pencatat
        $this->assertSame(1, $this->queryService->daftarDanRingkasan($this->properti, ['periode' => 'semua', 'cari' => 'Selimut'])['daftar']->total());
        $this->assertSame(1, $this->queryService->daftarDanRingkasan($this->properti, ['periode' => 'semua', 'cari' => '310'])['daftar']->total());
        $this->assertSame(2, $this->queryService->daftarDanRingkasan($this->properti, ['periode' => 'semua', 'cari' => 'Siti'])['daftar']->total());

        // Filter jenis
        $gedung = $this->queryService->daftarDanRingkasan($this->properti, ['periode' => 'semua', 'jenis' => 'gedung']);
        $this->assertSame(1, $gedung['daftar']->total());

        // Peringatan kuota bulan ini
        $peringatan = $this->queryService->peringatanKuotaBulanIni($this->properti);
        $this->assertCount(1, $peringatan);
        $this->assertSame('Devin', $peringatan[0]['penghuni']->nama);
        $this->assertSame('lewat', $peringatan[0]['kuota']['status']);
    }

    public function test_penghuni_aktif_pada_tanggal_kecualikan_booking_dan_ikuti_pindah_kamar(): void
    {
        $devin = $this->masuk('Devin', $this->kamar307, '2026-11-01');
        $this->masuk('Tamu Booking', $this->kamar310, '2026-11-01', 'booking');

        $kamar201 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '201', 'lantai' => 2, 'aktif' => true]);
        app(PenempatanService::class)->pindahKamar($devin, $kamar201, '2026-11-11', $this->admin);

        $sebelum = $this->queryService->penghuniAktifPadaTanggal($this->properti, '2026-11-10');
        $this->assertCount(1, $sebelum);
        $this->assertSame('307', $sebelum[0]['kamar']);

        $sesudah = $this->queryService->penghuniAktifPadaTanggal($this->properti, '2026-11-11');
        $this->assertCount(1, $sesudah);
        $this->assertSame('201', $sesudah[0]['kamar']);
    }

    private function masuk(string $nama, Kamar $kamar, string $tanggal, string $status = 'aktif'): Penghuni
    {
        return app(PenempatanService::class)->masuk($this->properti, [
            'nama' => $nama,
            'kamar_id' => $kamar->id,
            'tanggal_masuk' => $tanggal,
            'status' => $status,
        ], $this->admin)['penghuni'];
    }
}
