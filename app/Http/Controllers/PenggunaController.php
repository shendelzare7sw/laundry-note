<?php

namespace App\Http\Controllers;

use App\Enums\Peran;
use App\Http\Requests\ResetPasswordPenggunaRequest;
use App\Http\Requests\SimpanPenggunaRequest;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PenggunaController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();
        $query = User::query()->with('properti')->orderBy('role')->orderBy('name');

        if ($actor->isAdmin()) {
            $query->where('properti_id', $actor->properti_id)
                ->whereIn('role', [Peran::Admin->value, Peran::Penjaga->value]);
        } elseif ($actor->isOwner()) {
            $propertiIds = $actor->propertiDimiliki()->pluck('id');
            $query->whereIn('properti_id', $propertiIds)
                ->whereIn('role', [Peran::Admin->value, Peran::Penjaga->value]);
        }

        $daftarPropertiPilihan = match (true) {
            $actor->isDevAdmin() => Properti::query()->orderBy('nama')->get(),
            $actor->isOwner() => $actor->propertiDimiliki()->orderBy('nama')->get(),
            default => collect([$actor->properti])->filter(),
        };

        $peranDiizinkan = match (true) {
            $actor->isDevAdmin() => Peran::cases(),
            $actor->isOwner() => [Peran::Admin, Peran::Penjaga],
            default => [Peran::Penjaga],
        };

        return view('pengguna.index', [
            'daftarPengguna' => $query->get(),
            'daftarPropertiPilihan' => $daftarPropertiPilihan,
            'peranDiizinkan' => $peranDiizinkan,
        ]);
    }

    public function store(SimpanPenggunaRequest $request): RedirectResponse
    {
        $role = Peran::from($request->string('role')->toString());

        $pengguna = User::query()->create([
            'name' => $request->string('name')->toString(),
            'username' => $request->string('username')->toString(),
            'email' => $request->input('email'),
            'password' => $request->string('password')->toString(),
            'role' => $role,
            'properti_id' => $role->isStafProperti() ? $request->integer('properti_id') : null,
            'aktif' => true,
        ]);

        if ($request->filled('setup_properti_id')) {
            return redirect()
                ->route('properti.setup.tampilkan', $request->integer('setup_properti_id'))
                ->with('toast', ['tipe' => 'ok', 'pesan' => "Akun \"{$pengguna->username}\" berhasil dibuat."]);
        }

        return redirect()
            ->route('pengguna.index')
            ->with('toast', ['tipe' => 'ok', 'pesan' => "Akun \"{$pengguna->username}\" berhasil dibuat."]);
    }

    public function toggleAktif(Request $request, User $pengguna): RedirectResponse
    {
        Gate::authorize('update', $pengguna);

        $statusBaru = ! $pengguna->aktif;

        if (! $statusBaru && $pengguna->role === Peran::DevAdmin) {
            $sisaDevAdminAktif = User::query()
                ->where('role', Peran::DevAdmin->value)
                ->where('aktif', true)
                ->whereKeyNot($pengguna->id)
                ->count();

            if ($sisaDevAdminAktif === 0) {
                return redirect()
                    ->route('pengguna.index')
                    ->with('toast', [
                        'tipe' => 'danger',
                        'pesan' => 'Tidak dapat menonaktifkan satu-satunya Developer Admin yang aktif.',
                    ]);
            }
        }

        if ($statusBaru && $pengguna->role === Peran::Admin && $pengguna->properti_id !== null) {
            $sudahAdaAdminAktif = User::query()
                ->where('properti_id', $pengguna->properti_id)
                ->where('role', Peran::Admin->value)
                ->where('aktif', true)
                ->whereKeyNot($pengguna->id)
                ->exists();

            if ($sudahAdaAdminAktif) {
                return redirect()
                    ->route('pengguna.index')
                    ->with('toast', [
                        'tipe' => 'danger',
                        'pesan' => 'Properti ini sudah memiliki admin aktif lain. Nonaktifkan admin tersebut terlebih dahulu.',
                    ]);
            }
        }

        $pengguna->update(['aktif' => $statusBaru]);

        if (! $statusBaru) {
            DB::table('sessions')->where('user_id', $pengguna->id)->delete();
            $pengguna->tokens()->delete();
        }

        $labelStatus = $statusBaru ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('pengguna.index')
            ->with('toast', ['tipe' => 'ok', 'pesan' => "Akun \"{$pengguna->username}\" berhasil {$labelStatus}."]);
    }

    public function resetPassword(ResetPasswordPenggunaRequest $request, User $pengguna): RedirectResponse
    {
        $pengguna->update([
            'password' => $request->string('password')->toString(),
        ]);

        DB::table('sessions')->where('user_id', $pengguna->id)->delete();
        $pengguna->tokens()->delete();

        return redirect()
            ->route('pengguna.index')
            ->with('toast', ['tipe' => 'ok', 'pesan' => "Password untuk \"{$pengguna->username}\" berhasil diperbarui."]);
    }
}
