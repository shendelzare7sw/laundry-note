<?php

namespace App\Http\Middleware;

use App\Models\Properti;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class PastikanPropertiAktif
{
    /**
     * Tetapkan properti aktif sesuai peran pengguna dan pastikan akun serta properti valid.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->aktif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => __('auth.inactive')]);
        }

        if ($user->role->isStafProperti()) {
            $properti = $user->properti;

            if ($properti === null) {
                abort(403, 'Akun Anda belum terhubung ke properti mana pun.');
            }

            if ($properti->status !== 'aktif') {
                abort(403, 'Properti penempatan Anda sedang tidak aktif.');
            }

            $routeProperti = $request->route('properti');

            if ($routeProperti !== null) {
                $routePropertiId = $routeProperti instanceof Properti
                    ? $routeProperti->id
                    : (int) $routeProperti;

                if ($routePropertiId !== $properti->id) {
                    abort(403, 'Anda tidak memiliki akses ke properti lain.');
                }
            }

            app()->instance('properti.aktif', $properti);
            View::share('propertiAktif', $properti);
            View::share('daftarProperti', collect([$properti]));

            return $next($request);
        }

        $daftarProperti = $user->isDevAdmin()
            ? Properti::query()->orderBy('id')->get()
            : $user->propertiDimiliki()->orderBy('id')->get();

        $propertiAktifId = (int) $request->session()->get('properti_aktif_id', 0);
        $properti = $propertiAktifId > 0
            ? $daftarProperti->firstWhere('id', $propertiAktifId)
            : null;

        if ($properti !== null) {
            app()->instance('properti.aktif', $properti);
        } elseif ($propertiAktifId > 0) {
            $request->session()->forget('properti_aktif_id');
        }

        View::share('propertiAktif', $properti);
        View::share('daftarProperti', $daftarProperti);

        if ($properti === null && $request->routeIs('beranda', 'laundry.*', 'penghuni.*', 'laporan.*')) {
            return redirect()->route('properti.index');
        }

        return $next($request);
    }
}
