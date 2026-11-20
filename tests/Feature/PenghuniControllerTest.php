<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Laundry;
use App\Models\Pengaturan;
use App\Models\Properti;
use App\Models\User;
use App\Services\PenempatanService;
use App\Services\PengaturanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenghuniControllerTest extends TestCase
{
    use RefreshDatabase;

    private Properti $propertiA;

    private Properti $propertiB;

    private User $adminA;

    private User $penjagaA;

    private User $adminB;

    private Kamar $kamarA1;

    private Kamar $kamarA2;

    private Kamar $kamarB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);

        $owner = User::factory()->create(['role' => Peran::Owner]);

        $this->propertiA = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata A',
            'nama_properti_laporan' => 'Dbata A',
            'status' => 'aktif',
        ]);

        $this->propertiB = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata B',
            'nama_properti_laporan' => 'Dbata B',
            'status' => 'aktif',
        ]);

        $this->adminA = User::factory()->create([
            'role' => Peran::Admin,
            'properti_id' => $this->propertiA->id,
        ]);

        $this->penjagaA = User::factory()->create([
            'role' => Peran::Penjaga,
            'properti_id' => $this->propertiA->id,
        ]);

        $this->adminB = User::factory()->create([
            'role' => Peran::Admin,
            'properti_id' => $this->propertiB->id,
        ]);

        $this->kamarA1 = Kamar::query()->create([
            'properti_id' => $this->propertiA->id,
            'nomor' => '101',
            'lantai' => 1,
            'aktif' => true,
        ]);

        $this->kamarA2 = Kamar::query()->create([
            'properti_id' => $this->propertiA->id,
            'nomor' => '102',
            'lantai' => 1,
            'aktif' => true,
        ]);

        $this->kamarB1 = Kamar::query()->create([
            'properti_id' => $this->propertiB->id,
            'nomor' => '201',
            'lantai' => 2,
            'aktif' => true,
        ]);
    }

    public function test_admin_melihat_daftar_dan_detail_penghuni_tanpa_inline_script_atau_style(): void
    {
        $service = app(PenempatanService::class);
        $penghuni = $service->masuk($this->propertiA, [
            'nama' => 'Glenn Wijaya',
            'alias' => 'Glen',
            'kamar_id' => $this->kamarA1->id,
            'tanggal_masuk' => '2026-11-01',
            'no_wa' => '081234567890',
        ], $this->adminA)['penghuni'];

        Laundry::query()->create([
            'properti_id' => $this->propertiA->id,
            'tanggal' => now('Asia/Jakarta')->toDateString(),
            'jenis' => 'penghuni',
            'penghuni_id' => $penghuni->id,
            'kamar_id' => $this->kamarA1->id,
            'berat_kg' => '21.00',
            'biaya_utama' => 168000,
            'dicatat_oleh' => $this->adminA->id,
        ]);

        $resIndex = $this->actingAs($this->adminA)->get(route('penghuni.index'));
        $resIndex->assertOk();
        $resIndex->assertSee('Glenn Wijaya');
        $resIndex->assertSee('<progress', false);
        $resIndex->assertSee('kuota-lewat', false);
        $this->assertSame(0, preg_match('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $resIndex->getContent()));
        $this->assertSame(0, preg_match('/<style\b[^>]*>/i', $resIndex->getContent()));

        $resShow = $this->actingAs($this->adminA)->get(route('penghuni.show', $penghuni));
        $resShow->assertOk();
        $resShow->assertSee('Glenn Wijaya');
        $resShow->assertSee('data-confirm-title', false);
        $resShow->assertSee('Sesuaikan Tagihan Lewat Kuota');
        $this->assertSame(0, preg_match('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $resShow->getContent()));
        $this->assertSame(0, preg_match('/<style\b[^>]*>/i', $resShow->getContent()));
    }

    public function test_isolasi_lintas_properti_ditolak_403(): void
    {
        $service = app(PenempatanService::class);
        $penghuniB = $service->masuk($this->propertiB, [
            'nama' => 'Penghuni Properti B',
            'kamar_id' => $this->kamarB1->id,
            'tanggal_masuk' => '2026-11-01',
        ], $this->adminB)['penghuni'];

        $this->actingAs($this->adminA)
            ->get(route('penghuni.show', $penghuniB))
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->post(route('penghuni.keluar', $penghuniB), [
                'tanggal_keluar' => '2026-11-15',
            ])
            ->assertForbidden();
    }

    public function test_saklar_k6_mengatur_izin_kontrak_penjaga_dan_blokir_override_kuota(): void
    {
        $service = app(PenempatanService::class);
        $penghuni = $service->masuk($this->propertiA, [
            'nama' => 'Devin Pratama',
            'kamar_id' => $this->kamarA1->id,
            'tanggal_masuk' => '2026-11-01',
        ], $this->adminA)['penghuni'];

        // Default K6 = 0 -> penjaga bisa melihat detail, tapi ditolak melakukan aksi kontrak
        $this->actingAs($this->penjagaA)
            ->get(route('penghuni.show', $penghuni))
            ->assertOk()
            ->assertSee('Devin Pratama');

        $this->actingAs($this->penjagaA)
            ->post(route('penghuni.store'), [
                'nama' => 'Baru Oleh Penjaga',
                'kamar_id' => $this->kamarA2->id,
                'tanggal_masuk' => '2026-11-15',
            ])
            ->assertForbidden();

        $this->actingAs($this->penjagaA)
            ->post(route('penghuni.pindah-kamar', $penghuni), [
                'kamar_tujuan_id' => $this->kamarA2->id,
                'tanggal_pindah' => '2026-11-10',
            ])
            ->assertForbidden();

        $this->actingAs($this->penjagaA)
            ->post(route('penghuni.keluar', $penghuni), [
                'tanggal_keluar' => '2026-11-15',
            ])
            ->assertForbidden();

        // Aktifkan saklar K6 = 1 untuk properti A
        Pengaturan::query()->updateOrCreate(
            [
                'properti_id' => $this->propertiA->id,
                'kunci' => 'penjaga_kelola_kontrak',
                'berlaku_mulai' => '2026-01-01',
            ],
            [
                'nilai' => '1',
                'diubah_oleh' => $this->adminA->id,
            ]
        );
        app(PengaturanService::class)->bersihkanCache($this->propertiA);

        $this->actingAs($this->penjagaA)
            ->post(route('penghuni.pindah-kamar', $penghuni), [
                'kamar_tujuan_id' => $this->kamarA2->id,
                'tanggal_pindah' => '2026-11-10',
            ])
            ->assertRedirect(route('penghuni.show', $penghuni));

        $this->assertDatabaseHas('audit_log', [
            'properti_id' => $this->propertiA->id,
            'user_id' => $this->penjagaA->id,
            'aksi' => 'penghuni_pindah_kamar',
            'subjek_id' => $penghuni->id,
        ]);

        // Penjaga tetap TIDAK BOLEH melakukan penyesuaian kuota manual meski K6 aktif
        $this->actingAs($this->penjagaA)
            ->post(route('penghuni.penyesuaian-kuota', $penghuni), [
                'bulan' => '2026-11',
                'nominal' => 15000,
            ])
            ->assertForbidden();

        // Admin dapat menyimpan dan mereset penyesuaian kuota manual
        $this->actingAs($this->adminA)
            ->post(route('penghuni.penyesuaian-kuota', $penghuni), [
                'bulan' => '2026-11',
                'nominal' => 15000,
                'catatan' => 'Diskon khusus',
            ])
            ->assertRedirect(route('penghuni.show', ['penghuni' => $penghuni, 'bulan' => '2026-11']));

        $this->assertDatabaseHas('penyesuaian_lewat_kuota', [
            'penghuni_id' => $penghuni->id,
            'bulan' => '2026-11',
            'nominal' => 15000,
        ]);
    }
}
