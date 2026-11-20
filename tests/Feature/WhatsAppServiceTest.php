<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Properti;
use App\Models\User;
use App\Services\LaundryService;
use App\Services\PenempatanService;
use App\Services\WhatsAppService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppService $waService;

    private PenempatanService $penempatanService;

    private LaundryService $laundryService;

    private Properti $properti;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);
        $this->waService = app(WhatsAppService::class);
        $this->penempatanService = app(PenempatanService::class);
        $this->laundryService = app(LaundryService::class);

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
    }

    public function test_normalisasi_nomor_whatsapp_seragam_untuk_berbagai_format(): void
    {
        $this->assertSame('6281234567890', $this->waService->normalisasiNomor('0812-3456-7890'));
        $this->assertSame('6281234567890', $this->waService->normalisasiNomor('+62 812 3456 7890'));
        $this->assertSame('6281234567890', $this->waService->normalisasiNomor('6281234567890'));
        $this->assertSame('6281234567890', $this->waService->normalisasiNomor('81234567890'));
        $this->assertNull($this->waService->normalisasiNomor(''));
        $this->assertNull($this->waService->normalisasiNomor('123'));
    }

    public function test_render_template_tidak_menyisakan_placeholder_mentah(): void
    {
        $hasil = $this->waService->renderTemplate(
            "Halo *{nama}* ({kamar})\n{variabel_tidak_dikenal}\n\n\nTotal: {total}",
            [
                'nama' => 'Glenn',
                'kamar' => '302',
                'total' => 'Rp39.000',
            ]
        );

        $this->assertStringContainsString('Halo *Glenn* (302)', $hasil);
        $this->assertStringContainsString('Total: Rp39.000', $hasil);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z0-9_]+\}/i', $hasil);
    }

    public function test_pesan_tagihan_memuat_rincian_item_terpisah_dan_total_tagihan(): void
    {
        $kamar = Kamar::query()->create([
            'properti_id' => $this->properti->id,
            'nomor' => '302',
            'lantai' => 3,
            'aktif' => true,
        ]);

        $penghuni = $this->penempatanService->masuk($this->properti, [
            'nama' => 'Glenn',
            'kamar_id' => $kamar->id,
            'tanggal_masuk' => '2026-10-01',
            'no_wa' => '081234567890',
        ], $this->admin)['penghuni'];

        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-13',
            'penghuni_id' => $penghuni->id,
            'berat_kg' => '23',
            'item' => [
                ['nama' => 'Selimut', 'biaya' => 15000],
            ],
        ], $this->admin);

        $hasil = $this->waService->pesanTagihanDariPenghuni($this->properti, $penghuni, '2026-10');

        $this->assertSame('6281234567890', $hasil['nomor']);
        $this->assertNotNull($hasil['url']);
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $hasil['url']);
        $this->assertStringContainsString('Halo *Glenn* (Kamar 302)', $hasil['pesan']);
        $this->assertStringContainsString('Selimut', $hasil['pesan']);
        $this->assertStringContainsString('23,0/20,0 kg', $hasil['pesan']);
        $this->assertStringContainsString('Tagihan lewat kuota 3,0 kg: Rp24.000', $hasil['pesan']);
        $this->assertStringContainsString('Bayar terpisah (1 item): Rp15.000', $hasil['pesan']);
        $this->assertStringContainsString('*Total Tagihan: Rp39.000*', $hasil['pesan']);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z0-9_]+\}/i', $hasil['pesan']);
    }

    public function test_pesan_peringatan_kuota_dan_rekap_gedung_panjang(): void
    {
        $kamar = Kamar::query()->create([
            'properti_id' => $this->properti->id,
            'nomor' => '205',
            'lantai' => 2,
            'aktif' => true,
        ]);

        $penghuni = $this->penempatanService->masuk($this->properti, [
            'nama' => 'Karen',
            'kamar_id' => $kamar->id,
            'tanggal_masuk' => '2026-10-01',
            'no_wa' => '+62 813-9999-8888',
        ], $this->admin)['penghuni'];

        $this->laundryService->catat($this->properti, [
            'jenis' => 'penghuni',
            'tanggal' => '2026-10-10',
            'penghuni_id' => $penghuni->id,
            'berat_kg' => '18.5',
        ], $this->admin);

        $peringatan = $this->waService->pesanPeringatanKuota($this->properti, $penghuni, null, '2026-10');

        $this->assertSame('6281399998888', $peringatan['nomor']);
        $this->assertStringContainsString('18,5/20,0 kg', $peringatan['pesan']);
        $this->assertStringContainsString('1,5 kg', $peringatan['pesan']);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z0-9_]+\}/i', $peringatan['pesan']);

        // Rekap panjang (> 1800 karakter) tidak menyediakan url wa.me agar diarahkan ke Salin Teks
        $kartuDummy = [];
        for ($i = 1; $i <= 35; $i++) {
            $kartuDummy[] = [
                'penghuni' => $penghuni,
                'label_kamar' => (string) (100 + $i),
                'total_kg_label' => '15,0',
                'biaya_utama' => 120000,
                'tagihan_lewat_kuota' => 0,
                'bayar_terpisah' => 0,
                'baris' => [
                    ['tipe' => 'utama', 'tanggal_label' => '10 Okt 2026', 'berat_label' => '15,0', 'biaya' => 120000, 'merah' => false],
                ],
            ];
        }

        $rekap = $this->waService->pesanRekapGedung($this->properti, [
            'nama_properti' => "D'bata Residence",
            'rentang' => ['label' => '1 Okt 2026 - 31 Okt 2026'],
            'kartu_penghuni' => $kartuDummy,
            'kartu_lainnya' => [],
            'ringkasan' => [
                'total_kg_label' => '525,0',
                'jumlah_penghuni' => 35,
                'total_biaya_utama' => 4200000,
                'total_tagihan_lewat' => 0,
                'total_bayar_terpisah' => 0,
            ],
        ]);

        $this->assertTrue($rekap['terlalu_panjang']);
        $this->assertNull($rekap['url']);
        $this->assertStringContainsString('*REKAP LAUNDRY D\'bata Residence', $rekap['pesan']);
        $this->assertStringContainsString('*TOTAL KESELURUHAN*', $rekap['pesan']);
    }
}
