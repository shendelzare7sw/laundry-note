<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PeranDanPropertiTest extends TestCase
{
    use RefreshDatabase;

    private function buatOwnerDanDuaProperti(): array
    {
        $owner = User::factory()->create([
            'username' => 'owner_utama',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);

        $propertiA = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Residence',
            'nama_properti_laporan' => 'Dbata Residence',
            'status' => 'aktif',
        ]);

        $propertiB = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Residence 2',
            'nama_properti_laporan' => 'Dbata Residence 2',
            'status' => 'aktif',
        ]);

        return [$owner, $propertiA, $propertiB];
    }

    public function test_karyawan_langsung_melihat_propertinya_dan_ditolak_mengakses_properti_lain(): void
    {
        [, $propertiA, $propertiB] = $this->buatOwnerDanDuaProperti();

        $penjaga = User::factory()->create([
            'username' => 'penjaga_a',
            'role' => Peran::Penjaga,
            'properti_id' => $propertiA->id,
            'aktif' => true,
        ]);

        $this->actingAs($penjaga)
            ->get('/')
            ->assertOk()
            ->assertSee('Dbata Residence');

        $this->actingAs($penjaga)
            ->get(route('properti.tampilkan', $propertiA))
            ->assertOk()
            ->assertSee('Dbata Residence');

        $this->actingAs($penjaga)
            ->get(route('properti.tampilkan', $propertiB))
            ->assertForbidden();
    }

    public function test_matriks_hak_akses_pengelolaan_pengguna_dan_properti(): void
    {
        [$owner, $propertiA, $propertiB] = $this->buatOwnerDanDuaProperti();

        $devAdmin = User::factory()->create([
            'username' => 'devadmin_uji',
            'role' => Peran::DevAdmin,
            'aktif' => true,
        ]);

        $adminA = User::factory()->create([
            'username' => 'admin_a',
            'role' => Peran::Admin,
            'properti_id' => $propertiA->id,
            'aktif' => true,
        ]);

        $penjagaA = User::factory()->create([
            'username' => 'penjaga_a',
            'role' => Peran::Penjaga,
            'properti_id' => $propertiA->id,
            'aktif' => true,
        ]);

        $penjagaB = User::factory()->create([
            'username' => 'penjaga_b',
            'role' => Peran::Penjaga,
            'properti_id' => $propertiB->id,
            'aktif' => true,
        ]);

        // Penjaga tidak dapat mengakses manajemen pengguna atau properti
        $this->actingAs($penjagaA)->get('/pengguna')->assertForbidden();
        $this->actingAs($penjagaA)->get('/properti')->assertForbidden();

        // Admin hanya dapat membuat penjaga di propertinya sendiri
        $this->actingAs($adminA)->get('/pengguna')->assertOk();
        $this->actingAs($adminA)->post('/pengguna', [
            'name' => 'Penjaga Baru A',
            'username' => 'penjaga_baru_a',
            'password' => 'password123',
            'role' => Peran::Penjaga->value,
        ])->assertRedirect(route('pengguna.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'penjaga_baru_a',
            'properti_id' => $propertiA->id,
            'role' => Peran::Penjaga->value,
        ]);

        // Admin ditolak membuat admin/owner atau mengelola staf properti lain
        $this->actingAs($adminA)->post('/pengguna', [
            'name' => 'Admin Ilegal',
            'username' => 'admin_ilegal',
            'password' => 'password123',
            'role' => Peran::Admin->value,
            'properti_id' => $propertiA->id,
        ])->assertForbidden();

        $this->actingAs($adminA)
            ->patch(route('pengguna.toggle-aktif', $penjagaB))
            ->assertForbidden();

        // Owner dapat membuat properti baru dan membuat admin + penjaga di propertinya
        $this->actingAs($owner)->post('/properti', [
            'nama' => 'Dbata Residence 3',
            'nama_properti_laporan' => 'Dbata Residence 3',
            'alamat' => 'Jl. Baru No. 3',
            'status' => 'aktif',
        ])->assertRedirect(route('properti.index'));

        $this->assertDatabaseHas('properti', ['nama' => 'Dbata Residence 3']);

        $this->actingAs($owner)->post('/pengguna', [
            'name' => 'Admin B',
            'username' => 'admin_b',
            'password' => 'password123',
            'role' => Peran::Admin->value,
            'properti_id' => $propertiB->id,
        ])->assertRedirect(route('pengguna.index'));

        // DevAdmin dapat mengakses semua halaman
        $this->actingAs($devAdmin)->get('/')->assertOk();
        $this->actingAs($devAdmin)->get('/properti')->assertOk();
        $this->actingAs($devAdmin)->get('/pengguna')->assertOk();
    }

    public function test_indeks_unik_menolak_admin_aktif_kedua_di_properti_yang_sama(): void
    {
        [, $propertiA] = $this->buatOwnerDanDuaProperti();

        $adminPertama = User::factory()->create([
            'username' => 'admin_satu',
            'role' => Peran::Admin,
            'properti_id' => $propertiA->id,
            'aktif' => true,
        ]);

        $gagal = false;

        try {
            User::factory()->create([
                'username' => 'admin_dua',
                'role' => Peran::Admin,
                'properti_id' => $propertiA->id,
                'aktif' => true,
            ]);
        } catch (QueryException) {
            $gagal = true;
        }

        $this->assertTrue($gagal, 'Database harus menolak admin aktif kedua pada properti yang sama.');

        // Setelah admin pertama dinonaktifkan, admin baru boleh ditambahkan
        $adminPertama->update(['aktif' => false]);

        $adminPengganti = User::factory()->create([
            'username' => 'admin_pengganti',
            'role' => Peran::Admin,
            'properti_id' => $propertiA->id,
            'aktif' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $adminPengganti->id,
            'aktif' => true,
            'admin_properti' => $propertiA->id,
        ]);
    }

    public function test_owner_mendapat_error_validasi_saat_menambah_admin_aktif_kedua_via_form(): void
    {
        [$owner, $propertiA] = $this->buatOwnerDanDuaProperti();

        User::factory()->create([
            'username' => 'admin_existing',
            'role' => Peran::Admin,
            'properti_id' => $propertiA->id,
            'aktif' => true,
        ]);

        $response = $this->actingAs($owner)->from('/pengguna')->post('/pengguna', [
            'name' => 'Admin Kedua',
            'username' => 'admin_kedua',
            'password' => 'password123',
            'role' => Peran::Admin->value,
            'properti_id' => $propertiA->id,
        ]);

        $response->assertRedirect('/pengguna');
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'admin_kedua']);
    }

    public function test_nonaktifkan_akun_dan_reset_password_serta_pilih_properti(): void
    {
        [$owner, $propertiA, $propertiB] = $this->buatOwnerDanDuaProperti();

        $penjaga = User::factory()->create([
            'username' => 'penjaga_aktif',
            'role' => Peran::Penjaga,
            'properti_id' => $propertiA->id,
            'aktif' => true,
        ]);

        $this->actingAs($owner)
            ->patch(route('pengguna.toggle-aktif', $penjaga))
            ->assertRedirect(route('pengguna.index'));

        $this->assertFalse($penjaga->fresh()->aktif);

        $this->actingAs($penjaga->fresh())
            ->get('/')
            ->assertRedirect('/login');

        $this->actingAs($owner)
            ->patch(route('pengguna.reset-password', $penjaga), [
                'password' => 'passwordbaru123',
                'password_confirmation' => 'passwordbaru123',
            ])
            ->assertRedirect(route('pengguna.index'));

        $this->assertTrue(Hash::check('passwordbaru123', $penjaga->fresh()->password));

        $this->actingAs($owner)
            ->post(route('properti.pilih', $propertiB))
            ->assertSessionHas('properti_aktif_id', $propertiB->id);
    }
}
