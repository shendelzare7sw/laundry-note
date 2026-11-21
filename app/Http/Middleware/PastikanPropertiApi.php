<?php

namespace App\Http\Middleware;

use App\Models\Properti;
use App\Models\User;
use App\Support\PesanAksesAkun;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanPropertiApi
{
    /**
     * Tetapkan konteks properti aktif untuk request API stateless (tanpa session):
     * - Admin/Penjaga otomatis terikat ke users.properti_id.
     * - Owner/DevAdmin memakai header X-Properti-Id (atau otomatis bila hanya ada 1 properti aktif).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->galat($request, 401, 'UNAUTHORIZED', 'Sesi autentikasi tidak valid atau sudah berakhir.');
        }

        if (! $user->aktif) {
            $user->currentAccessToken()?->delete();

            return $this->galat($request, 403, 'AKUN_NONAKTIF', PesanAksesAkun::akunNonaktif($user));
        }

        if ($user->role->isStafProperti()) {
            $properti = $user->properti;

            if ($properti === null) {
                $user->currentAccessToken()?->delete();

                return $this->galat($request, 403, 'PROPERTI_TIDAK_TERIKAT', PesanAksesAkun::propertiTidakTerikat());
            }

            if ($properti->status !== 'aktif') {
                $user->currentAccessToken()?->delete();

                return $this->galat($request, 403, 'PROPERTI_TIDAK_AKTIF', PesanAksesAkun::propertiTidakAktif());
            }

            app()->instance('properti.aktif', $properti);

            return $next($request);
        }

        $headerId = $request->header('X-Properti-Id') ?? $request->query('properti_id');

        if ($headerId !== null && $headerId !== '') {
            if (! is_numeric($headerId)) {
                return $this->galat($request, 422, 'PROPERTI_TIDAK_VALID', 'Header X-Properti-Id harus berupa angka ID properti.');
            }

            $properti = Properti::query()->find((int) $headerId);

            if ($properti === null || ! $user->bisaAksesProperti($properti)) {
                return $this->galat($request, 403, 'AKSES_PROPERTI_DITOLAK', 'Anda tidak memiliki akses ke properti tersebut.');
            }

            app()->instance('properti.aktif', $properti);

            return $next($request);
        }

        $daftarAktif = $user->isDevAdmin()
            ? Properti::query()->where('status', 'aktif')->orderBy('id')->get()
            : $user->propertiDimiliki()->where('status', 'aktif')->orderBy('id')->get();

        if ($daftarAktif->count() === 1) {
            app()->instance('properti.aktif', $daftarAktif->first());

            return $next($request);
        }

        return response()->json([
            'message' => 'Silakan pilih properti aktif melalui header X-Properti-Id.',
            'code' => 'PROPERTI_BELUM_DIPILIH',
            'request_id' => (string) $request->attributes->get('request_id', ''),
            'properti_list' => $daftarAktif->map(fn (Properti $p): array => [
                'id' => $p->id,
                'nama' => $p->nama,
                'status' => $p->status,
            ])->values()->all(),
        ], 422);
    }

    private function galat(Request $request, int $status, string $kode, string $pesan): JsonResponse
    {
        return response()->json([
            'message' => $pesan,
            'code' => $kode,
            'request_id' => (string) $request->attributes->get('request_id', ''),
        ], $status);
    }
}
