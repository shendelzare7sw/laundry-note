<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\AuditLog;
use App\Models\Kamar;
use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use App\Policies\LaundryPolicy;
use App\Services\KuotaService;
use App\Services\LaundryService;
use App\Services\PenempatanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LaundryServiceTest extends TestCase
{
    use RefreshDatabase;

    private Properti $properti;

    private User $admin;

    private User $penjaga;

    private Kamar $kamar307;

    private Kamar $kamar310;

    private LaundryService $laundry;

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

        $this->admin = User::factory()->create(['role' => Peran::Admin, 'properti_id' => $this->properti->id]);
        $this->penjaga = User::factory()->create(['role' => Peran::Penjaga, 'properti_id' => $this->properti->id]);

        $this->kamar307 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '307', 'lantai' => 3, 'aktif' => true]);
        $this->kamar310 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '310', 'lantai' => 3, 'aktif' => true]);

        $this->laundry = app(LaundryService::class);
    }

    public function test_biaya_otomatis_memakai_minimum_dan_banyak_item_disinkronkan_ke_total_terpisah(): void
    {
        $penghuni = $this->masuk('Devin', $this->kamar307, '2026-11-01');

        $catatan = $this->laundry->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-11-05',
            'penghuni_id' => $penghuni->id,
            'berat_kg' => '1,5',
            'biaya_utama' => null,
            'item' => [
                ['nama' => 'Selimut', 'jumlah' => 1, 'biaya' => 15000],
                ['nama' => 'Bed cover', 'jumlah' => 2, 'berat_kg' => '3,2', 'biaya' => 30000],
            ],
        ], $this->admin);

        $this->assertSame(16000, $catatan->biaya_utama);
        $this->assertFalse($catatan->biaya_manual);
        $this->assertSame($this->kamar307->id, $catatan->kamar_id);
        $this->assertSame(45000, $catatan->item_biaya);
        $this->assertCount(2, $catatan->item);
        $this->assertSame('Selimut ×1, Bed cover ×2', $catatan->ringkasanItem());
        $this->assertSame('3.20', (string) $catatan->item[1]->berat_kg);

        $status = app(KuotaService::class)->statusBulan($penghuni, '2026-11');
        $this->assertSame(150, $status['terpakai_centi']);
        $this->assertSame(45000, $status['bayar_terpisah']);
    }

    public function test_biaya_manual_ditandai_bila_berbeda_dari_hitungan_otomatis_kasus_syafira(): void
    {
        $penghuni = $this->masuk('Syafira', $this->kamar307, '2026-11-01');

        $manual = $this->laundry->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-10', 'penghuni_id' => $penghuni->id,
            'berat_kg' => '2.4', 'biaya_utama' => 20000,
        ], $this->admin);

        $sama = $this->laundry->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-11', 'penghuni_id' => $penghuni->id,
            'berat_kg' => '2.4', 'biaya_utama' => 19200,
        ], $this->admin);

        $this->assertSame(20000, $manual->biaya_utama);
        $this->assertTrue($manual->biaya_manual);
        $this->assertFalse($sama->biaya_manual);
    }

    public function test_kamar_mengikuti_penempatan_saat_pindah_dan_kuota_tidak_reset(): void
    {
        $penghuni = $this->masuk('Devin', $this->kamar307, '2026-10-01');
        app(PenempatanService::class)->pindahKamar($penghuni, $this->kamar310, '2026-11-11', $this->admin);

        $sebelum = $this->laundry->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-03', 'penghuni_id' => $penghuni->id, 'berat_kg' => '3,5',
        ], $this->admin);
        $sesudah = $this->laundry->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-12', 'penghuni_id' => $penghuni->id, 'berat_kg' => '2,6',
        ], $this->admin);

        $this->assertSame($this->kamar307->id, $sebelum->kamar_id);
        $this->assertSame($this->kamar310->id, $sesudah->kamar_id);
        $this->assertSame(610, app(KuotaService::class)->statusBulan($penghuni->fresh(), '2026-11')['terpakai_centi']);
    }

    public function test_tanggal_di_luar_masa_tinggal_dan_status_booking_ditolak(): void
    {
        $penghuni = $this->masuk('Glenn', $this->kamar307, '2026-11-10');

        try {
            $this->laundry->catat($this->properti, [
                'jenis' => 'penghuni', 'tanggal' => '2026-11-05', 'penghuni_id' => $penghuni->id, 'berat_kg' => '3',
            ], $this->admin);
            $this->fail('Catatan sebelum tanggal masuk seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tanggal', $e->errors());
        }

        $booking = $this->masuk('Riko', $this->kamar310, '2026-11-01', 'booking');

        try {
            $this->laundry->catat($this->properti, [
                'jenis' => 'penghuni', 'tanggal' => '2026-11-05', 'penghuni_id' => $booking->id, 'berat_kg' => '3',
            ], $this->admin);
            $this->fail('Penghuni booking seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Booking', $e->errors()['penghuni_id'][0]);
        }

        $this->assertSame(0, Laundry::query()->count());
    }

    public function test_gedung_dan_tanpa_nama_tidak_mengurangi_kuota_lalu_tanpa_nama_bisa_ditetapkan(): void
    {
        $penghuni = $this->masuk('Devin', $this->kamar307, '2026-11-01');

        $this->laundry->catat($this->properti, ['jenis' => 'gedung', 'tanggal' => '2026-11-05', 'berat_kg' => '6'], $this->admin);
        $tanpaNama = $this->laundry->catat($this->properti, ['jenis' => 'tanpa_nama', 'tanggal' => '2026-11-06', 'berat_kg' => '4'], $this->admin);

        $this->assertNull($tanpaNama->penghuni_id);
        $this->assertSame(0, app(KuotaService::class)->statusBulan($penghuni, '2026-11')['terpakai_centi']);

        $ditetapkan = $this->laundry->tetapkanPemilik($tanpaNama, $penghuni, $this->admin);

        $this->assertSame('penghuni', $ditetapkan->jenis);
        $this->assertSame($this->kamar307->id, $ditetapkan->kamar_id);
        $this->assertSame(400, app(KuotaService::class)->statusBulan($penghuni, '2026-11')['terpakai_centi']);
        $this->assertTrue(AuditLog::query()->where('aksi', 'laundry_tetapkan_pemilik')->exists());
    }

    public function test_catatan_tanpa_berat_dan_tanpa_item_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->laundry->catat($this->properti, ['jenis' => 'gedung', 'tanggal' => '2026-11-05', 'berat_kg' => '', 'item' => []], $this->admin);
    }

    public function test_ref_idempoten_tidak_membuat_catatan_ganda(): void
    {
        $data = ['jenis' => 'gedung', 'tanggal' => '2026-11-05', 'berat_kg' => '3', 'ref' => 'form-abc-123'];

        $pertama = $this->laundry->catat($this->properti, $data, $this->admin);
        $kedua = $this->laundry->catat($this->properti, $data, $this->admin);

        $this->assertSame($pertama->id, $kedua->id);
        $this->assertSame(1, Laundry::query()->count());
    }

    public function test_ubah_mengganti_item_dan_hapus_lunak_bisa_dipulihkan_dengan_audit(): void
    {
        $catatan = $this->laundry->catat($this->properti, [
            'jenis' => 'gedung', 'tanggal' => '2026-11-05', 'berat_kg' => '3',
            'item' => [['nama' => 'Sprei', 'biaya' => 10000]],
        ], $this->admin);

        $diubah = $this->laundry->ubah($catatan, [
            'jenis' => 'gedung', 'tanggal' => '2026-11-05', 'berat_kg' => '3',
            'item' => [['nama' => 'Selimut', 'biaya' => 15000], ['nama' => 'Lainnya', 'biaya' => 5000]],
        ], $this->admin);

        $this->assertSame(20000, $diubah->item_biaya);
        $this->assertSame(['Selimut', 'Lainnya'], $diubah->item->pluck('nama')->all());

        $this->laundry->hapus($diubah, $this->admin);
        $this->assertSoftDeleted('laundry', ['id' => $catatan->id]);

        $this->laundry->pulihkan($diubah, $this->admin);
        $this->assertNotSoftDeleted('laundry', ['id' => $catatan->id]);

        $this->assertSame(
            ['laundry_tambah', 'laundry_ubah', 'laundry_hapus', 'laundry_pulihkan'],
            AuditLog::query()->where('subjek_type', Laundry::class)->orderBy('id')->pluck('aksi')->all()
        );
    }

    public function test_lengkapi_harga_hanya_mengisi_catatan_yang_biayanya_kosong(): void
    {
        $kosong = $this->laundry->catat($this->properti, ['jenis' => 'gedung', 'tanggal' => '2026-11-05', 'berat_kg' => '3'], $this->admin);
        $manual = $this->laundry->catat($this->properti, ['jenis' => 'gedung', 'tanggal' => '2026-11-06', 'berat_kg' => '2.4', 'biaya_utama' => 20000], $this->admin);
        $kosong->forceFill(['biaya_utama' => null])->save();

        $pratinjau = $this->laundry->pratinjauLengkapiHarga($this->properti);
        $this->assertSame(1, $pratinjau['jumlah']);
        $this->assertSame(24000, $pratinjau['total']);

        $this->assertSame(1, $this->laundry->lengkapiHarga($this->properti, $this->admin));
        $this->assertSame(24000, $kosong->fresh()->biaya_utama);
        $this->assertSame(20000, $manual->fresh()->biaya_utama);
        $this->assertSame(0, $this->laundry->pratinjauLengkapiHarga($this->properti)['jumlah']);
    }

    public function test_pratinjau_kuota_menghitung_sebelum_dan_sesudah_tanpa_menghitung_dobel_saat_ubah(): void
    {
        $penghuni = $this->masuk('Devin', $this->kamar307, '2026-11-01');
        $lama = $this->laundry->catat($this->properti, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-03', 'penghuni_id' => $penghuni->id, 'berat_kg' => '18',
        ], $this->admin);

        $baru = $this->laundry->pratinjau($this->properti, '2026-11-10', $penghuni, '2,4');
        $this->assertSame('307', $baru['kamar']);
        $this->assertSame(19200, $baru['biaya_otomatis']);
        $this->assertSame('18', $baru['kuota']['sebelum_kg']);
        $this->assertSame('20,4', $baru['kuota']['sesudah_kg']);
        $this->assertSame('lewat', $baru['kuota']['status']);

        $ubah = $this->laundry->pratinjau($this->properti, '2026-11-03', $penghuni, '15', $lama);
        $this->assertSame('0', $ubah['kuota']['sebelum_kg']);
        $this->assertSame('mendekati', $ubah['kuota']['status']);
    }

    public function test_policy_penjaga_hanya_catatan_sendiri_dalam_batas_jam(): void
    {
        $policy = app(LaundryPolicy::class);
        $milikPenjaga = $this->laundry->catat($this->properti, ['jenis' => 'gedung', 'tanggal' => '2026-11-05', 'berat_kg' => '3'], $this->penjaga);
        $milikAdmin = $this->laundry->catat($this->properti, ['jenis' => 'gedung', 'tanggal' => '2026-11-05', 'berat_kg' => '4'], $this->admin);

        $this->assertTrue($policy->update($this->penjaga, $milikPenjaga));
        $this->assertFalse($policy->update($this->penjaga, $milikAdmin));
        $this->assertTrue($policy->update($this->admin, $milikPenjaga));
        $this->assertFalse($policy->restore($this->penjaga, $milikPenjaga));
        $this->assertFalse($policy->lengkapiHarga($this->penjaga, $this->properti));

        $this->travel(25)->hours();
        $this->assertFalse($policy->update($this->penjaga, $milikPenjaga->fresh()));
        $this->assertTrue($policy->update($this->admin, $milikPenjaga->fresh()));
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
