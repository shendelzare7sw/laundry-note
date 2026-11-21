<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PesanAksesAkun;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanAkunAktifApi
{
    /**
     * Pastikan setiap request API terautentikasi berasal dari akun aktif
     * dan (untuk staf properti) terhubung ke properti berstatus aktif.
     * Bila tidak valid, token saat ini langsung dicabut dan akses ditolak (HTTP 403).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->galat($request, 401, 'UNAUTHORIZED', 'Sesi login Anda telah berakhir atau dicabut oleh pengelola. Silakan masuk kembali.');
        }

        if (! $user->aktif) {
            $user->currentAccessToken()?->delete();

            return $this->galat($request, 403, 'AKUN_NONAKTIF', PesanAksesAkun::akunNonaktif($user));
        }

        if ($user->role->isStafProperti()) {
            $user->loadMissing('properti');

            if ($user->properti === null) {
                $user->currentAccessToken()?->delete();

                return $this->galat($request, 403, 'PROPERTI_TIDAK_TERIKAT', PesanAksesAkun::propertiTidakTerikat());
            }

            if ($user->properti->status !== 'aktif') {
                $user->currentAccessToken()?->delete();

                return $this->galat($request, 403, 'PROPERTI_TIDAK_AKTIF', PesanAksesAkun::propertiTidakAktif());
            }
        }

        return $next($request);
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
