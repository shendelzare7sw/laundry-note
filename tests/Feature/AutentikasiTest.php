<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_dapat_login_dengan_username_dan_diarahkan_ke_beranda(): void
    {
        $owner = User::factory()->create([
            'username' => 'owneruji',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);

        Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Residence',
            'nama_properti_laporan' => 'Dbata Residence',
            'status' => 'aktif',
        ]);

        $response = $this->post('/login', [
            'username' => 'owneruji',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($owner);
        $response->assertRedirect(route('beranda', absolute: false));
    }

    public function test_login_ditolak_jika_password_salah(): void
    {
        User::factory()->create([
            'username' => 'stafuji',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => 'stafuji',
            'password' => 'salah-banget',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
    }

    public function test_akun_nonaktif_ditolak_saat_login(): void
    {
        User::factory()->nonaktif()->create([
            'username' => 'stafnonaktif',
            'role' => Peran::Owner,
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => 'stafnonaktif',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
    }

    public function test_rate_limiter_aktif_setelah_lima_kali_gagal_login(): void
    {
        User::factory()->create([
            'username' => 'ratelimituser',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'ratelimituser',
                'password' => 'salah',
            ]);
        }

        $response = $this->post('/login', [
            'username' => 'ratelimituser',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
        $errors = session('errors')->get('username');
        $this->assertStringContainsString('Terlalu banyak percobaan login', $errors[0]);
    }

    public function test_rute_register_dimatikan(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }
}
