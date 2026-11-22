<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Properti;
use App\Services\NotifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiApiController extends Controller
{
    public function __construct(
        private readonly NotifikasiService $notifikasiService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;
        $propertiAktif = $properti instanceof Properti ? $properti : null;

        $items = $this->notifikasiService
            ->daftar($request->user(), $propertiAktif)
            ->take(30)
            ->values()
            ->all();

        return response()->json([
            'items' => $items,
            'server_time_ms' => now('Asia/Jakarta')->getTimestampMs(),
        ]);
    }
}
