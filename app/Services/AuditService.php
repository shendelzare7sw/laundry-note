<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditService
{
    /**
     * Catat perubahan penting ke tabel audit_log dan picu FCM push real-time setelah commit.
     *
     * @param  array<string, mixed>|null  $sebelum
     * @param  array<string, mixed>|null  $sesudah
     */
    public function catat(
        ?int $propertiId,
        ?User $aktor,
        string $aksi,
        Model|string|null $subjek = null,
        ?int $subjekId = null,
        ?array $sebelum = null,
        ?array $sesudah = null,
        ?string $ip = null,
    ): AuditLog {
        $subjekType = $subjek instanceof Model ? $subjek::class : $subjek;
        $resolvedId = $subjek instanceof Model ? $subjek->getKey() : $subjekId;

        $log = AuditLog::query()->create([
            'properti_id' => $propertiId,
            'user_id' => $aktor?->id,
            'aksi' => $aksi,
            'subjek_type' => $subjekType,
            'subjek_id' => $resolvedId !== null ? (int) $resolvedId : null,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
            'ip' => $ip ?? request()?->ip(),
            'created_at' => now('Asia/Jakarta'),
        ]);

        if ($propertiId !== null && ! app()->runningUnitTests()) {
            $originToken = request()?->header('X-Device-Token');
            $originTokenStr = is_string($originToken) && trim($originToken) !== '' ? trim($originToken) : null;

            DB::afterCommit(function () use ($log, $originTokenStr): void {
                app(FcmPushService::class)->kirimUntukAudit($log, $originTokenStr);
            });
        }

        return $log;
    }
}
