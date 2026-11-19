<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PropertiController;
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
    Route::view('/penghuni', 'penghuni.index')->name('penghuni.index');
    Route::view('/laporan', 'laporan.index')->name('laporan.index');
    Route::view('/lainnya', 'lainnya.index')->name('lainnya.index');

    Route::get('/properti', [PropertiController::class, 'index'])->name('properti.index');
    Route::post('/properti', [PropertiController::class, 'store'])->name('properti.store');
    Route::get('/properti/{properti}', [PropertiController::class, 'tampilkan'])->name('properti.tampilkan');
    Route::put('/properti/{properti}', [PropertiController::class, 'update'])->name('properti.update');
    Route::post('/properti/pilih/{properti}', [PropertiController::class, 'pilih'])->name('properti.pilih');

    Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
    Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
    Route::patch('/pengguna/{pengguna}/status', [PenggunaController::class, 'toggleAktif'])->name('pengguna.toggle-aktif');
    Route::patch('/pengguna/{pengguna}/reset-password', [PenggunaController::class, 'resetPassword'])->name('pengguna.reset-password');
});
