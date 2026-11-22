<?php

namespace App\Http\Controllers;

use App\Models\Properti;
use App\Services\NotifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function __construct(
        private readonly NotifikasiService $notifikasiService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $propertiAktif = null;

        if ($user !== null) {
            if ($user->role->isStafProperti() && $user->properti_id !== null) {
                $propertiAktif = Properti::query()->find($user->properti_id);
            } elseif ($user->role->isGlobal()) {
                $propertiId = $request->session()->get('properti_aktif_id');
                if ($propertiId) {
                    $propertiAktif = Properti::query()->find((int) $propertiId);
                }
            }
        }

        $items = $this->notifikasiService
            ->daftar($user, $propertiAktif)
            ->take(25)
            ->values()
            ->all();

        return response()->json([
            'items' => $items,
            'server_time_ms' => now('Asia/Jakarta')->getTimestampMs(),
        ]);
    }
}
