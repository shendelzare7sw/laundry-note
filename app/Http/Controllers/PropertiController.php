<?php

namespace App\Http\Controllers;

use App\Enums\Peran;
use App\Http\Requests\PropertiRequest;
use App\Models\Properti;
use App\Models\User;
use App\Services\PropertiWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PropertiController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Properti::class);

        $user = $request->user();
        $query = Properti::query()
            ->with(['adminAktif', 'owner'])
            ->withCount(['kamar', 'penghuni'])
            ->orderBy('id');

        if ($user->isOwner()) {
            $query->where('owner_id', $user->id);
        }

        return view('properti.index', [
            'daftarPropertiKelola' => $query->get(),
        ]);
    }

    public function store(PropertiRequest $request, PropertiWizardService $wizard): RedirectResponse
    {
        $user = $request->user();
        $ownerId = $user->isOwner()
            ? $user->id
            : User::query()->where('role', Peran::Owner->value)->value('id');

        $properti = Properti::query()->create([
            'owner_id' => $ownerId,
            'nama' => $request->string('nama')->toString(),
            'alamat' => $request->input('alamat'),
            'no_wa_admin' => $request->input('no_wa_admin'),
            'deskripsi' => $request->input('deskripsi'),
            'nama_properti_laporan' => $request->string('nama_properti_laporan')->toString(),
            'status' => $request->string('status')->toString(),
        ]);

        $wizard->salinPengaturanBawaan($properti, $user);

        $request->session()->put('properti_aktif_id', $properti->id);

        if ($properti->status === 'draf') {
            return redirect()
                ->route('properti.setup.tampilkan', $properti)
                ->with('toast', ['tipe' => 'ok', 'pesan' => "Properti \"{$properti->nama}\" dibuat sebagai draf. Lengkapi setup sebelum diaktifkan."]);
        }

        return redirect()
            ->route('properti.index')
            ->with('toast', ['tipe' => 'ok', 'pesan' => "Properti \"{$properti->nama}\" berhasil ditambahkan."]);
    }

    public function tampilkan(Properti $properti): View
    {
        Gate::authorize('view', $properti);

        $properti->load(['owner', 'adminAktif', 'penjaga']);

        return view('properti.tampilkan', [
            'properti' => $properti,
        ]);
    }

    public function edit(Properti $properti): View
    {
        Gate::authorize('update', $properti);

        return view('properti.edit', [
            'properti' => $properti,
        ]);
    }

    public function update(PropertiRequest $request, Properti $properti): RedirectResponse
    {
        $properti->update([
            'nama' => $request->string('nama')->toString(),
            'alamat' => $request->input('alamat'),
            'no_wa_admin' => $request->input('no_wa_admin'),
            'deskripsi' => $request->input('deskripsi'),
            'nama_properti_laporan' => $request->string('nama_properti_laporan')->toString(),
            'status' => $request->string('status')->toString(),
        ]);

        return redirect()
            ->route('properti.index')
            ->with('toast', ['tipe' => 'ok', 'pesan' => "Properti \"{$properti->nama}\" berhasil diperbarui."]);
    }

    public function pilih(Request $request, Properti $properti): RedirectResponse
    {
        Gate::authorize('view', $properti);

        $request->session()->put('properti_aktif_id', $properti->id);

        return redirect()
            ->route('beranda')
            ->with('toast', ['tipe' => 'ok', 'pesan' => "Membuka properti \"{$properti->nama}\"."]);
    }

    public function lepas(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Properti::class);

        $request->session()->forget('properti_aktif_id');

        return redirect()->route('properti.index');
    }
}
