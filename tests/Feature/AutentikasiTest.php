<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Properti;
use App\Models\User;
use App\Support\PesanAksesAkun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_diarahkan_ke_halaman_pilih_properti_saat_login(): void
    {
        $owner = User::factory()->create([
            'username' => 'owneruji',
            'role' => Peran::Owner,
            'aktif' => true,
        ]);

        $properti = Properti::query()->create([
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
        $response->assertRedirect(route('properti.index', absolute: false));

        // Mengakses beranda sebelum memilih properti harus diarahkan ke /properti
        $this->actingAs($owner)
            ->get(route('beranda'))
            ->assertRedirect(route('properti.index'));

        // Setelah memilih properti, owner masuk ke beranda properti tersebut
        $this->actingAs($owner)
            ->post(route('properti.pilih', $properti))
            ->assertRedirect(route('beranda'));

        $this->actingAs($owner)
            ->withSession(['properti_aktif_id' => $properti->id])
            ->get(route('beranda'))
            ->assertOk()
            ->assertSee('Dbata Residence');
    }

    public function test_staf_properti_langsung_diarahkan_ke_beranda_saat_login(): void
    {
        $owner = User::factory()->create(['username' => 'owner_staf', 'role' => Peran::Owner]);
        $properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Residence',
            'nama_properti_laporan' => 'Dbata Residence',
            'status' => 'aktif',
        ]);

        $penjaga = User::factory()->create([
            'username' => 'penjaga_uji',
            'role' => Peran::Penjaga,
            'properti_id' => $properti->id,
            'aktif' => true,
        ]);

        $this->post('/login', [
            'username' => 'penjaga_uji',
            'password' => 'password',
        ])->assertRedirect(route('beranda', absolute: false));
    }

    public function test_staf_ditolak_jika_properti_penempatannya_nonaktif(): void
    {
        $owner = User::factory()->create(['username' => 'owner_arsip', 'role' => Peran::Owner]);
        $properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Properti Nonaktif',
            'nama_properti_laporan' => 'Properti Nonaktif',
            'status' => 'arsip',
        ]);

        $penjaga = User::factory()->create([
            'username' => 'penjaga_arsip',
            'role' => Peran::Penjaga,
            'properti_id' => $properti->id,
            'aktif' => true,
        ]);

        // Sesi staf yang propertinya dinonaktifkan langsung dipaksa logout & diarahkan ke /login
        $this->actingAs($penjaga)
            ->get('/')
            ->assertRedirect(route('login'))
            ->assertSessionHas('auth_blocked', PesanAksesAkun::propertiTidakAktif());
        $this->assertGuest();

        $this->app['auth']->forgetGuards();

        // Uji juga penolakan langsung pada form login untuk status arsip maupun draf
        $this->from('/login')
            ->post('/login', [
                'username' => 'penjaga_arsip',
                'password' => 'password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => PesanAksesAkun::propertiTidakAktif(),
            ]);
        $this->assertGuest();

        $properti->update(['status' => 'draf']);

        $this->from('/login')
            ->post('/login', [
                'username' => 'penjaga_arsip',
                'password' => 'password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => PesanAksesAkun::propertiTidakAktif(),
            ]);
        $this->assertGuest();
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
