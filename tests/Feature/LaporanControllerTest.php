<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Properti;
use App\Models\User;
use App\Services\LaundryService;
use App\Services\PenempatanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanControllerTest extends TestCase
{
    use RefreshDatabase;

    private Properti $properti;

    private Properti $propertiLain;

    private User $admin;

    private User $penjaga;

    private Kamar $kamar101;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);

        $owner = User::factory()->create(['role' => Peran::Owner]);
        $this->properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => "D'bata Residence",
            'nama_properti_laporan' => "D'bata Residence",
            'status' => 'aktif',
        ]);
        $this->propertiLain = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Properti Sebelah',
            'nama_properti_laporan' => 'Properti Sebelah',
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

        $this->kamar101 = Kamar::query()->create([
            'properti_id' => $this->properti->id,
            'nomor' => '101',
            'lantai' => 1,
            'aktif' => true,
        ]);
    }

    public function test_admin_dan_penjaga_dapat_melihat_laporan_tanpa_inline_script_atau_style(): void
    {
        $penghuni = app(PenempatanService::class)->masuk($this->properti, [
            'nama' => 'Travis',
            'kamar_id' => $this->kamar101->id,
            'tanggal_masuk' => '2026-10-01',
            'no_wa' => '081234567890',
        ], $this->admin)['penghuni'];

        app(LaundryService::class)->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-09',
            'penghuni_id' => $penghuni->id,
            'berat_kg' => '3.8',
            'item' => [
                ['nama' => 'Bed cover', 'biaya' => 20000],
            ],
        ], $this->admin);

        // Data properti lain tidak boleh bocor
        $kamarLain = Kamar::query()->create([
            'properti_id' => $this->propertiLain->id,
            'nomor' => '999',
            'lantai' => 9,
            'aktif' => true,
        ]);
        $penghuniLain = app(PenempatanService::class)->masuk($this->propertiLain, [
            'nama' => 'Rahasia Sebelah',
            'kamar_id' => $kamarLain->id,
            'tanggal_masuk' => '2026-10-01',
        ], $this->admin)['penghuni'];
        app(LaundryService::class)->catat($this->propertiLain, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-09',
            'penghuni_id' => $penghuniLain->id,
            'berat_kg' => '5',
        ], $this->admin);

        foreach ([$this->admin, $this->penjaga] as $pengguna) {
            $response = $this->actingAs($pengguna)->get(route('laporan.index', [
                'periode' => 'rentang',
                'dari' => '2026-10-01',
                'sampai' => '2026-10-31',
            ]));

            $response->assertOk()
                ->assertSee("D'bata Residence")
                ->assertSee('LAPORAN LAUNDRY')
                ->assertSee('Travis')
                ->assertSee('3,8 kg')
                ->assertSee('Bed cover')
                ->assertSee('Rp20.000')
                ->assertDontSee('Rahasia Sebelah');

            $html = $response->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $html);
            $this->assertDoesNotMatchRegularExpression('/<style\b[^>]*>/i', $html);
        }
    }

    public function test_ekspor_csv_mengembalikan_stream_utf8_berisi_data_terfilter(): void
    {
        $penghuni = app(PenempatanService::class)->masuk($this->properti, [
            'nama' => 'Glori',
            'kamar_id' => $this->kamar101->id,
            'tanggal_masuk' => '2026-10-01',
        ], $this->admin)['penghuni'];

        app(LaundryService::class)->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-20',
            'penghuni_id' => $penghuni->id,
            'berat_kg' => '2.4',
            'item' => [
                ['nama' => 'Bed cover', 'biaya' => 20000],
            ],
        ], $this->admin);

        $response = $this->actingAs($this->admin)->get(route('laporan.csv', [
            'periode' => 'rentang',
            'dari' => '2026-10-01',
            'sampai' => '2026-10-31',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $konten = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $konten);
        $this->assertStringContainsString('Glori', $konten);
        $this->assertStringContainsString('Bed cover', $konten);
        $this->assertStringContainsString('20000', $konten);
    }
}
