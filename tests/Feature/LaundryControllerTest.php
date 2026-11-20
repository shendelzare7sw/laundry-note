<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use App\Services\LaundryService;
use App\Services\PenempatanService;
use Carbon\CarbonImmutable;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaundryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Properti $propertiA;

    private Properti $propertiB;

    private User $adminA;

    private User $penjagaA;

    private User $adminB;

    private Kamar $kamarA1;

    private Kamar $kamarB1;

    private Penghuni $penghuniA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);

        $this->owner = User::factory()->create(['role' => Peran::Owner]);

        $this->propertiA = Properti::query()->create([
            'owner_id' => $this->owner->id,
            'nama' => 'Dbata A',
            'nama_properti_laporan' => 'Dbata A',
            'status' => 'aktif',
        ]);

        $this->propertiB = Properti::query()->create([
            'owner_id' => $this->owner->id,
            'nama' => 'Dbata B',
            'nama_properti_laporan' => 'Dbata B',
            'status' => 'aktif',
        ]);

        $this->adminA = User::factory()->create(['role' => Peran::Admin, 'properti_id' => $this->propertiA->id]);
        $this->penjagaA = User::factory()->create(['role' => Peran::Penjaga, 'properti_id' => $this->propertiA->id]);
        $this->adminB = User::factory()->create(['role' => Peran::Admin, 'properti_id' => $this->propertiB->id]);

        $this->kamarA1 = Kamar::query()->create(['properti_id' => $this->propertiA->id, 'nomor' => '101', 'lantai' => 1, 'aktif' => true]);
        $this->kamarB1 = Kamar::query()->create(['properti_id' => $this->propertiB->id, 'nomor' => '201', 'lantai' => 2, 'aktif' => true]);

        $this->penghuniA = app(PenempatanService::class)->masuk($this->propertiA, [
            'nama' => 'Devin',
            'kamar_id' => $this->kamarA1->id,
            'tanggal_masuk' => '2026-10-01',
        ], $this->adminA)['penghuni'];
    }

    public function test_owner_admin_dan_penjaga_dapat_mengakses_dan_menyimpan_laundry_multi_item(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00', 'Asia/Jakarta'));

        $this->actingAs($this->owner)
            ->withSession(['properti_aktif_id' => $this->propertiA->id])
            ->get(route('laundry.index'))
            ->assertOk();

        $this->actingAs($this->penjagaA)
            ->get(route('laundry.create'))
            ->assertOk();

        $res = $this->actingAs($this->adminA)->post(route('laundry.store'), [
            'jenis' => 'penghuni',
            'tanggal' => '2026-11-20',
            'penghuni_id' => $this->penghuniA->id,
            'berat_kg' => '2,5',
            'catatan' => 'Setoran pagi',
            'ref' => 'req-multi-1',
            'item' => [
                ['nama' => 'Selimut', 'jumlah' => 1, 'biaya' => 15000],
                ['nama' => 'Bed cover', 'jumlah' => 1, 'berat_kg' => '3,5', 'biaya' => 25000],
                ['nama' => '', 'jumlah' => '1', 'berat_kg' => '', 'biaya' => ''],
            ],
        ]);

        $res->assertRedirect(route('laundry.index'));

        $catatan = Laundry::query()->with('item')->firstOrFail();
        $this->assertSame(20000, $catatan->biaya_utama);
        $this->assertSame(40000, $catatan->item_biaya);
        $this->assertCount(2, $catatan->item);
    }

    public function test_validasi_tanggal_masa_depan_berat_besar_kosong_dan_pesan_bahasa_indonesia(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00', 'Asia/Jakarta'));

        $resKosong = $this->actingAs($this->adminA)->post(route('laundry.store'), [
            'jenis' => 'gedung',
            'tanggal' => '2026-11-21',
            'berat_kg' => '',
            'item' => [],
        ]);

        $resKosong->assertSessionHasErrors(['tanggal', 'berat_kg']);
        $semuaGalat = implode(' ', session('errors')->all());
        $this->assertStringNotContainsString('validation.', $semuaGalat);
        $this->assertStringContainsString('masa depan', session('errors')->first('tanggal'));

        $resBeratBesar = $this->actingAs($this->adminA)->post(route('laundry.store'), [
            'jenis' => 'gedung',
            'tanggal' => '2026-11-20',
            'berat_kg' => '35',
        ]);

        $resBeratBesar->assertSessionHasErrors('konfirmasi_berat_besar');

        $this->actingAs($this->adminA)->post(route('laundry.store'), [
            'jenis' => 'gedung',
            'tanggal' => '2026-11-20',
            'berat_kg' => '35',
            'konfirmasi_berat_besar' => '1',
        ])->assertRedirect(route('laundry.index'));
    }

    public function test_hak_penjaga_dibatasi_pemilik_sendiri_jam_edit_dan_admin_kelola_terhapus_lengkapi_harga(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00', 'Asia/Jakarta'));
        $service = app(LaundryService::class);

        $milikPenjaga = $service->catat($this->propertiA, [
            'jenis' => 'gedung', 'tanggal' => '2026-11-20', 'berat_kg' => '3',
        ], $this->penjagaA);

        $milikAdmin = $service->catat($this->propertiA, [
            'jenis' => 'gedung', 'tanggal' => '2026-11-20', 'berat_kg' => '4',
        ], $this->adminA);
        $milikAdmin->forceFill(['biaya_utama' => null])->save();

        // Penjaga bisa mengedit catatannya sendiri dan tidak melihat tombol pulihkan saat menghapus
        $this->actingAs($this->penjagaA)->get(route('laundry.edit', $milikPenjaga))->assertOk();
        $this->actingAs($this->penjagaA)->get(route('laundry.edit', $milikAdmin))->assertForbidden();
        $this->actingAs($this->penjagaA)->put(route('laundry.update', $milikAdmin), [
            'jenis' => 'gedung', 'tanggal' => '2026-11-20', 'berat_kg' => '4',
        ])->assertForbidden();
        $this->actingAs($this->penjagaA)->get(route('laundry.terhapus'))->assertForbidden();
        $this->actingAs($this->penjagaA)->post(route('laundry.lengkapi-harga'))->assertForbidden();

        // Penjaga menghapus catatannya sebelum lewat 24 jam -> berhasil, tapi toast tanpa aksi pulihkan
        $penjagaBaru = $service->catat($this->propertiA, [
            'jenis' => 'gedung', 'tanggal' => '2026-11-20', 'berat_kg' => '2',
        ], $this->penjagaA);
        $resHapusPenjaga = $this->actingAs($this->penjagaA)->delete(route('laundry.destroy', $penjagaBaru));
        $resHapusPenjaga->assertRedirect(route('laundry.index'));
        $this->assertArrayNotHasKey('aksi_url', session('toast'));

        // Penjaga tidak bisa mengubah setelah lewat 24 jam
        $this->travel(25)->hours();
        $this->actingAs($this->penjagaA)->get(route('laundry.edit', $milikPenjaga))->assertForbidden();
        $this->actingAs($this->penjagaA)->delete(route('laundry.destroy', $milikPenjaga))->assertForbidden();

        // Admin menghapus & mendapatkan toast undo, memulihkan dari /laundry, lalu memulihkan dari /laundry/terhapus
        $resHapus = $this->actingAs($this->adminA)->delete(route('laundry.destroy', $milikPenjaga));
        $resHapus->assertRedirect(route('laundry.index'));
        $toast = session('toast');
        $this->assertSame(route('laundry.pulihkan', $milikPenjaga->id), $toast['aksi_url'] ?? null);

        $this->actingAs($this->penjagaA)->post(route('laundry.pulihkan', $milikPenjaga->id))->assertForbidden();

        $this->actingAs($this->adminA)->get(route('laundry.terhapus'))->assertOk();
        $this->actingAs($this->adminA)->post(route('laundry.pulihkan', $milikPenjaga->id))->assertRedirect(route('laundry.terhapus'));
        $this->assertNotSoftDeleted('laundry', ['id' => $milikPenjaga->id]);

        $this->actingAs($this->adminA)->from(route('laundry.index'))->post(route('laundry.pulihkan', $penjagaBaru->id))->assertRedirect(route('laundry.index'));
        $this->assertNotSoftDeleted('laundry', ['id' => $penjagaBaru->id]);

        $this->actingAs($this->adminA)->post(route('laundry.lengkapi-harga'))->assertRedirect(route('laundry.index'));
        $this->assertSame(32000, $milikAdmin->fresh()->biaya_utama);
    }

    public function test_admin_menetapkan_pemilik_tanpa_nama_dan_isolasi_antar_properti_ketat(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00', 'Asia/Jakarta'));

        $tanpaNama = app(LaundryService::class)->catat($this->propertiA, [
            'jenis' => 'tanpa_nama', 'tanggal' => '2026-11-20', 'berat_kg' => '3',
        ], $this->adminA);

        $this->actingAs($this->adminA)
            ->post(route('laundry.tetapkan-pemilik', $tanpaNama), ['penghuni_id' => $this->penghuniA->id])
            ->assertRedirect(route('laundry.index'));

        $this->assertSame('penghuni', $tanpaNama->fresh()->jenis);
        $this->assertSame($this->penghuniA->id, $tanpaNama->fresh()->penghuni_id);

        // Admin properti B ditolak 403 untuk semua aksi atas catatan properti A
        $this->actingAs($this->adminB)->get(route('laundry.edit', $tanpaNama))->assertForbidden();
        $this->actingAs($this->adminB)->put(route('laundry.update', $tanpaNama), [
            'jenis' => 'gedung', 'tanggal' => '2026-11-20', 'berat_kg' => '3',
        ])->assertForbidden();
        $this->actingAs($this->adminB)->post(route('laundry.tetapkan-pemilik', $tanpaNama), [
            'penghuni_id' => $this->penghuniA->id,
        ])->assertForbidden();
        $this->actingAs($this->adminB)->delete(route('laundry.destroy', $tanpaNama))->assertForbidden();

        $tanpaNama->delete();
        $this->actingAs($this->adminB)->post(route('laundry.pulihkan', $tanpaNama->id))->assertForbidden();
    }

    public function test_endpoint_info_kuota_mengembalikan_pratinjau_dan_daftar_penghuni_sesuai_tanggal(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00', 'Asia/Jakarta'));

        app(LaundryService::class)->catat($this->propertiA, [
            'jenis' => 'penghuni', 'tanggal' => '2026-11-10', 'penghuni_id' => $this->penghuniA->id, 'berat_kg' => '18',
        ], $this->adminA);

        $res = $this->actingAs($this->adminA)->getJson(route('laundry.info-kuota', [
            'tanggal' => '2026-11-20',
            'penghuni_id' => $this->penghuniA->id,
            'berat_kg' => '2,5',
        ]));

        $res->assertOk()
            ->assertJsonPath('kamar', '101')
            ->assertJsonPath('biaya_otomatis', 20000)
            ->assertJsonPath('kuota.sebelum_kg', '18')
            ->assertJsonPath('kuota.sesudah_kg', '20,5')
            ->assertJsonPath('kuota.status', 'lewat')
            ->assertJsonCount(1, 'daftar_penghuni');
    }
}
