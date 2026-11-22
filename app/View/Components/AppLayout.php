<?php

namespace App\View\Components;

use App\Models\Properti;
use App\Services\NotifikasiService;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(
        public string $judul = 'Laundry-Kos',
        public ?string $subjudul = null,
    ) {}

    public function render(): View
    {
        $user = auth()->user();
        $propertiAktif = app()->bound('properti.aktif') ? app('properti.aktif') : null;
        $properti = $propertiAktif instanceof Properti ? $propertiAktif : null;

        $daftarNotifikasi = app(NotifikasiService::class)->daftar($user, $properti, sertakanAktivitas: false);

        return view('layouts.app', [
            'daftarNotifikasi' => $daftarNotifikasi,
        ]);
    }
}
