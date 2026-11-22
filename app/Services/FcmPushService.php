<?php

namespace App\Services;

use App\Enums\Peran;
use App\Models\AuditLog;
use App\Models\DeviceToken;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmPushService
{
    private const CACHE_ACCESS_TOKEN = 'fcm_oauth2_access_token_v1';

    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function __construct(
        private readonly NotifikasiService $notifikasiService,
    ) {}

    /**
     * Kirim High-Priority Push Notification via FCM HTTP v1 ke semua perangkat staf/owner
     * yang berhak atas properti terkait (kecuali perangkat pengirim jika berasal dari HP yang sama).
     */
    public function kirimUntukAudit(AuditLog $log, ?string $originDeviceToken = null): void
    {
        if ($log->properti_id === null) {
            return;
        }

        $credentials = $this->muatKredensial();
        if ($credentials === null) {
            return;
        }

        $payload = $this->notifikasiService->formatSatuAudit($log);
        if ($payload === null) {
            return;
        }

        $tokens = $this->daftarTokenPenerima((int) $log->properti_id, $originDeviceToken);
        if ($tokens === []) {
            return;
        }

        $accessToken = $this->dapatkanAccessToken($credentials);
        if ($accessToken === null) {
            return;
        }

        $projectId = $credentials['project_id'];
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $dataPayload = [
            'id' => (string) $payload['id'],
            'properti_id' => (string) $log->properti_id,
            'type' => strtoupper((string) ($payload['tipe'] ?? 'ACTIVITY')),
            'title' => (string) $payload['judul'],
            'subtitle' => (string) ($payload['keterangan'] ?? ''),
            'timestamp_ms' => (string) ($payload['timestamp_ms'] ?? now('Asia/Jakarta')->getTimestampMs()),
            'aktor_id' => $payload['aktor_id'] !== null ? (string) $payload['aktor_id'] : '',
            'penghuni_id' => $payload['penghuni_id'] !== null ? (string) $payload['penghuni_id'] : '',
            'laundry_id' => $payload['laundry_id'] !== null ? (string) $payload['laundry_id'] : '',
        ];

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($accessToken)
                    ->timeout(6)
                    ->post($endpoint, [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => (string) $payload['judul'],
                                'body' => (string) ($payload['keterangan'] ?? ''),
                            ],
                            'data' => $dataPayload,
                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => [
                                    'channel_id' => 'laundry_kos_activity',
                                    'sound' => 'default',
                                    'color' => '#8B1A1A',
                                ],
                            ],
                        ],
                    ]);

                if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                    DeviceToken::query()->where('token', $token)->delete();
                }
            } catch (Throwable $e) {
                Log::warning('Gagal mengirim FCM push notification.', [
                    'audit_id' => $log->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function daftarTokenPenerima(int $propertiId, ?string $excludeToken): array
    {
        $properti = Properti::query()->find($propertiId);
        if (! $properti instanceof Properti) {
            return [];
        }

        $penerimaUserIds = User::query()
            ->where('aktif', true)
            ->where(function ($q) use ($properti): void {
                $q->where('properti_id', $properti->id)
                    ->orWhere('id', $properti->owner_id)
                    ->orWhere('role', Peran::DevAdmin->value);
            })
            ->pluck('id')
            ->all();

        if ($penerimaUserIds === []) {
            return [];
        }

        $query = DeviceToken::query()->whereIn('user_id', $penerimaUserIds);
        if ($excludeToken !== null && trim($excludeToken) !== '') {
            $query->where('token', '!=', trim($excludeToken));
        }

        return $query->pluck('token')->unique()->values()->all();
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    private function muatKredensial(): ?array
    {
        $path = (string) config('services.firebase.credentials_path', storage_path('app/firebase-service-account.json'));
        if ($path === '' || ! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $json = json_decode($raw, true);
        if (! is_array($json) || empty($json['project_id']) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        return [
            'project_id' => (string) $json['project_id'],
            'client_email' => (string) $json['client_email'],
            'private_key' => (string) $json['private_key'],
        ];
    }

    /**
     * @param  array{project_id: string, client_email: string, private_key: string}  $credentials
     */
    private function dapatkanAccessToken(array $credentials): ?string
    {
        $cached = Cache::get(self::CACHE_ACCESS_TOKEN);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $now = time();
            $header = $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64UrlEncode((string) json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::FCM_SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signingInput = "{$header}.{$claims}";
            $signature = '';
            $ok = openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
            if (! $ok) {
                return null;
            }

            $jwt = $signingInput.'.'.$this->base64UrlEncode($signature);

            $res = Http::asForm()
                ->timeout(8)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (! $res->successful()) {
                Log::warning('Gagal mendapatkan OAuth2 access token Firebase.', [
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);

                return null;
            }

            $token = (string) ($res->json('access_token') ?? '');
            if ($token === '') {
                return null;
            }

            Cache::put(self::CACHE_ACCESS_TOKEN, $token, now()->addMinutes(50));

            return $token;
        } catch (Throwable $e) {
            Log::warning('Exception saat membuat OAuth2 token Firebase.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
