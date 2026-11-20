<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use App\Services\KuotaService;
use App\Services\PenempatanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PenempatanServiceTest extends TestCase
{
    use RefreshDatabase;

    private Properti $properti;

    private User $admin;

    private Kamar $kamar307;

    private Kamar $kamar310;

    private Kamar $kamarNonaktif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);

        $owner = User::factory()->create(['role' => Peran::Owner]);
        $this->properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Residence',
            'nama_properti_laporan' => 'Dbata Residence',
            'status' => 'aktif',
        ]);

        $this->admin = User::factory()->create([
            'role' => Peran::Admin,
            'properti_id' => $this->properti->id,
        ]);

        $this->kamar307 = Kamar::query()->create([
            'properti_id' => $this->properti->id,
            'nomor' => '307',
            'lantai' => 3,
            'aktif' => true,
        ]);

        $this->kamar310 = Kamar::query()->create([
            'properti_id' => $this->properti->id,
            'nomor' => '310',
            'lantai' => 3,
            'aktif' => true,
        ]);

        $this->kamarNonaktif = Kamar::query()->create([
            'properti_id' => $this->properti->id,
            'nomor' => '100',
            'lantai' => 1,
            'aktif' => false,
        ]);
    }

    public function test_pindah_kamar_tidak_mereset_kuota_dan_tutup_penempatan_lama(): void
    {
        $service = app(PenempatanService::class);
        $kuotaService = app(KuotaService::class);

        $masuk = $service->masuk($this->properti, [
            'nama' => 'Riko',
            'kamar_id' => $this->kamar307->id,
            'tanggal_masuk' => '2026-11-01',
            'no_wa' => '0812-3456-7890',
        ], $this->admin);

        $penghuni = $masuk['penghuni'];
        $this->assertSame('6281234567890', $penghuni->no_wa);
        $this->assertNull($masuk['peringatan']);

        Laundry::query()->create([
            'properti_id' => $this->properti->id,
            'tanggal' => '2026-11-05',
            'jenis' => 'penghuni',
            'penghuni_id' => $penghuni->id,
            'kamar_id' => $this->kamar307->id,
            'berat_kg' => '11.00',
            'biaya_utama' => 88000,
            'dicatat_oleh' => $this->admin->id,
        ]);

        $penempatanBaru = $service->pindahKamar($penghuni, $this->kamar310, '2026-11-11', $this->admin);

        $this->assertSame($this->kamar310->id, $penempatanBaru->kamar_id);
        $this->assertSame('pindah', $penempatanBaru->sebab);
        $this->assertSame('2026-11-11', $penempatanBaru->mulai->toDateString());

        $this->assertDatabaseHas('penempatan', [
            'penghuni_id' => $penghuni->id,
            'kamar_id' => $this->kamar307->id,
            'selesai' => '2026-11-10',
        ]);

        Laundry::query()->create([
            'properti_id' => $this->properti->id,
            'tanggal' => '2026-11-15',
            'jenis' => 'penghuni',
            'penghuni_id' => $penghuni->id,
            'kamar_id' => $this->kamar310->id,
            'berat_kg' => '13.00',
            'biaya_utama' => 104000,
            'dicatat_oleh' => $this->admin->id,
        ]);

        // Kuota tetap dijumlahkan dari kamar 307 (11 kg) + kamar 310 (13 kg) = 24 kg -> lewat 4 kg = Rp32.000
        $kuota = $kuotaService->statusBulan($penghuni, '2026-11');
        $this->assertSame(2400, $kuota['terpakai_centi']);
        $this->assertSame('lewat', $kuota['status']);
        $this->assertSame(32000, $kuota['tagihan_lewat_kuota']);
    }

    public function test_validasi_bentrok_kamar_nonaktif_dan_batalkan_pindah(): void
    {
        $service = app(PenempatanService::class);

        $penghuniA = $service->masuk($this->properti, [
            'nama' => 'Penghuni A',
            'kamar_id' => $this->kamar307->id,
            'tanggal_masuk' => '2026-11-01',
        ], $this->admin)['penghuni'];

        $service->masuk($this->properti, [
            'nama' => 'Penghuni B',
            'kamar_id' => $this->kamar310->id,
            'tanggal_masuk' => '2026-11-01',
        ], $this->admin);

        // Tidak boleh pindah ke kamar yang sedang ditempati
        try {
            $service->pindahKamar($penghuniA, $this->kamar310, '2026-11-10', $this->admin);
            $this->fail('Seharusnya gagal pindah ke kamar terisi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kamar_tujuan_id', $e->errors());
        }

        // Tidak boleh pindah ke kamar nonaktif
        try {
            $service->pindahKamar($penghuniA, $this->kamarNonaktif, '2026-11-10', $this->admin);
            $this->fail('Seharusnya gagal pindah ke kamar nonaktif.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kamar_tujuan_id', $e->errors());
        }

        // Kosongkan kamar 310 (keluar tanggal 10 Nov) -> pindah 10 Nov harus ditolak (overlap), 11 Nov berhasil
        $penghuniB = Penghuni::query()->where('nama', 'Penghuni B')->firstOrFail();
        $service->keluar($penghuniB, '2026-11-10', 'Selesai', $this->admin);

        try {
            $service->pindahKamar($penghuniA, $this->kamar310, '2026-11-10', $this->admin);
            $this->fail('Seharusnya ditolak karena bertabrakan dengan tanggal keluar penghuni B.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kamar_tujuan_id', $e->errors());
        }

        $service->pindahKamar($penghuniA, $this->kamar310, '2026-11-11', $this->admin);

        // Batalkan pindah berhasil sebelum ada laundry di kamar baru
        $pulih = $service->batalkanPindah($penghuniA, $this->admin);
        $this->assertSame($this->kamar307->id, $pulih->kamar_id);
        $this->assertNull($pulih->selesai);

        // Pindah lagi lalu catat laundry -> batalkan pindah harus ditolak
        $service->pindahKamar($penghuniA, $this->kamar310, '2026-11-12', $this->admin);
        Laundry::query()->create([
            'properti_id' => $this->properti->id,
            'tanggal' => '2026-11-12',
            'jenis' => 'penghuni',
            'penghuni_id' => $penghuniA->id,
            'kamar_id' => $this->kamar310->id,
            'berat_kg' => '2.00',
            'biaya_utama' => 16000,
            'dicatat_oleh' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);
        $service->batalkanPindah($penghuniA, $this->admin);
    }

    public function test_keluar_batalkan_keluar_validasi_masa_tinggal_dan_normalisasi_wa(): void
    {
        $service = app(PenempatanService::class);

        $penghuni = $service->masuk($this->properti, [
            'nama' => 'Devin',
            'kamar_id' => $this->kamar307->id,
            'tanggal_masuk' => '2026-11-01',
            'no_wa' => '+62 815-3369-948',
        ], $this->admin)['penghuni'];

        $this->assertSame('628153369948', $penghuni->no_wa);

        $keluar = $service->keluar($penghuni, '2026-11-10', 'Pindah kerja', $this->admin);
        $this->assertSame('keluar', $keluar->status);
        $this->assertFalse($service->validasiTanggalDalamMasaTinggal($keluar, '2026-10-31'));
        $this->assertTrue($service->validasiTanggalDalamMasaTinggal($keluar, '2026-11-10'));
        $this->assertFalse($service->validasiTanggalDalamMasaTinggal($keluar, '2026-11-11'));

        // Uji nomor terlalu pendek menghasilkan peringatan namun tetap tersimpan
        $gantiPendek = $service->gantiNomor($keluar, '081234', $this->admin);
        $this->assertSame('6281234', $gantiPendek['penghuni']->no_wa);
        $this->assertNotNull($gantiPendek['peringatan']);

        // Penghuni baru masuk ke kamar 307 tanggal 11 Nov -> batalkanKeluar Devin harus ditolak
        $service->masuk($this->properti, [
            'nama' => 'Pengganti',
            'kamar_id' => $this->kamar307->id,
            'tanggal_masuk' => '2026-11-11',
        ], $this->admin);

        try {
            $service->batalkanKeluar($keluar, $this->admin);
            $this->fail('Seharusnya gagal batalkan keluar karena kamar 307 sudah terisi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('batalkan_keluar', $e->errors());
        }

        // Pada penghuni lain yang kamarnya masih kosong, batalkanKeluar berhasil
        $penghuni2 = $service->masuk($this->properti, [
            'nama' => 'Penghuni 310',
            'kamar_id' => $this->kamar310->id,
            'tanggal_masuk' => '2026-11-01',
        ], $this->admin)['penghuni'];

        $penghuni2 = $service->keluar($penghuni2, '2026-11-12', null, $this->admin);
        $pulih2 = $service->batalkanKeluar($penghuni2, $this->admin);
        $this->assertSame('aktif', $pulih2->status);
        $this->assertNull($pulih2->tanggal_keluar);
        $this->assertDatabaseHas('penempatan', [
            'penghuni_id' => $pulih2->id,
            'kamar_id' => $this->kamar310->id,
            'selesai' => null,
        ]);
    }
}
