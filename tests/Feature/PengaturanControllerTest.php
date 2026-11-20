<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Properti;
use App\Models\User;
use App\Services\PengaturanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanControllerTest extends TestCase
{
    use RefreshDatabase;

    private Properti $properti;

    private User $owner;

    private User $admin;

    private User $penjaga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);

        $this->owner = User::factory()->create(['role' => Peran::Owner]);
        $this->properti = Properti::query()->create([
            'owner_id' => $this->owner->id,
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

    public function test_penjaga_ditolak_mengakses_dan_mengubah_pengaturan(): void
    {
        $this->actingAs($this->penjaga)
            ->get(route('pengaturan.index'))
            ->assertForbidden();

        $this->actingAs($this->penjaga)
            ->put(route('pengaturan.izin-penjaga'), [
                'jam_edit_penjaga' => 48,
                'penjaga_kelola_kontrak' => 1,
                'penjaga_ubah_catatan_lain' => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_dapat_membuka_halaman_pengaturan_dan_menyimpan_ketiga_tab_tanpa_inline_script(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pengaturan.index'));
        $response->assertOk()->assertSee('Pengaturan Properti');

        $html = $response->getContent();
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<style\b[^>]*>/i', $html);

        // Simpan aturan tarif & kuota
        $this->actingAs($this->admin)
            ->put(route('pengaturan.aturan'), [
                'berlaku_mulai' => '2026-12-01',
                'nama_properti' => "D'bata Residence",
                'tarif_per_kg' => 9000,
                'min_kg' => '2',
                'kuota_kg' => '22',
                'ambang_mendekati_persen' => 80,
                'toleransi_kg' => '1',
                'pembulatan' => 1000,
                'mode_kuota' => 'penghuni',
            ])
            ->assertRedirect(route('pengaturan.index', ['tab' => 'aturan']));

        $svc = app(PengaturanService::class);
        $this->assertSame(9000, $svc->tarifPerKg($this->properti, '2026-12-01'));
        $this->assertSame(8000, $svc->tarifPerKg($this->properti, '2026-11-20'));

        // Simpan izin penjaga (termasuk saklar catatan pengguna lain)
        $this->actingAs($this->admin)
            ->put(route('pengaturan.izin-penjaga'), [
                'jam_edit_penjaga' => 48,
                'penjaga_kelola_kontrak' => 1,
                'penjaga_ubah_catatan_lain' => 1,
            ])
            ->assertRedirect(route('pengaturan.index', ['tab' => 'izin']));

        $this->assertTrue($svc->penjagaBolehKelolaKontrak($this->properti));
        $this->assertTrue($svc->penjagaBolehUbahCatatanLain($this->properti));

        // Simpan & reset template WA
        $this->actingAs($this->admin)
            ->put(route('pengaturan.template-wa'), [
                'template_wa_tagihan' => 'Tagihan baru {nama} {total}',
                'template_wa_peringatan' => 'Peringatan baru {nama} {kuota_terpakai}',
                'template_wa_rekap' => 'Rekap baru {gedung} {rincian}',
            ])
            ->assertRedirect(route('pengaturan.index', ['tab' => 'whatsapp']));

        $this->assertSame('Tagihan baru {nama} {total}', $svc->nilai($this->properti, 'template_wa_tagihan'));

        $this->actingAs($this->admin)
            ->post(route('pengaturan.template-wa.reset'), ['kunci' => 'template_wa_tagihan'])
            ->assertRedirect(route('pengaturan.index', ['tab' => 'whatsapp']));

        $this->assertSame(
            PengaturanSeeder::nilaiBawaan($this->properti->nama_properti_laporan)['template_wa_tagihan'],
            $svc->nilai($this->properti, 'template_wa_tagihan')
        );
    }
}
