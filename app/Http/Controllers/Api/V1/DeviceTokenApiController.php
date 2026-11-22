<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:255'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        DeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now('Asia/Jakarta'),
            ],
        );

        return response()->json([
            'message' => 'Token perangkat berhasil terdaftar.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = trim((string) ($request->input('token') ?: $request->header('X-Device-Token', '')));

        if ($token !== '') {
            DeviceToken::query()
                ->where('token', $token)
                ->where('user_id', $request->user()->id)
                ->delete();
        }

        return response()->json([
            'message' => 'Token perangkat dihapus.',
        ]);
    }
}
