<?php

namespace App\Support;

use App\Enums\Peran;
use App\Models\User;

class PesanAksesAkun
{
    public static function akunNonaktif(User $user): string
    {
        return match ($user->role) {
            Peran::Penjaga => 'Akun Penjaga Anda sedang dinonaktifkan oleh Admin/Owner. Anda tidak dapat masuk sampai akun diaktifkan kembali.',
            Peran::Admin => 'Akun Admin Properti Anda sedang dinonaktifkan oleh Owner. Anda tidak dapat masuk sampai akun diaktifkan kembali.',
            Peran::Owner => 'Akun Owner Anda sedang dinonaktifkan oleh Dev Admin. Silakan hubungi pengelola sistem.',
            Peran::DevAdmin => 'Akun Anda sedang dinonaktifkan. Silakan hubungi Dev Admin lain.',
        };
    }

    public static function propertiTidakTerikat(): string
    {
        return 'Akun Anda belum terhubung ke properti mana pun. Silakan hubungi Owner.';
    }

    public static function propertiTidakAktif(): string
    {
        return 'Properti penempatan Anda sedang tidak aktif (draf/arsip). Anda tidak dapat masuk sampai properti diaktifkan kembali oleh Owner.';
    }
}
