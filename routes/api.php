<?php

use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\BerandaApiController;
use App\Http\Controllers\Api\V1\DeviceTokenApiController;
use App\Http\Controllers\Api\V1\LaporanApiController;
use App\Http\Controllers\Api\V1\LaundryApiController;
use App\Http\Controllers\Api\V1\NotifikasiApiController;
use App\Http\Controllers\Api\V1\PengaturanApiController;
use App\Http\Controllers\Api\V1\PenggunaApiController;
use App\Http\Controllers\Api\V1\PenghuniApiController;
use App\Http\Controllers\Api\V1\PropertiApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [AuthApiController::class, 'login'])
        ->middleware('throttle:15,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'akun.aktif.api'])->group(function (): void {
        Route::post('/auth/logout', [AuthApiController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthApiController::class, 'me'])->name('me');
        Route::post('/device-token', [DeviceTokenApiController::class, 'store'])->name('device-token.store');
        Route::delete('/device-token', [DeviceTokenApiController::class, 'destroy'])->name('device-token.destroy');
        Route::get('/properti', [PropertiApiController::class, 'index'])->name('properti.index');
        Route::post('/properti', [PropertiApiController::class, 'store'])->name('properti.store');
        Route::put('/properti/{properti}', [PropertiApiController::class, 'update'])->name('properti.update');
        Route::get('/properti/{properti}/setup', [PropertiApiController::class, 'setupShow'])->name('properti.setup.show');
        Route::post('/properti/{properti}/setup/kamar', [PropertiApiController::class, 'setupKamar'])->name('properti.setup.kamar');
        Route::post('/properti/{properti}/setup/aturan', [PropertiApiController::class, 'setupAturan'])->name('properti.setup.aturan');
        Route::post('/properti/{properti}/setup/aktifkan', [PropertiApiController::class, 'setupAktifkan'])->name('properti.setup.aktifkan');

        Route::get('/pengguna', [PenggunaApiController::class, 'index'])->name('pengguna.index');
        Route::post('/pengguna', [PenggunaApiController::class, 'store'])->name('pengguna.store');
        Route::patch('/pengguna/{pengguna}/status', [PenggunaApiController::class, 'toggleAktif'])->name('pengguna.toggle-aktif');
        Route::patch('/pengguna/{pengguna}/reset-password', [PenggunaApiController::class, 'resetPassword'])->name('pengguna.reset-password');

        Route::middleware('properti.api')->group(function (): void {
            Route::get('/beranda', [BerandaApiController::class, 'index'])->name('beranda');
            Route::get('/notifikasi', [NotifikasiApiController::class, 'index'])->name('notifikasi.index');
            Route::get('/kamar', [PropertiApiController::class, 'kamar'])->name('kamar.index');

            Route::get('/pengaturan', [PengaturanApiController::class, 'show'])->name('pengaturan.show');
            Route::put('/pengaturan/aturan', [PengaturanApiController::class, 'simpanAturan'])->name('pengaturan.aturan');
            Route::put('/pengaturan/izin-penjaga', [PengaturanApiController::class, 'simpanIzinPenjaga'])->name('pengaturan.izin-penjaga');
            Route::put('/pengaturan/template-wa', [PengaturanApiController::class, 'simpanTemplateWa'])->name('pengaturan.template-wa');
            Route::post('/pengaturan/template-wa/reset', [PengaturanApiController::class, 'resetTemplateWa'])->name('pengaturan.template-wa.reset');

            Route::get('/penghuni', [PenghuniApiController::class, 'index'])->name('penghuni.index');
            Route::post('/penghuni', [PenghuniApiController::class, 'store'])->name('penghuni.store');
            Route::get('/penghuni/{penghuni}', [PenghuniApiController::class, 'show'])->name('penghuni.show');
            Route::put('/penghuni/{penghuni}', [PenghuniApiController::class, 'update'])->name('penghuni.update');
            Route::post('/penghuni/{penghuni}/pindah-kamar', [PenghuniApiController::class, 'pindahKamar'])->name('penghuni.pindah-kamar');
            Route::post('/penghuni/{penghuni}/batalkan-pindah', [PenghuniApiController::class, 'batalkanPindah'])->name('penghuni.batalkan-pindah');
            Route::post('/penghuni/{penghuni}/keluar', [PenghuniApiController::class, 'keluar'])->name('penghuni.keluar');
            Route::post('/penghuni/{penghuni}/batalkan-keluar', [PenghuniApiController::class, 'batalkanKeluar'])->name('penghuni.batalkan-keluar');
            Route::post('/penghuni/{penghuni}/ganti-nomor', [PenghuniApiController::class, 'gantiNomor'])->name('penghuni.ganti-nomor');
            Route::post('/penghuni/{penghuni}/penyesuaian-kuota', [PenghuniApiController::class, 'simpanPenyesuaianKuota'])->name('penghuni.penyesuaian-kuota');

            Route::get('/laundry', [LaundryApiController::class, 'index'])->name('laundry.index');
            Route::get('/laundry/info-kuota', [LaundryApiController::class, 'infoKuota'])->name('laundry.info-kuota');
            Route::get('/laundry/terhapus', [LaundryApiController::class, 'terhapus'])->name('laundry.terhapus');
            Route::post('/laundry/lengkapi-harga', [LaundryApiController::class, 'lengkapiHarga'])->name('laundry.lengkapi-harga');
            Route::post('/laundry', [LaundryApiController::class, 'store'])->name('laundry.store');
            Route::get('/laundry/{laundry}', [LaundryApiController::class, 'show'])->name('laundry.show');
            Route::put('/laundry/{laundry}', [LaundryApiController::class, 'update'])->name('laundry.update');
            Route::delete('/laundry/{laundry}', [LaundryApiController::class, 'destroy'])->name('laundry.destroy');
            Route::post('/laundry/{laundryId}/pulihkan', [LaundryApiController::class, 'pulihkan'])
                ->whereNumber('laundryId')
                ->name('laundry.pulihkan');
            Route::post('/laundry/{laundry}/tetapkan-pemilik', [LaundryApiController::class, 'tetapkanPemilik'])->name('laundry.tetapkan-pemilik');

            Route::get('/laporan', [LaporanApiController::class, 'index'])->name('laporan.index');
            Route::get('/wa/tagihan/{penghuni}', [LaporanApiController::class, 'waTagihan'])->name('wa.tagihan');
            Route::get('/wa/peringatan/{penghuni}', [LaporanApiController::class, 'waPeringatan'])->name('wa.peringatan');
        });
    });
});
