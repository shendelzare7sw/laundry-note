<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\AuditLog;
use App\Models\Kamar;
use App\Models\Pengaturan;
use App\Models\Properti;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WizardSetupPropertiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dapat_menjalankan_wizard_setup_properti_sampai_aktif(): void
    {
        $this->seed(PengaturanSeeder::class);

        $owner = User::factory()->create([
            'username' => 'owner_wizard',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);

        // 1. Owner buat properti baru berstatus draf -> diarahkan ke wizard setup
        $response = $this->actingAs($owner)->post(route('properti.store'), [
            'nama' => 'Dbata Residence 2',
            'nama_properti_laporan' => 'Dbata Residence 2',
            'alamat' => 'Jl. Melati No. 2',
            'status' => 'draf',
        ]);

        $properti = Properti::query()->where('nama', 'Dbata Residence 2')->firstOrFail();
        $response->assertRedirect(route('properti.setup.tampilkan', $properti));

        // Aturan bawaan global otomatis disalin
        $this->assertDatabaseHas('pengaturan', [
            'properti_id' => $properti->id,
            'kunci' => 'tarif_per_kg',
            'nilai' => '8000',
        ]);

        // 2. Aktivasi ditolak sebelum ada kamar dan admin
        $this->actingAs($owner)
            ->post(route('properti.setup.aktifkan', $properti))
            ->assertRedirect(route('properti.setup.tampilkan', $properti))
            ->assertSessionHasErrors('aktivasi');

        $this->assertSame('draf', $properti->fresh()->status);

        // 3. Generate kamar per lantai (idempoten)
        $payloadKamar = [
            'lantai' => [
                ['lantai' => 1, 'dari' => 101, 'sampai' => 105],
                ['lantai' => 2, 'dari' => 201, 'sampai' => 203],
            ],
        ];

        $this->actingAs($owner)
            ->post(route('properti.setup.kamar', $properti), $payloadKamar)
            ->assertRedirect(route('properti.setup.tampilkan', $properti));

        $this->actingAs($owner)
            ->post(route('properti.setup.kamar', $properti), $payloadKamar)
            ->assertRedirect(route('properti.setup.tampilkan', $properti));

        $this->assertSame(8, Kamar::query()->untukProperti($properti)->count());

        // 4. Simpan aturan khusus properti
        $this->actingAs($owner)
            ->post(route('properti.setup.aturan', $properti), [
                'tarif_per_kg' => 9000,
                'min_kg' => 2,
                'kuota_kg' => 25,
                'ambang_mendekati_persen' => 80,
                'toleransi_kg' => 1,
                'pembulatan' => 1000,
                'mode_kuota' => 'penghuni',
                'jam_edit_penjaga' => 12,
                'penjaga_kelola_kontrak' => 1,
            ])
            ->assertRedirect(route('properti.setup.tampilkan', $properti));

        $this->assertSame(
            '9000',
            Pengaturan::query()
                ->untukProperti($properti)
                ->where('kunci', 'tarif_per_kg')
                ->latest('id')
                ->value('nilai')
        );

        // 5. Tambahkan admin properti lewat form wizard
        $this->actingAs($owner)
            ->post(route('pengguna.store'), [
                'name' => 'Admin Dbata 2',
                'username' => 'admin_dbata2',
                'password' => 'password123',
                'role' => Peran::Admin->value,
                'properti_id' => $properti->id,
                'setup_properti_id' => $properti->id,
            ])
            ->assertRedirect(route('properti.setup.tampilkan', $properti));

        // 6. Aktivasi sekarang berhasil
        $this->actingAs($owner)
            ->post(route('properti.setup.aktifkan', $properti))
            ->assertRedirect(route('properti.setup.tampilkan', $properti))
            ->assertSessionHasNoErrors();

        $this->assertSame('aktif', $properti->fresh()->status);
        $this->assertTrue(
            AuditLog::query()
                ->untukProperti($properti)
                ->where('aksi', 'wizard_aktifkan_properti')
                ->exists()
        );
    }

    public function test_admin_dan_penjaga_ditolak_mengakses_wizard_setup_properti(): void
    {
        $owner = User::factory()->create(['username' => 'owner_w2', 'role' => Peran::Owner]);
        $properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Properti Uji',
            'nama_properti_laporan' => 'Properti Uji',
            'status' => 'aktif',
        ]);

        $admin = User::factory()->create([
            'username' => 'admin_w2',
            'role' => Peran::Admin,
            'properti_id' => $properti->id,
        ]);

        $penjaga = User::factory()->create([
            'username' => 'penjaga_w2',
            'role' => Peran::Penjaga,
            'properti_id' => $properti->id,
        ]);

        $this->actingAs($admin)->get(route('properti.setup.tampilkan', $properti))->assertForbidden();
        $this->actingAs($penjaga)->get(route('properti.setup.tampilkan', $properti))->assertForbidden();
    }

    public function test_owner_dapat_menyimpan_semua_setup_sekaligus_termasuk_aturan_dan_staf_baru(): void
    {
        $this->seed(PengaturanSeeder::class);

        $owner = User::factory()->create([
            'username' => 'owner_all',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);

        $properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Setup Terpadu',
            'nama_properti_laporan' => 'Dbata Setup Terpadu',
            'status' => 'draf',
        ]);

        Kamar::query()->create([
            'properti_id' => $properti->id,
            'nomor' => '101',
            'lantai' => 1,
            'aktif' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('properti.setup.simpan-semua', $properti), [
                'nama' => 'Dbata Residence Utama',
                'nama_properti_laporan' => 'Dbata Residence Utama',
                'alamat' => 'Jl. H. Dero No. 48',
                'tarif_per_kg' => 8500,
                'min_kg' => 2,
                'kuota_kg' => 20,
                'ambang_mendekati_persen' => 75,
                'toleransi_kg' => 1,
                'pembulatan' => 1000,
                'mode_kuota' => 'penghuni',
                'jam_edit_penjaga' => 24,
                'penjaga_kelola_kontrak' => 1,
                'name' => 'Admin Utama',
                'username' => 'admin_utama',
                'password' => 'password123',
                'role' => Peran::Admin->value,
                'aktifkan_sekarang' => 1,
            ])
            ->assertRedirect(route('properti.setup.tampilkan', $properti))
            ->assertSessionHasNoErrors();

        $properti->refresh();
        $this->assertSame('Dbata Residence Utama', $properti->nama);
        $this->assertSame('aktif', $properti->status);

        $this->assertDatabaseHas('pengaturan', [
            'properti_id' => $properti->id,
            'kunci' => 'penjaga_kelola_kontrak',
            'nilai' => '1',
        ]);

        $this->assertDatabaseHas('users', [
            'username' => 'admin_utama',
            'properti_id' => $properti->id,
            'role' => Peran::Admin->value,
            'aktif' => true,
        ]);
    }

    public function test_pesan_validasi_password_staf_kosong_pada_setup_tampil_bahasa_indonesia(): void
    {
        $this->seed(PengaturanSeeder::class);

        $owner = User::factory()->create([
            'username' => 'owner_val',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);

        $properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Validasi',
            'nama_properti_laporan' => 'Dbata Validasi',
            'status' => 'draf',
        ]);

        $response = $this->from(route('properti.setup.tampilkan', $properti))
            ->actingAs($owner)
            ->post(route('properti.setup.simpan-semua', $properti), [
                'nama' => 'Dbata Validasi',
                'nama_properti_laporan' => 'Dbata Validasi',
                'tarif_per_kg' => 8000,
                'min_kg' => 2,
                'kuota_kg' => 20,
                'ambang_mendekati_persen' => 75,
                'toleransi_kg' => 1,
                'pembulatan' => 1000,
                'mode_kuota' => 'penghuni',
                'jam_edit_penjaga' => 24,
                'name' => 'Admin Staf',
                'username' => 'admin_dbata',
                'password' => '',
                'role' => Peran::Admin->value,
            ]);

        $response->assertRedirect(route('properti.setup.tampilkan', $properti));
        $response->assertSessionHasErrors([
            'password' => 'Password awal belum diisi (wajib dilengkapi jika nama atau username karyawan diisi).',
        ]);
        $this->assertStringNotContainsString(
            'validation.required_with',
            implode(' ', session('errors')->all())
        );
    }
}
