<?php

namespace App\View\Components;

use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\KuotaService;
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
        $daftarNotifikasi = collect();
        $user = auth()->user();
        $propertiAktif = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        if ($propertiAktif instanceof Properti) {
            $penghuniAktif = Penghuni::query()
                ->where('properti_id', $propertiAktif->id)
                ->whereIn('status', ['aktif', 'booking'])
                ->with('penempatanAktif.kamar')
                ->get();

            $ringkasan = app(KuotaService::class)->ringkasanSemua(
                properti: $propertiAktif,
                penghuniIds: $penghuniAktif->pluck('id')->all(),
            );

            foreach ($penghuniAktif as $penghuni) {
                $kuota = $ringkasan->get($penghuni->id);
                $status = $kuota['status'] ?? 'normal';

                if (! in_array($status, ['lewat', 'mendekati'], true)) {
                    continue;
                }

                $kamar = $penghuni->penempatanAktif?->kamar?->nomor ?? '-';

                $daftarNotifikasi->push([
                    'prioritas' => $status === 'lewat' ? 1 : 2,
                    'persen' => $kuota['persen'] ?? 0,
                    'tipe' => $status === 'lewat' ? 'danger' : 'warn',
                    'judul' => $status === 'lewat'
                        ? "{$penghuni->nama} (Kamar {$kamar}) melewati kuota"
                        : "{$penghuni->nama} (Kamar {$kamar}) mendekati kuota",
                    'keterangan' => $status === 'lewat'
                        ? "Pemakaian {$kuota['terpakai_kg']}/{$kuota['kuota_kg']} kg · Tagihan Rp".number_format($kuota['tagihan_lewat_kuota'], 0, ',', '.')
                        : "Pemakaian {$kuota['terpakai_kg']}/{$kuota['kuota_kg']} kg · Sisa {$kuota['sisa_kg']} kg",
                    'url' => route('penghuni.show', $penghuni),
                ]);
            }

            if ($user && $user->role->isGlobal() && ($propertiAktif->status !== 'aktif' || ! $propertiAktif->adminAktif()->exists())) {
                $daftarNotifikasi->push([
                    'prioritas' => 0,
                    'persen' => 100,
                    'tipe' => 'info',
                    'judul' => "Setup properti {$propertiAktif->nama} belum lengkap",
                    'keterangan' => $propertiAktif->status !== 'aktif'
                        ? "Status properti masih {$propertiAktif->status}."
                        : 'Admin properti aktif belum ditetapkan.',
                    'url' => route('properti.setup.tampilkan', $propertiAktif),
                ]);
            }

            $daftarNotifikasi = $daftarNotifikasi
                ->sortBy([['prioritas', 'asc'], ['persen', 'desc']])
                ->values();
        } elseif ($user && $user->role->isGlobal()) {
            $propertiPerluSetup = ($user->isDevAdmin() ? Properti::query() : $user->propertiDimiliki())
                ->where(function ($query): void {
                    $query->where('status', 'draf')->orWhereDoesntHave('adminAktif');
                })
                ->orderBy('id')
                ->limit(5)
                ->get();

            foreach ($propertiPerluSetup as $prop) {
                $daftarNotifikasi->push([
                    'prioritas' => 0,
                    'persen' => 100,
                    'tipe' => 'info',
                    'judul' => "Lengkapi setup {$prop->nama}",
                    'keterangan' => $prop->status !== 'aktif'
                        ? "Status saat ini: {$prop->status}."
                        : 'Admin aktif belum ditentukan.',
                    'url' => route('properti.setup.tampilkan', $prop),
                ]);
            }
        }

        return view('layouts.app', [
            'daftarNotifikasi' => $daftarNotifikasi,
        ]);
    }
}
