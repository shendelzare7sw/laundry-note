<?php

namespace App\Http\Controllers;

use App\Enums\Peran;
use App\Http\Requests\PropertiRequest;
use App\Models\Properti;
use App\Models\User;
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
        $query = Properti::query()->with(['adminAktif', 'owner'])->orderBy('id');

        if ($user->isOwner()) {
            $query->where('owner_id', $user->id);
        }

        return view('properti.index', [
            'daftarPropertiKelola' => $query->get(),
        ]);
    }

    public function store(PropertiRequest $request): RedirectResponse
    {
        $user = $request->user();
        $ownerId = $user->isOwner()
            ? $user->id
            : User::query()->where('role', Peran::Owner->value)->value('id');

        $properti = Properti::query()->create([
            'owner_id' => $ownerId,
            'nama' => $request->string('nama')->toString(),
            'alamat' => $request->input('alamat'),
            'nama_properti_laporan' => $request->string('nama_properti_laporan')->toString(),
            'status' => $request->string('status')->toString(),
        ]);

        $request->session()->put('properti_aktif_id', $properti->id);

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

    public function update(PropertiRequest $request, Properti $properti): RedirectResponse
    {
        $properti->update([
            'nama' => $request->string('nama')->toString(),
            'alamat' => $request->input('alamat'),
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
            ->back(fallback: route('beranda'))
            ->with('toast', ['tipe' => 'ok', 'pesan' => "Beralih ke properti \"{$properti->nama}\"."]);
    }
}
