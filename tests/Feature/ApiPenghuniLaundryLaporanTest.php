<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Properti;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiPenghuniLaundryLaporanTest extends TestCase
{
    use RefreshDatabase;

    private Properti $properti;

    private User $admin;

    private string $adminToken;

    private Kamar $kamar307;

    private Kamar $kamar310;

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
        $this->admin = User::factory()->create([
            'role' => Peran::Admin,
            'properti_id' => $this->properti->id,
            'aktif' => true,
        ]);
        $this->adminToken = $this->admin->createToken('android')->plainTextToken;

        $this->kamar307 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '307', 'lantai' => 3, 'aktif' => true]);
        $this->kamar310 = Kamar::query()->create(['properti_id' => $this->properti->id, 'nomor' => '310', 'lantai' => 3, 'aktif' => true]);
    }

    public function test_siklus_lengkap_penghuni_pindah_kamar_laundry_multi_item_laporan_dan_wa(): void
    {
        // 1. Daftar penghuni masuk di kamar 307
        $masukRes = $this->withToken($this->adminToken)
            ->postJson('/api/v1/penghuni', [
                'nama' => 'Devin Hong',
                'kamar_id' => $this->kamar307->id,
                'status' => 'aktif',
                'tanggal_masuk' => '2026-10-01',
                'jangka_waktu_sewa' => 'bulanan',
                'no_wa' => '081234567890',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kamar_nomor', '307')
            ->assertJsonPath('data.tanggal_keluar_rencana', '2026-11-01');

        $penghuniId = (int) $masukRes->json('data.id');

        // 2. Catat laundry multi-item + idempotensi ref
        $catatRes = $this->withToken($this->adminToken)
            ->postJson('/api/v1/laundry', [
                'jenis' => 'penghuni',
                'tanggal' => '2026-10-05',
                'penghuni_id' => $penghuniId,
                'berat_kg' => '12.5',
                'ref' => 'mobile-ref-001',
                'item' => [
                    ['nama' => 'Bed cover', 'jumlah' => 1, 'biaya' => 20000],
                    ['nama' => 'Selimut', 'jumlah' => 1, 'biaya' => 15000],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.berat_centi', 1250)
            ->assertJsonPath('data.item_biaya', 35000)
            ->assertJsonCount(2, 'data.item');

        $laundryId = (int) $catatRes->json('data.id');

        // Kirim ulang dengan ref sama -> idempoten (mengembalikan ID sama, bukan duplikat)
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/laundry', [
                'jenis' => 'penghuni',
                'tanggal' => '2026-10-05',
                'penghuni_id' => $penghuniId,
                'berat_kg' => '12.5',
                'ref' => 'mobile-ref-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $laundryId);

        // Info kuota pada tanggal sama mendeteksi duplikat 1-tanggal-1-catatan
        $this->withToken($this->adminToken)
            ->getJson("/api/v1/laundry/info-kuota?tanggal=2026-10-05&penghuni_id={$penghuniId}")
            ->assertOk()
            ->assertJsonPath('duplikat.id', $laundryId);

        // 3. Pindah ke kamar 310 (kuota tidak ter-reset)
        $this->withToken($this->adminToken)
            ->postJson("/api/v1/penghuni/{$penghuniId}/pindah-kamar", [
                'kamar_tujuan_id' => $this->kamar310->id,
                'tanggal_pindah' => '2026-10-11',
            ])
            ->assertOk()
            ->assertJsonPath('penempatan.kamar_nomor', '310');

        // Catat laundry kedua di kamar baru -> kumulatif 23,0 kg (lewat 3,0 kg)
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/laundry', [
                'jenis' => 'penghuni',
                'tanggal' => '2026-10-15',
                'penghuni_id' => $penghuniId,
                'berat_kg' => '10.5',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kamar_nomor', '310')
            ->assertJsonPath('data.lewat_kuota', true);

        // 4. Penyesuaian manual tagihan lewat kuota via API
        $this->withToken($this->adminToken)
            ->postJson("/api/v1/penghuni/{$penghuniId}/penyesuaian-kuota", [
                'bulan' => '2026-10',
                'nominal' => 20000,
                'catatan' => 'Kesepakatan mobile',
            ])
            ->assertOk()
            ->assertJsonPath('kuota.tagihan_lewat_kuota', 20000);

        // 5. Cek Laporan API (jalur 307→310, total 23,0 kg, bayar terpisah 35.000, tagihan lewat 20.000)
        $this->withToken($this->adminToken)
            ->getJson('/api/v1/laporan?periode=rentang&dari=2026-10-01&sampai=2026-10-31')
            ->assertOk()
            ->assertJsonPath('ringkasan.total_kg_label', '23,0')
            ->assertJsonPath('ringkasan.total_tagihan_lewat', 20000)
            ->assertJsonPath('ringkasan.total_bayar_terpisah', 35000)
            ->assertJsonPath('kartu_penghuni.0.label_kamar', '307→310')
            ->assertJsonPath('kartu_penghuni.0.boleh_sesuaikan_kuota', true);

        // 6. Cek WA Tagihan (hanya tagihan lewat kuota + item terpisah, bukan biaya utama)
        $waRes = $this->withToken($this->adminToken)
            ->getJson("/api/v1/wa/tagihan/{$penghuniId}?bulan=2026-10")
            ->assertOk()
            ->assertJsonPath('total_tagihan', 55000);

        $pesanWa = (string) $waRes->json('pesan');
        $this->assertStringContainsString('📦 Lewat Kuota Laundry', $pesanWa);
        $this->assertStringContainsString('Biaya: Rp 20.000', $pesanWa);
        $this->assertStringContainsString('🧺 Tagihan Terpisah', $pesanWa);
        $this->assertStringContainsString('Jumlah item: 2 item', $pesanWa);
        $this->assertStringContainsString('💰 Total Tagihan: Rp 55.000', $pesanWa);

        // 7. Hapus lunak & pulihkan via API
        $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/laundry/{$laundryId}")
            ->assertOk()
            ->assertJsonPath('boleh_pulihkan', true);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/laundry/terhapus')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withToken($this->adminToken)
            ->postJson("/api/v1/laundry/{$laundryId}/pulihkan")
            ->assertOk()
            ->assertJsonPath('data.id', $laundryId);

        // 8. Daftar laundry memakai meta ?tampil=N
        $this->withToken($this->adminToken)
            ->getJson('/api/v1/laundry?periode=rentang&dari=2026-10-01&sampai=2026-10-31&tampil=20')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.ditampilkan', 2)
            ->assertJsonPath('meta.ada_lagi', false);
    }
}
