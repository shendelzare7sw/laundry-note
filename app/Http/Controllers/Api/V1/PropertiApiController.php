<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KamarResource;
use App\Http\Resources\Api\V1\PropertiResource;
use App\Models\Kamar;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertiApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $daftar = match (true) {
            $user->isDevAdmin() => Properti::query()->withCount(['kamar', 'penghuniAktif'])->orderBy('id')->get(),
            $user->isOwner() => $user->propertiDimiliki()->withCount(['kamar', 'penghuniAktif'])->orderBy('id')->get(),
            $user->properti_id !== null => Properti::query()->whereKey($user->properti_id)->withCount(['kamar', 'penghuniAktif'])->get(),
            default => collect(),
        };

        return response()->json([
            'data' => PropertiResource::collection($daftar)->resolve($request),
        ]);
    }

    public function kamar(Request $request): JsonResponse
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');
        $hanyaKosong = $request->boolean('kosong');

        $daftar = Kamar::query()
            ->where('properti_id', $properti->id)
            ->where('aktif', true)
            ->with('penempatanAktif.penghuni')
            ->when($hanyaKosong, fn ($q) => $q->whereDoesntHave('penempatanAktif'))
            ->orderBy('lantai')
            ->orderBy('nomor')
            ->get();

        return response()->json([
            'data' => KamarResource::collection($daftar)->resolve($request),
        ]);
    }
}
