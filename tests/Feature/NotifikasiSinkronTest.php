<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Properti;
use App\Models\User;
use App\Services\PenempatanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotifikasiSinkronTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutasi_dari_mobile_api_langsung_terbaca_di_notifikasi_web_dan_api(): void
    {
        $this->seed(PengaturanSeeder::class);

        $owner = User::factory()->create([
            'name' => 'Pemilik Kos',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);
        $properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Kos Sinkron',
            'nama_properti_laporan' => 'Kos Sinkron',
            'status' => 'aktif',
        ]);

        $penjaga = User::factory()->create([
            'name' => 'Budi Penjaga',
            'role' => Peran::Penjaga,
            'properti_id' => $properti->id,
            'aktif' => true,
        ]);

        $kamar = Kamar::query()->create([
            'properti_id' => $properti->id,
            'nomor' => '101',
            'lantai' => 1,
        ]);

        $penghuni = app(PenempatanService::class)->masuk($properti, [
            'nama' => 'Andi',
            'kamar_id' => $kamar->id,
            'tanggal_masuk' => '2026-11-01',
            'status' => 'aktif',
        ], $owner)['penghuni'];

        // 1. Penjaga input laundry via Mobile API v1
        Sanctum::actingAs($penjaga);
        $this->postJson('/api/v1/laundry', [
            'jenis' => 'penghuni',
            'tanggal' => '2026-11-20',
            'penghuni_id' => $penghuni->id,
            'berat_kg' => '2.1',
            'ref' => 'notif-sync-test-1',
        ], ['X-Properti-Id' => (string) $properti->id])
            ->assertCreated();

        // 2. Bell notifikasi Web Owner langsung menerima aktivitas dari Mobile API
        $webRes = $this->actingAs($owner)
            ->withSession(['properti_aktif_id' => $properti->id])
            ->getJson(route('notifikasi.index'))
            ->assertOk();

        $webItems = collect($webRes->json('items'));
        $laundryNotif = $webItems->first(fn ($i) => str_contains((string) ($i['judul'] ?? ''), 'Laundry baru dicatat (Budi Penjaga)'));
        $this->assertNotNull($laundryNotif, 'Aktivitas laundry dari Mobile API harus muncul di bell Web.');
        $this->assertStringContainsString('Rp17.000', (string) $laundryNotif['keterangan']);

        // 3. Endpoint API v1 /api/v1/notifikasi untuk Owner juga sinkron dengan ID identik
        Sanctum::actingAs($owner);
        $apiRes = $this->getJson('/api/v1/notifikasi', ['X-Properti-Id' => (string) $properti->id])
            ->assertOk();

        $apiIds = collect($apiRes->json('items'))->pluck('id')->all();
        $this->assertContains($laundryNotif['id'], $apiIds);
    }
}
