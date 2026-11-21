<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PropertiResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Laundry;
use App\Models\Pengaturan;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        /** @var User|null $user */
        $user = User::query()
            ->with('properti')
            ->where('username', trim($data['username']))
            ->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        if (! $user->aktif) {
            return response()->json([
                'message' => 'Akun Anda sedang dinonaktifkan. Hubungi pengelola.',
                'code' => 'AKUN_NONAKTIF',
                'request_id' => (string) $request->attributes->get('request_id', ''),
            ], 403);
        }

        if ($user->role->isStafProperti() && ($user->properti === null || $user->properti->status !== 'aktif')) {
            return response()->json([
                'message' => 'Properti penempatan Anda sedang tidak aktif.',
                'code' => 'PROPERTI_TIDAK_AKTIF',
                'request_id' => (string) $request->attributes->get('request_id', ''),
            ], 403);
        }

        $namaPerangkat = trim((string) ($data['device_name'] ?? 'android'));
        $token = $user->createToken($namaPerangkat !== '' ? $namaPerangkat : 'android')->plainTextToken;

        $propertiList = $this->daftarPropertiAkses($user);
        $propertiAktif = $this->resolvePropertiAwal($user, $propertiList);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve($request),
            'properti_aktif' => $propertiAktif !== null ? (new PropertiResource($propertiAktif))->resolve($request) : null,
            'properti_list' => PropertiResource::collection($propertiList)->resolve($request),
            'permissions' => $this->hakAkses($user, $propertiAktif),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Berhasil keluar dari perangkat ini.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('properti');

        if (! $user->aktif) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Akun Anda sedang dinonaktifkan. Hubungi pengelola.',
                'code' => 'AKUN_NONAKTIF',
                'request_id' => (string) $request->attributes->get('request_id', ''),
            ], 403);
        }

        $propertiList = $this->daftarPropertiAkses($user);
        $headerId = $request->header('X-Properti-Id');
        $propertiAktif = is_numeric($headerId)
            ? $propertiList->firstWhere('id', (int) $headerId)
            : $this->resolvePropertiAwal($user, $propertiList);

        return response()->json([
            'user' => (new UserResource($user))->resolve($request),
            'properti_aktif' => $propertiAktif !== null ? (new PropertiResource($propertiAktif))->resolve($request) : null,
            'properti_list' => PropertiResource::collection($propertiList)->resolve($request),
            'permissions' => $this->hakAkses($user, $propertiAktif),
        ]);
    }

    /**
     * @return Collection<int, Properti>
     */
    private function daftarPropertiAkses(User $user): Collection
    {
        if ($user->isDevAdmin()) {
            return Properti::query()->withCount(['kamar', 'penghuniAktif'])->orderBy('id')->get();
        }

        if ($user->isOwner()) {
            return $user->propertiDimiliki()->withCount(['kamar', 'penghuniAktif'])->orderBy('id')->get();
        }

        if ($user->properti_id === null) {
            return collect();
        }

        return Properti::query()
            ->whereKey($user->properti_id)
            ->withCount(['kamar', 'penghuniAktif'])
            ->get();
    }

    /**
     * @param  Collection<int, Properti>  $propertiList
     */
    private function resolvePropertiAwal(User $user, Collection $propertiList): ?Properti
    {
        if ($user->role->isStafProperti()) {
            return $propertiList->first();
        }

        $aktif = $propertiList->where('status', 'aktif')->values();

        return $aktif->count() === 1 ? $aktif->first() : null;
    }

    /**
     * @return array<string, bool>
     */
    private function hakAkses(User $user, ?Properti $properti): array
    {
        return [
            'kelola_properti' => $user->can('viewAny', Properti::class),
            'kelola_pengguna' => $user->can('viewAny', User::class),
            'kelola_pengaturan' => $properti !== null && $user->can('manage', [Pengaturan::class, $properti]),
            'kelola_kontrak' => $properti !== null && $user->can('kelolaKontrak', [Penghuni::class, $properti]),
            'catat_laundry' => $properti !== null && $user->can('create', [Laundry::class, $properti]),
            'lengkapi_harga' => $properti !== null && $user->can('lengkapiHarga', [Laundry::class, $properti]),
            'lihat_terhapus' => $properti !== null && $user->can('lihatTerhapus', [Laundry::class, $properti]),
            'sesuaikan_kuota' => $properti !== null && ! $user->isPenjaga(),
        ];
    }
}
