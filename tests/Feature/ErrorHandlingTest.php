<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_419_saat_guest_otomatis_redirect_ke_login(): void
    {
        Route::post('/_uji-419-guest', function (): void {
            throw new HttpException(419, 'Page Expired');
        })->middleware('web');

        $response = $this->post('/_uji-419-guest', [
            'username' => 'owner',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
    }

    public function test_419_saat_sudah_login_otomatis_redirect_dengan_toast_peringatan(): void
    {
        $owner = User::factory()->create(['role' => Peran::Owner, 'aktif' => true]);
        $properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Uji',
            'nama_properti_laporan' => 'Dbata Uji',
            'status' => 'aktif',
        ]);
        $admin = User::factory()->create([
            'role' => Peran::Admin,
            'properti_id' => $properti->id,
            'aktif' => true,
        ]);

        Route::post('/_uji-419-auth', function (): void {
            throw new HttpException(419, 'Page Expired');
        })->middleware('web');

        $response = $this->actingAs($admin)
            ->from(route('laundry.create'))
            ->post('/_uji-419-auth', [
                'catatan' => 'Cucian penting',
            ]);

        $response->assertRedirect(route('laundry.create'))
            ->assertSessionHas('toast.tipe', 'warn')
            ->assertSessionHasInput('catatan', 'Cucian penting');
    }

    public function test_halaman_404_dan_403_berbahasa_indonesia_tanpa_inline_script(): void
    {
        $res404 = $this->get('/halaman-tidak-pernah-ada-xyz');
        $res404->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan')
            ->assertSee('Masuk ke Aplikasi');

        $html404 = $res404->getContent();
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $html404);
        $this->assertDoesNotMatchRegularExpression('/<style\b[^>]*>/i', $html404);

        $owner = User::factory()->create(['role' => Peran::Owner, 'aktif' => true]);
        $properti = Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => 'Dbata Uji',
            'nama_properti_laporan' => 'Dbata Uji',
            'status' => 'aktif',
        ]);
        $penjaga = User::factory()->create([
            'role' => Peran::Penjaga,
            'properti_id' => $properti->id,
            'aktif' => true,
        ]);

        $res403 = $this->actingAs($penjaga)->get(route('pengaturan.index'));
        $res403->assertForbidden()
            ->assertSee('Akses Ditolak')
            ->assertSee('Kembali ke Beranda');
    }
}
