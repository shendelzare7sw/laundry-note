<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Properti;
use App\Models\User;
use App\Policies\LaundryPolicy;
use App\Services\LaundryService;
use App\Services\PenempatanService;
use App\Services\PengaturanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanServiceTest extends TestCase
{
    use RefreshDatabase;

    private PengaturanService $pengaturanService;

    private Properti $properti;

    private User $admin;

    private User $penjaga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);
        $this->pengaturanService = app(PengaturanService::class);

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
        $this->penjaga = User::factory()->create([
            'role' => Peran::Penjaga,
            'properti_id' => $this->properti->id,
        ]);
    }

    public function test_tarif_berversi_berlaku_mulai_desember_tidak_mengubah_november(): void
    {
        $this->pengaturanService->simpanAturanBerversi(
            properti: $this->properti,
            nilaiBaru: [
                'tarif_per_kg' => '10000',
                'kuota_kg' => '25',
            ],
            berlakuMulai: '2026-12-01',
            aktor: $this->admin,
        );

        // November tetap memakai tarif lama Rp8.000 & kuota 20 kg
        $this->assertSame(8000, $this->pengaturanService->tarifPerKg($this->properti, '2026-11-20'));
        $this->assertSame(2000, $this->pengaturanService->kuotaKgCenti($this->properti, '2026-11-20'));

        // Desember otomatis memakai tarif baru Rp10.000 & kuota 25 kg
        $this->assertSame(10000, $this->pengaturanService->tarifPerKg($this->properti, '2026-12-01'));
        $this->assertSame(2500, $this->pengaturanService->kuotaKgCenti($this->properti, '2026-12-01'));

        $this->assertDatabaseHas('audit_log', [
            'properti_id' => $this->properti->id,
            'aksi' => 'pengaturan_aturan_ubah',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_saklar_penjaga_ubah_catatan_lain_mengatur_izin_edit_laundry_policy(): void
    {
        $kamar = Kamar::query()->create([
            'properti_id' => $this->properti->id,
            'nomor' => '101',
            'lantai' => 1,
            'aktif' => true,
        ]);
        $penghuni = app(PenempatanService::class)->masuk($this->properti, [
            'nama' => 'Travis',
            'kamar_id' => $kamar->id,
            'tanggal_masuk' => '2026-11-01',
        ], $this->admin)['penghuni'];

        $catatanAdmin = app(LaundryService::class)->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-11-20',
            'penghuni_id' => $penghuni->id,
            'berat_kg' => '3',
        ], $this->admin);

        $policy = app(LaundryPolicy::class);

        // Default nonaktif: penjaga tidak boleh ubah catatan admin
        $this->assertFalse($this->pengaturanService->penjagaBolehUbahCatatanLain($this->properti));
        $this->assertFalse($policy->update($this->penjaga, $catatanAdmin));

        // Aktifkan saklar izin ubah catatan pengguna lain
        $this->pengaturanService->simpanIzinPenjaga($this->properti, [
            'jam_edit_penjaga' => '24',
            'penjaga_kelola_kontrak' => '1',
            'penjaga_ubah_catatan_lain' => '1',
        ], $this->admin);

        $this->assertTrue($this->pengaturanService->penjagaBolehKelolaKontrak($this->properti));
        $this->assertTrue($this->pengaturanService->penjagaBolehUbahCatatanLain($this->properti));
        $this->assertTrue($policy->update($this->penjaga, $catatanAdmin));

        // Tetap menghormati batas jam_edit_penjaga
        $this->travel(25)->hours();
        $this->assertFalse($policy->update($this->penjaga, $catatanAdmin->fresh()));

        // Kosongkan jam_edit_penjaga -> tanpa batas waktu edit
        $this->pengaturanService->simpanIzinPenjaga($this->properti, [
            'jam_edit_penjaga' => '',
            'penjaga_kelola_kontrak' => '1',
            'penjaga_ubah_catatan_lain' => '1',
        ], $this->admin);

        $this->assertTrue($policy->update($this->penjaga, $catatanAdmin->fresh()));
    }

    public function test_simpan_dan_reset_template_whatsapp(): void
    {
        $this->pengaturanService->simpanTemplateWa($this->properti, [
            'template_wa_tagihan' => 'Tagihan khusus {nama}: {total}',
        ], $this->admin);

        $this->assertSame(
            'Tagihan khusus {nama}: {total}',
            $this->pengaturanService->nilai($this->properti, 'template_wa_tagihan')
        );

        $this->pengaturanService->resetTemplateWa($this->properti, 'template_wa_tagihan', $this->admin);

        $this->assertSame(
            PengaturanSeeder::nilaiBawaan($this->properti->nama_properti_laporan)['template_wa_tagihan'],
            $this->pengaturanService->nilai($this->properti, 'template_wa_tagihan')
        );
    }
}
