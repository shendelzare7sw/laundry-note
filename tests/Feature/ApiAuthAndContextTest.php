<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Properti;
use App\Models\User;
use App\Services\PengaturanService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthAndContextTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Properti $propertiA;

    private Properti $propertiB;

    private User $adminA;

    private User $penjagaA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PengaturanSeeder::class);

        $this->owner = User::factory()->create([
            'username' => 'owner_api',
            'password' => 'rahasia123',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);

        $this->propertiA = Properti::query()->create([
            'owner_id' => $this->owner->id,
            'nama' => "D'bata Residence",
            'nama_properti_laporan' => "D'bata Residence",
            'status' => 'aktif',
        ]);

        $this->propertiB = Properti::query()->create([
            'owner_id' => $this->owner->id,
            'nama' => 'Dbata Dua',
            'nama_properti_laporan' => 'Dbata Dua',
            'status' => 'aktif',
        ]);

        $this->adminA = User::factory()->create([
            'username' => 'admin_api',
            'password' => 'rahasia123',
            'role' => Peran::Admin,
            'properti_id' => $this->propertiA->id,
            'aktif' => true,
        ]);

        $this->penjagaA = User::factory()->create([
            'username' => 'penjaga_api',
            'password' => 'rahasia123',
            'role' => Peran::Penjaga,
            'properti_id' => $this->propertiA->id,
            'aktif' => true,
        ]);

        Kamar::query()->create(['properti_id' => $this->propertiA->id, 'nomor' => '101', 'lantai' => 1, 'aktif' => true]);
    }

    public function test_login_me_dan_logout_sanctum_dengan_request_id(): void
    {
        // Password salah
        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin_api',
            'password' => 'salah',
        ])->assertUnprocessable()
            ->assertHeader('X-Request-Id');

        // Akun nonaktif ditolak 403
        $nonaktif = User::factory()->create([
            'username' => 'nonaktif_api',
            'password' => 'rahasia123',
            'role' => Peran::Penjaga,
            'properti_id' => $this->propertiA->id,
            'aktif' => false,
        ]);
        $this->postJson('/api/v1/auth/login', [
            'username' => $nonaktif->username,
            'password' => 'rahasia123',
        ])->assertForbidden()
            ->assertJsonPath('code', 'AKUN_NONAKTIF');

        // Login sukses
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin_api',
            'password' => 'rahasia123',
            'device_name' => 'Pixel 8 Android',
        ])->assertOk()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.username', 'admin_api')
            ->assertJsonPath('properti_aktif.id', $this->propertiA->id)
            ->assertJsonPath('permissions.kelola_pengaturan', true);

        $token = (string) $loginRes->json('token');
        $this->assertNotEmpty($token);

        // /me memakai Bearer token
        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');

        // Logout mencabut token
        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_konteks_multi_properti_beranda_kamar_dan_pengaturan(): void
    {
        $ownerToken = $this->owner->createToken('android')->plainTextToken;

        // Owner punya 2 properti aktif -> tanpa X-Properti-Id mengembalikan 422 PROPERTI_BELUM_DIPILIH
        $this->withToken($ownerToken)
            ->getJson('/api/v1/beranda')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PROPERTI_BELUM_DIPILIH')
            ->assertJsonCount(2, 'properti_list');

        // Dengan X-Properti-Id sukses
        $this->withToken($ownerToken)
            ->withHeader('X-Properti-Id', (string) $this->propertiA->id)
            ->getJson('/api/v1/beranda')
            ->assertOk()
            ->assertJsonPath('properti.id', $this->propertiA->id);

        $this->withToken($ownerToken)
            ->withHeader('X-Properti-Id', (string) $this->propertiA->id)
            ->getJson('/api/v1/kamar?kosong=1')
            ->assertOk()
            ->assertJsonPath('data.0.nomor', '101');

        // Penjaga tidak boleh membaca maupun mengubah pengaturan (sama dengan Web)
        app('auth')->forgetGuards();
        $penjagaToken = $this->penjagaA->createToken('android')->plainTextToken;

        $this->withToken($penjagaToken)
            ->getJson('/api/v1/pengaturan')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->withToken($penjagaToken)
            ->putJson('/api/v1/pengaturan/izin-penjaga', [
                'jam_edit_penjaga' => null,
                'penjaga_ubah_catatan_lain' => true,
            ])
            ->assertForbidden();

        // Admin menyimpan izin penjaga (jam_edit_penjaga kosong = tanpa batas)
        app('auth')->forgetGuards();
        $adminToken = $this->adminA->createToken('android')->plainTextToken;

        $this->withToken($adminToken)
            ->putJson('/api/v1/pengaturan/izin-penjaga', [
                'jam_edit_penjaga' => null,
                'penjaga_kelola_kontrak' => true,
                'penjaga_ubah_catatan_lain' => true,
            ])
            ->assertOk();

        $svc = app(PengaturanService::class);
        $this->assertTrue($svc->penjagaBolehKelolaKontrak($this->propertiA));
        $this->assertTrue($svc->penjagaBolehUbahCatatanLain($this->propertiA));

        $this->withToken($adminToken)
            ->getJson('/api/v1/pengaturan')
            ->assertOk()
            ->assertJsonPath('izin_penjaga.jam_edit_penjaga', null)
            ->assertJsonPath('izin_penjaga.penjaga_ubah_catatan_lain', true);
    }

    public function test_keamanan_akun_nonaktif_pencabutan_token_dan_perlindungan_dev_admin(): void
    {
        $devAdmin = User::factory()->create([
            'username' => 'dev_utama',
            'password' => 'rahasia123',
            'role' => Peran::DevAdmin,
            'aktif' => true,
        ]);

        // 1. Penjaga, Admin, dan Owner yang dinonaktifkan tidak bisa login sama sekali (403 AKUN_NONAKTIF)
        $penjagaToken = $this->penjagaA->createToken('hp-penjaga')->plainTextToken;
        $ownerToken = $this->owner->createToken('hp-owner')->plainTextToken;

        // Admin menonaktifkan Penjaga via API -> token Sanctum Penjaga langsung terhapus
        $adminToken = $this->adminA->createToken('hp-admin')->plainTextToken;
        $this->withToken($adminToken)
            ->patchJson("/api/v1/pengguna/{$this->penjagaA->id}/status")
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $this->penjagaA->id]);

        // Penjaga yang sudah nonaktif mencoba login lagi -> ditolak 403 AKUN_NONAKTIF tanpa token baru
        app('auth')->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'username' => 'PENJAGA_API', // juga menguji case-insensitive username
            'password' => 'rahasia123',
        ])->assertForbidden()
            ->assertJsonPath('code', 'AKUN_NONAKTIF')
            ->assertJsonMissingPath('token');

        // Request dengan token lama Penjaga -> ditolak 401 UNAUTHORIZED berbahasa Indonesia
        app('auth')->forgetGuards();
        $this->withToken($penjagaToken)
            ->getJson('/api/v1/beranda')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHORIZED');

        // 2. Jika akun Owner dinonaktifkan langsung (mis. token masih ada di DB), middleware akun.aktif.api
        // langsung memblokir semua endpoint (/properti, /pengguna, /me) dengan 403 AKUN_NONAKTIF & hapus token
        $this->owner->update(['aktif' => false]);
        app('auth')->forgetGuards();
        $this->withToken($ownerToken)
            ->getJson('/api/v1/properti')
            ->assertForbidden()
            ->assertJsonPath('code', 'AKUN_NONAKTIF');
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $this->owner->id]);

        // Owner nonaktif tidak bisa login lagi
        app('auth')->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'username' => 'owner_api',
            'password' => 'rahasia123',
        ])->assertForbidden()
            ->assertJsonPath('code', 'AKUN_NONAKTIF');

        // 3. Jika properti diubah ke draf/arsip oleh DevAdmin, token staf properti dicabut & staf tidak bisa login,
        // namun DevAdmin tetap bisa login dan mengakses properti
        $this->owner->update(['aktif' => true]);
        app('auth')->forgetGuards();
        $devToken = $devAdmin->createToken('hp-dev')->plainTextToken;

        $this->withToken($devToken)
            ->putJson("/api/v1/properti/{$this->propertiA->id}", [
                'nama' => $this->propertiA->nama,
                'nama_properti_laporan' => $this->propertiA->nama_properti_laporan,
                'status' => 'arsip',
            ])
            ->assertOk();

        // Token admin propertiA harus sudah tercabut
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $this->adminA->id]);

        // Admin propertiA tidak bisa login selama properti arsip
        app('auth')->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin_api',
            'password' => 'rahasia123',
        ])->assertForbidden()
            ->assertJsonPath('code', 'PROPERTI_TIDAK_AKTIF');

        // DevAdmin tetap bisa login kapan pun
        app('auth')->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'username' => 'dev_utama',
            'password' => 'rahasia123',
        ])->assertOk()
            ->assertJsonPath('user.role', 'dev_admin');
    }
}
