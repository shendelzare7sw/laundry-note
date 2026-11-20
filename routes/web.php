<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PenghuniController;
use App\Http\Controllers\PropertiController;
use App\Http\Controllers\PropertiWizardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'properti.aktif'])->group(function (): void {
    Route::get('/', [BerandaController::class, 'index'])->name('beranda');

    Route::view('/laundry', 'laundry.index')->name('laundry.index');

    Route::get('/penghuni', [PenghuniController::class, 'index'])->name('penghuni.index');
    Route::post('/penghuni', [PenghuniController::class, 'store'])->name('penghuni.store');
    Route::get('/penghuni/{penghuni}', [PenghuniController::class, 'show'])->name('penghuni.show');
    Route::put('/penghuni/{penghuni}', [PenghuniController::class, 'update'])->name('penghuni.update');
    Route::post('/penghuni/{penghuni}/pindah-kamar', [PenghuniController::class, 'pindahKamar'])->name('penghuni.pindah-kamar');
    Route::post('/penghuni/{penghuni}/batalkan-pindah', [PenghuniController::class, 'batalkanPindah'])->name('penghuni.batalkan-pindah');
    Route::post('/penghuni/{penghuni}/keluar', [PenghuniController::class, 'keluar'])->name('penghuni.keluar');
    Route::post('/penghuni/{penghuni}/batalkan-keluar', [PenghuniController::class, 'batalkanKeluar'])->name('penghuni.batalkan-keluar');
    Route::post('/penghuni/{penghuni}/ganti-nomor', [PenghuniController::class, 'gantiNomor'])->name('penghuni.ganti-nomor');
    Route::post('/penghuni/{penghuni}/penyesuaian-kuota', [PenghuniController::class, 'simpanPenyesuaianKuota'])->name('penghuni.penyesuaian-kuota');

    Route::view('/laporan', 'laporan.index')->name('laporan.index');
    Route::view('/lainnya', 'lainnya.index')->name('lainnya.index');

    Route::get('/properti', [PropertiController::class, 'index'])->name('properti.index');
    Route::post('/properti', [PropertiController::class, 'store'])->name('properti.store');
    Route::post('/properti/lepas', [PropertiController::class, 'lepas'])->name('properti.lepas');
    Route::get('/properti/{properti}', [PropertiController::class, 'tampilkan'])->name('properti.tampilkan');
    Route::get('/properti/{properti}/edit', [PropertiController::class, 'edit'])->name('properti.edit');
    Route::put('/properti/{properti}', [PropertiController::class, 'update'])->name('properti.update');
    Route::post('/properti/pilih/{properti}', [PropertiController::class, 'pilih'])->name('properti.pilih');

    Route::get('/properti/{properti}/setup', [PropertiWizardController::class, 'tampilkan'])->name('properti.setup.tampilkan');
    Route::post('/properti/{properti}/setup/kamar', [PropertiWizardController::class, 'simpanKamar'])->name('properti.setup.kamar');
    Route::post('/properti/{properti}/setup/aturan', [PropertiWizardController::class, 'simpanAturan'])->name('properti.setup.aturan');
    Route::post('/properti/{properti}/setup/aktifkan', [PropertiWizardController::class, 'aktifkan'])->name('properti.setup.aktifkan');

    Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
    Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
    Route::patch('/pengguna/{pengguna}/status', [PenggunaController::class, 'toggleAktif'])->name('pengguna.toggle-aktif');
    Route::patch('/pengguna/{pengguna}/reset-password', [PenggunaController::class, 'resetPassword'])->name('pengguna.reset-password');
});
