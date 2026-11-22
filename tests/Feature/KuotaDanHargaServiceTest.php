<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Laundry;
use App\Models\Pengaturan;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use App\Services\HargaService;
use App\Services\KuotaService;
use App\Services\PengaturanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KuotaDanHargaServiceTest extends TestCase
{
    use RefreshDatabase;

    private Properti $properti;

    private User $admin;

    private Kamar $kamar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);

        $owner = User::factory()->create(['role' => Peran::Owner]);
        $this->properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Test',
            'nama_properti_laporan' => 'Dbata Test',
            'status' => 'aktif',
        ]);

        $this->admin = User::factory()->create([
            'role' => Peran::Admin,
            'properti_id' => $this->properti->id,
        ]);

        $this->kamar = Kamar::query()->create([
            'properti_id' => $this->properti->id,
            'nomor' => '101',
            'lantai' => 1,
            'aktif' => true,
        ]);
    }

    public function test_perhitungan_biaya_utama_minimum_kg_dan_desimal_aman(): void
    {
        $harga = app(HargaService::class);

        $this->assertSame(150, $harga->keCentiKg('1,5'));
        $this->assertSame(240, $harga->keCentiKg('2.4'));
        $this->assertSame(2050, $harga->keCentiKg(20.5));
        $this->assertSame('20,5', $harga->dariCentiKg(2050, true));

        // 1,5 kg dan 2,0 kg sama-sama dikenakan minimum 2 kg * Rp8.000 = Rp16.000
        $this->assertSame(16000, $harga->hitungBiayaUtama('1,5', 8000, 2));
        $this->assertSame(16000, $harga->hitungBiayaUtama(2.0, 8000, 2));

        // 2,1 kg * Rp8.000 = 16.800 -> bulatkan ke atas ke ribuan = Rp17.000
        $this->assertSame(17000, $harga->hitungBiayaUtama('2,1', 8000, 2));
        // 2,4 kg * Rp8.000 = 19.200 -> bulatkan ke atas ke ribuan = Rp20.000
        $this->assertSame(20000, $harga->hitungBiayaUtama('2,4', 8000, 2));
        // 2,9 kg * Rp8.000 = 23.200 -> bulatkan ke atas ke ribuan = Rp24.000
        $this->assertSame(24000, $harga->hitungBiayaUtama('2,9', 8000, 2));
        // 3,0 kg * Rp8.000 = Rp24.000 (tepat kelipatan 1.000)
        $this->assertSame(24000, $harga->hitungBiayaUtama('3,0', 8000, 2));
    }

    public function test_semua_skenario_kuota_dan_tagihan_sesuai_aturan_bisnis(): void
    {
        $kuotaService = app(KuotaService::class);

        $kasus = [
            ['nama' => 'Glenn 21kg', 'berat' => '21.00', 'terpisah' => 0, 'status' => 'lewat', 'toleransi' => false, 'otomatis' => 8000, 'total' => 8000],
            ['nama' => 'Under Toleransi', 'berat' => '20.50', 'terpisah' => 0, 'status' => 'lewat', 'toleransi' => true, 'otomatis' => 0, 'total' => 0],
            ['nama' => 'Dengan Selimut', 'berat' => '23.00', 'terpisah' => 15000, 'status' => 'lewat', 'toleransi' => false, 'otomatis' => 24000, 'total' => 39000],
            ['nama' => 'Pembulatan Atas 5.3kg', 'berat' => '25.30', 'terpisah' => 0, 'status' => 'lewat', 'toleransi' => false, 'otomatis' => 43000, 'total' => 43000],
            ['nama' => 'Pembulatan Atas 2.6kg', 'berat' => '22.60', 'terpisah' => 0, 'status' => 'lewat', 'toleransi' => false, 'otomatis' => 21000, 'total' => 21000],
            ['nama' => 'Tepat 20kg', 'berat' => '20.00', 'terpisah' => 0, 'status' => 'mendekati', 'toleransi' => false, 'otomatis' => 0, 'total' => 0],
            ['nama' => 'Batas 75%', 'berat' => '15.00', 'terpisah' => 0, 'status' => 'mendekati', 'toleransi' => false, 'otomatis' => 0, 'total' => 0],
            ['nama' => 'Normal', 'berat' => '14.50', 'terpisah' => 0, 'status' => 'normal', 'toleransi' => false, 'otomatis' => 0, 'total' => 0],
        ];

        foreach ($kasus as $item) {
            $penghuni = $this->buatPenghuniDenganLaundry($item['nama'], [
                ['tanggal' => '2026-11-10', 'berat_kg' => $item['berat'], 'item_biaya' => $item['terpisah']],
            ]);

            $status = $kuotaService->statusBulan($penghuni, '2026-11');

            $this->assertSame($item['status'], $status['status'], "Gagal status untuk {$item['nama']}");
            $this->assertSame($item['toleransi'], $status['di_bawah_toleransi'], "Gagal flag toleransi untuk {$item['nama']}");
            $this->assertSame($item['otomatis'], $status['tagihan_otomatis'], "Gagal tagihan otomatis untuk {$item['nama']}");
            $this->assertSame($item['total'], $status['total_tagihan'], "Gagal total tagihan untuk {$item['nama']}");
        }
    }

    public function test_penyesuaian_manual_lewat_kuota_simpan_audit_dan_reset(): void
    {
        $kuotaService = app(KuotaService::class);
        $penghuni = $this->buatPenghuniDenganLaundry('Penyesuaian', [
            ['tanggal' => '2026-11-12', 'berat_kg' => '22.60', 'item_biaya' => 0],
        ]);

        $awal = $kuotaService->statusBulan($penghuni, '2026-11');
        $this->assertSame(21000, $awal['tagihan_lewat_kuota']);

        $kuotaService->simpanPenyesuaian($penghuni, '2026-11', 20800, 'Kesepakatan tanpa pembulatan', $this->admin);

        $setelahManual = $kuotaService->statusBulan($penghuni, '2026-11');
        $this->assertSame(20800, $setelahManual['tagihan_lewat_kuota']);
        $this->assertSame(20800, $setelahManual['nominal_penyesuaian']);
        $this->assertDatabaseHas('audit_log', [
            'properti_id' => $this->properti->id,
            'aksi' => 'kuota_simpan_penyesuaian',
            'subjek_id' => $penghuni->id,
        ]);

        $kuotaService->simpanPenyesuaian($penghuni, '2026-11', null, null, $this->admin);

        $setelahReset = $kuotaService->statusBulan($penghuni, '2026-11');
        $this->assertNull($setelahReset['nominal_penyesuaian']);
        $this->assertSame(21000, $setelahReset['tagihan_lewat_kuota']);
        $this->assertDatabaseHas('audit_log', [
            'properti_id' => $this->properti->id,
            'aksi' => 'kuota_hapus_penyesuaian',
            'subjek_id' => $penghuni->id,
        ]);
    }

    public function test_aturan_berversi_tanggal_berlaku_tidak_mengubah_bulan_sebelumnya(): void
    {
        Pengaturan::query()->create([
            'properti_id' => $this->properti->id,
            'kunci' => 'tarif_per_kg',
            'nilai' => '10000',
            'berlaku_mulai' => '2026-12-01',
            'diubah_oleh' => $this->admin->id,
        ]);

        Pengaturan::query()->create([
            'properti_id' => $this->properti->id,
            'kunci' => 'kuota_kg',
            'nilai' => '25',
            'berlaku_mulai' => '2026-12-01',
            'diubah_oleh' => $this->admin->id,
        ]);

        app(PengaturanService::class)->bersihkanCache($this->properti);

        $penghuni = $this->buatPenghuniDenganLaundry('Versi Aturan', [
            ['tanggal' => '2026-11-15', 'berat_kg' => '22.00', 'item_biaya' => 0],
            ['tanggal' => '2026-12-05', 'berat_kg' => '22.00', 'item_biaya' => 0],
        ]);

        $kuotaService = app(KuotaService::class);
        $november = $kuotaService->statusBulan($penghuni, '2026-11');
        $desember = $kuotaService->statusBulan($penghuni, '2026-12');

        $this->assertSame(2000, $november['kuota_centi']);
        $this->assertSame(8000, $november['tarif_per_kg']);
        $this->assertSame('lewat', $november['status']);
        $this->assertSame(16000, $november['tagihan_lewat_kuota']);

        $this->assertSame(2500, $desember['kuota_centi']);
        $this->assertSame(10000, $desember['tarif_per_kg']);
        $this->assertSame('mendekati', $desember['status']);
        $this->assertSame(0, $desember['tagihan_lewat_kuota']);
    }

    public function test_ringkasan_semua_efisien_tanpa_query_n_plus_1_dan_abaikan_soft_delete(): void
    {
        $ids = [];

        for ($i = 1; $i <= 12; $i++) {
            $penghuni = $this->buatPenghuniDenganLaundry("Penghuni {$i}", [
                ['tanggal' => '2026-11-05', 'berat_kg' => '5.00', 'item_biaya' => 0],
            ]);
            $ids[] = $penghuni->id;
        }

        // Tambah catatan yang di-soft-delete agar tidak masuk hitungan kuota
        $deletedRow = Laundry::query()->create([
            'properti_id' => $this->properti->id,
            'tanggal' => '2026-11-06',
            'jenis' => 'penghuni',
            'penghuni_id' => $ids[0],
            'kamar_id' => $this->kamar->id,
            'berat_kg' => '18.00',
            'biaya_utama' => 144000,
            'dicatat_oleh' => $this->admin->id,
        ]);
        $deletedRow->delete();

        $kuotaService = app(KuotaService::class);
        // Hangatkan cache pengaturan dulu agar log murni mengukur query agregasi ringkasanSemua
        app(PengaturanService::class)->semuaPada($this->properti, '2026-11-30');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $ringkasan = $kuotaService->ringkasanSemua($this->properti, '2026-11', $ids);
        $jumlahQuery = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(12, $ringkasan);
        $this->assertSame(500, $ringkasan->get($ids[0])['terpakai_centi']);
        $this->assertLessThanOrEqual(4, $jumlahQuery);
    }

    public function test_catatan_tanpa_biaya_tidak_asal_dihitung_pada_tagihan_otomatis_dan_reset(): void
    {
        $kuotaService = app(KuotaService::class);
        $penghuni = Penghuni::query()->create([
            'properti_id' => $this->properti->id,
            'nama' => 'Glenn Kasus Belum Diisi',
            'status' => 'aktif',
            'tanggal_masuk' => '2026-01-01',
        ]);

        // 21,0 kg sudah punya biaya_utama -> kelebihan berharga 1,0 kg = Rp8.000
        Laundry::query()->create([
            'properti_id' => $this->properti->id,
            'tanggal' => '2026-11-17',
            'jenis' => 'penghuni',
            'penghuni_id' => $penghuni->id,
            'kamar_id' => $this->kamar->id,
            'berat_kg' => '21.00',
            'biaya_utama' => 164800,
            'item_biaya' => 0,
            'dicatat_oleh' => $this->admin->id,
        ]);

        // 2,7 kg biaya_utama NULL (Belum diisi) -> tidak boleh asal dihitung jadi Rp30.000
        $belumDiisi = Laundry::query()->create([
            'properti_id' => $this->properti->id,
            'tanggal' => '2026-11-20',
            'jenis' => 'penghuni',
            'penghuni_id' => $penghuni->id,
            'kamar_id' => $this->kamar->id,
            'berat_kg' => '2.70',
            'biaya_utama' => null,
            'item_biaya' => 0,
            'dicatat_oleh' => $this->admin->id,
        ]);

        $awal = $kuotaService->statusBulan($penghuni, '2026-11');
        $this->assertSame(2370, $awal['terpakai_centi']);
        $this->assertSame(8000, $awal['tagihan_otomatis']);
        $this->assertSame(8000, $awal['tagihan_lewat_kuota']);

        // Ubah manual lalu reset otomatis harus kembali ke Rp8.000 (bukan Rp30.000)
        $kuotaService->simpanPenyesuaian($penghuni, '2026-11', 12000, 'Manual sementara', $this->admin);
        $kuotaService->simpanPenyesuaian($penghuni, '2026-11', null, null, $this->admin, true);
        $setelahReset = $kuotaService->statusBulan($penghuni, '2026-11');
        $this->assertSame(8000, $setelahReset['tagihan_lewat_kuota']);

        // Setelah biaya 2,7 kg diinput, barulah ikut terhitung (kelebihan 3,7 kg = 29.600 -> Rp30.000)
        $belumDiisi->forceFill(['biaya_utama' => 22000])->save();
        $setelahDiisi = $kuotaService->statusBulan($penghuni, '2026-11');
        $this->assertSame(30000, $setelahDiisi['tagihan_otomatis']);
        $this->assertSame(30000, $setelahDiisi['tagihan_lewat_kuota']);
    }

    /**
     * @param  array<int, array{tanggal: string, berat_kg: string, item_biaya: int}>  $catatan
     */
    private function buatPenghuniDenganLaundry(string $nama, array $catatan): Penghuni
    {
        $penghuni = Penghuni::query()->create([
            'properti_id' => $this->properti->id,
            'nama' => $nama,
            'status' => 'aktif',
            'tanggal_masuk' => '2026-01-01',
        ]);

        foreach ($catatan as $baris) {
            Laundry::query()->create([
                'properti_id' => $this->properti->id,
                'tanggal' => $baris['tanggal'],
                'jenis' => 'penghuni',
                'penghuni_id' => $penghuni->id,
                'kamar_id' => $this->kamar->id,
                'berat_kg' => $baris['berat_kg'],
                'biaya_utama' => 0,
                'item_biaya' => $baris['item_biaya'],
                'dicatat_oleh' => $this->admin->id,
            ]);
        }

        return $penghuni;
    }
}
