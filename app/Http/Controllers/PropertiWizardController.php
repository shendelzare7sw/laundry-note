<?php

namespace App\Http\Controllers;

use App\Http\Requests\WizardAturanRequest;
use App\Http\Requests\WizardKamarRequest;
use App\Models\Pengaturan;
use App\Models\Properti;
use App\Services\PropertiWizardService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PropertiWizardController extends Controller
{
    public function tampilkan(Properti $properti): View
    {
        Gate::authorize('update', $properti);

        $properti->load(['kamar', 'adminAktif', 'penjaga']);

        $pengaturanTersimpan = Pengaturan::query()
            ->untukProperti($properti)
            ->orderByDesc('berlaku_mulai')
            ->get()
            ->unique('kunci')
            ->pluck('nilai', 'kunci')
            ->all();

        $aturanSaatIni = array_merge(
            PengaturanSeeder::nilaiBawaan($properti->nama_properti_laporan),
            $pengaturanTersimpan
        );

        $kamarPerLantai = $properti->kamar->groupBy('lantai');
        $siapKamar = $properti->kamar->where('aktif', true)->isNotEmpty();
        $siapAturan = $pengaturanTersimpan !== [];
        $siapAdmin = $properti->adminAktif !== null;

        return view('properti.setup', [
            'properti' => $properti,
            'kamarPerLantai' => $kamarPerLantai,
            'aturanSaatIni' => $aturanSaatIni,
            'siapKamar' => $siapKamar,
            'siapAturan' => $siapAturan,
            'siapAdmin' => $siapAdmin,
        ]);
    }

    public function simpanKamar(
        WizardKamarRequest $request,
        Properti $properti,
        PropertiWizardService $wizard
    ): RedirectResponse {
        $jumlah = $wizard->generateKamarPerLantai(
            $properti,
            $request->validated('lantai'),
            $request->user()
        );

        return redirect()
            ->route('properti.setup.tampilkan', $properti)
            ->with('toast', ['tipe' => 'ok', 'pesan' => "{$jumlah} kamar baru berhasil ditambahkan."]);
    }

    public function simpanAturan(
        WizardAturanRequest $request,
        Properti $properti,
        PropertiWizardService $wizard
    ): RedirectResponse {
        $data = $request->validated();
        $data['penjaga_kelola_kontrak'] = $request->boolean('penjaga_kelola_kontrak') ? '1' : '0';

        $wizard->simpanAturan($properti, $data, $request->user());

        return redirect()
            ->route('properti.setup.tampilkan', $properti)
            ->with('toast', ['tipe' => 'ok', 'pesan' => 'Aturan laundry properti berhasil disimpan.']);
    }

    public function aktifkan(
        Request $request,
        Properti $properti,
        PropertiWizardService $wizard
    ): RedirectResponse {
        Gate::authorize('update', $properti);

        $hasil = $wizard->aktifkanProperti($properti, $request->user());

        if (! $hasil['berhasil']) {
            return redirect()
                ->route('properti.setup.tampilkan', $properti)
                ->withErrors(['aktivasi' => $hasil['alasan']])
                ->with('toast', ['tipe' => 'danger', 'pesan' => $hasil['alasan'][0]]);
        }

        return redirect()
            ->route('properti.setup.tampilkan', $properti)
            ->with('toast', ['tipe' => 'ok', 'pesan' => "Properti \"{$properti->nama}\" sekarang aktif."]);
    }
}
