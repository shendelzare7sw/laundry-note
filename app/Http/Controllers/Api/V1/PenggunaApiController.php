<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Peran;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordPenggunaRequest;
use App\Http\Requests\SimpanPenggunaRequest;
use App\Http\Resources\Api\V1\PropertiResource;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PenggunaApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        /** @var User $actor */
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

        $data = $query->get()->map(fn (User $u): array => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'email' => $u->email,
            'role' => $u->role->value,
            'role_label' => $u->role->label(),
            'properti_id' => $u->properti_id,
            'properti_nama' => $u->properti?->nama,
            'aktif' => (bool) $u->aktif,
            'boleh_ubah' => $actor->can('update', $u),
        ])->values()->all();

        return response()->json([
            'data' => $data,
            'peran_diizinkan' => array_map(fn (Peran $p): array => [
                'value' => $p->value,
                'label' => $p->label(),
            ], $peranDiizinkan),
            'properti_pilihan' => PropertiResource::collection($daftarPropertiPilihan)->resolve($request),
        ]);
    }

    public function store(SimpanPenggunaRequest $request): JsonResponse
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

        return response()->json([
            'message' => "Akun \"{$pengguna->username}\" berhasil dibuat.",
        ], 201);
    }

    public function toggleAktif(Request $request, User $pengguna): JsonResponse
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
                return response()->json([
                    'message' => 'Tidak dapat menonaktifkan satu-satunya Developer Admin yang aktif.',
                ], 422);
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
                return response()->json([
                    'message' => 'Properti ini sudah memiliki admin aktif lain. Nonaktifkan admin tersebut terlebih dahulu.',
                ], 422);
            }
        }

        $pengguna->update(['aktif' => $statusBaru]);

        if (! $statusBaru) {
            DB::table('sessions')->where('user_id', $pengguna->id)->delete();
            $pengguna->tokens()->delete();
        }

        $labelStatus = $statusBaru ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "Akun \"{$pengguna->username}\" berhasil {$labelStatus}.",
        ]);
    }

    public function resetPassword(ResetPasswordPenggunaRequest $request, User $pengguna): JsonResponse
    {
        $pengguna->update([
            'password' => $request->string('password')->toString(),
        ]);

        DB::table('sessions')->where('user_id', $pengguna->id)->delete();
        $pengguna->tokens()->delete();

        return response()->json([
            'message' => "Password untuk \"{$pengguna->username}\" berhasil diperbarui.",
        ]);
    }
}
