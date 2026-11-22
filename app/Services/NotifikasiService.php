<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Kamar;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class NotifikasiService
{
    public function __construct(
        private readonly KuotaService $kuotaService,
    ) {}

    /**
     * Bangun daftar notifikasi terpadu (setup properti, aktivitas lintas role/perangkat dari audit_log,
     * dan peringatan kuota penghuni) agar Web dan Mobile APK selalu 100% sinkron.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function daftar(?User $user, ?Properti $propertiAktif, bool $sertakanAktivitas = true): Collection
    {
        if (! $user instanceof User) {
            return collect();
        }

        $items = collect();

        if ($propertiAktif instanceof Properti) {
            // 1. Setup properti belum lengkap (khusus Owner/DevAdmin)
            if ($user->role->isGlobal() && ($propertiAktif->status !== 'aktif' || ! $propertiAktif->adminAktif()->exists())) {
                $items->push([
                    'id' => "setup_{$propertiAktif->id}_{$propertiAktif->status}",
                    'tipe' => 'info',
                    'judul' => "Setup properti {$propertiAktif->nama} belum lengkap",
                    'keterangan' => $propertiAktif->status !== 'aktif'
                        ? "Status properti masih {$propertiAktif->status}."
                        : 'Admin properti aktif belum ditetapkan.',
                    'waktu_label' => null,
                    'timestamp_ms' => null,
                    'aktor_id' => null,
                    'penghuni_id' => null,
                    'laundry_id' => null,
                    'url' => route('properti.setup.tampilkan', $propertiAktif),
                ]);
            }

            // 2. Aktivitas perubahan data terbaru (7 hari terakhir) dari Web maupun Mobile API
            if ($sertakanAktivitas) {
                foreach ($this->daftarAktivitasAudit($propertiAktif) as $aktivitas) {
                    $items->push($aktivitas);
                }
            }

            // 3. Peringatan kuota penghuni aktif
            $penghuniAktif = Penghuni::query()
                ->where('properti_id', $propertiAktif->id)
                ->whereIn('status', ['aktif', 'booking'])
                ->with('penempatanAktif.kamar')
                ->get();

            $ringkasan = $this->kuotaService->ringkasanSemua(
                properti: $propertiAktif,
                penghuniIds: $penghuniAktif->pluck('id')->all(),
            );

            $kuotaItems = collect();
            foreach ($penghuniAktif as $penghuni) {
                $kuota = $ringkasan->get($penghuni->id);
                $status = $kuota['status'] ?? 'normal';

                if (! in_array($status, ['lewat', 'mendekati'], true)) {
                    continue;
                }

                $kamar = $penghuni->penempatanAktif?->kamar?->nomor ?? '-';

                $kuotaItems->push([
                    'prioritas' => $status === 'lewat' ? 1 : 2,
                    'persen' => $kuota['persen'] ?? 0,
                    'id' => "kuota_{$propertiAktif->id}_{$penghuni->id}_{$status}",
                    'tipe' => $status === 'lewat' ? 'danger' : 'warn',
                    'judul' => $status === 'lewat'
                        ? "{$penghuni->nama} (Kamar {$kamar}) melewati kuota"
                        : "{$penghuni->nama} (Kamar {$kamar}) mendekati kuota",
                    'keterangan' => $status === 'lewat'
                        ? "Pemakaian {$kuota['terpakai_kg']}/{$kuota['kuota_kg']} kg · Tagihan Rp".number_format($kuota['tagihan_lewat_kuota'], 0, ',', '.')
                        : "Pemakaian {$kuota['terpakai_kg']}/{$kuota['kuota_kg']} kg · Sisa {$kuota['sisa_kg']} kg",
                    'waktu_label' => null,
                    'timestamp_ms' => null,
                    'aktor_id' => null,
                    'penghuni_id' => $penghuni->id,
                    'laundry_id' => null,
                    'url' => route('penghuni.show', $penghuni),
                ]);
            }

            foreach ($kuotaItems->sortBy([['prioritas', 'asc'], ['persen', 'desc']]) as $kItem) {
                unset($kItem['prioritas'], $kItem['persen']);
                $items->push($kItem);
            }
        } elseif ($user->role->isGlobal()) {
            $propertiPerluSetup = ($user->isDevAdmin() ? Properti::query() : $user->propertiDimiliki())
                ->where(function ($query): void {
                    $query->where('status', 'draf')->orWhereDoesntHave('adminAktif');
                })
                ->orderBy('id')
                ->limit(10)
                ->get();

            foreach ($propertiPerluSetup as $prop) {
                $items->push([
                    'id' => "prop_{$prop->id}_{$prop->status}",
                    'tipe' => 'info',
                    'judul' => "Lengkapi setup {$prop->nama}",
                    'keterangan' => $prop->status !== 'aktif'
                        ? "Status saat ini: {$prop->status}."
                        : 'Admin aktif belum ditentukan.',
                    'waktu_label' => null,
                    'timestamp_ms' => null,
                    'aktor_id' => null,
                    'penghuni_id' => null,
                    'laundry_id' => null,
                    'url' => route('properti.setup.tampilkan', $prop),
                ]);
            }
        }

        return $items->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function daftarAktivitasAudit(Properti $properti): array
    {
        $sejak = now('Asia/Jakarta')->subDays(7);

        $logs = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('properti_id', $properti->id)
            ->where('created_at', '>=', $sejak)
            ->whereIn('aksi', [
                'laundry_tambah',
                'laundry_ubah',
                'laundry_hapus',
                'laundry_pulihkan',
                'laundry_tetapkan_pemilik',
                'laundry_lengkapi_harga',
                'penghuni_masuk',
                'penghuni_pindah_kamar',
                'penghuni_batalkan_pindah',
                'penghuni_keluar',
                'penghuni_batalkan_keluar',
                'penghuni_ganti_nomor',
                'penghuni_ubah_data',
                'kuota_simpan_penyesuaian',
                'kuota_hapus_penyesuaian',
                'pengaturan_aturan_ubah',
                'pengaturan_izin_penjaga_ubah',
                'pengaturan_template_wa_ubah',
            ])
            ->with('pengguna:id,name,role')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        if ($logs->isEmpty()) {
            return [];
        }

        // Batch-load penghuni & kamar untuk mencegah N+1 query
        $penghuniIds = [];
        $kamarIds = [];
        foreach ($logs as $log) {
            $snap = $log->sesudah ?? $log->sebelum ?? [];
            if (! empty($snap['penghuni_id'])) {
                $penghuniIds[] = (int) $snap['penghuni_id'];
            }
            if (! empty($snap['kamar_id'])) {
                $kamarIds[] = (int) $snap['kamar_id'];
            }
            if ($log->subjek_type === Penghuni::class && $log->subjek_id) {
                $penghuniIds[] = (int) $log->subjek_id;
            }
        }

        $penghuniMap = $penghuniIds !== []
            ? Penghuni::withTrashed()->whereIn('id', array_unique($penghuniIds))->pluck('nama', 'id')
            : collect();
        $kamarMap = $kamarIds !== []
            ? Kamar::query()->whereIn('id', array_unique($kamarIds))->pluck('nomor', 'id')
            : collect();

        $hariIni = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $hasil = [];

        foreach ($logs as $log) {
            $aktorNama = $log->pengguna?->name ?? 'Staf';
            $snap = is_array($log->sesudah) && $log->sesudah !== []
                ? $log->sesudah
                : (is_array($log->sebelum) ? $log->sebelum : []);

            $waktu = $log->created_at
                ? CarbonImmutable::parse($log->created_at)->setTimezone('Asia/Jakarta')
                : CarbonImmutable::now('Asia/Jakarta');
            $waktuLabel = $waktu->toDateString() === $hariIni
                ? 'Hari ini · '.$waktu->format('H:i')
                : $waktu->translatedFormat('d M, H:i');

            $penghuniId = null;
            $laundryId = null;
            $url = route('laundry.index');
            $tipe = 'activity';
            $judul = "Perubahan data ({$aktorNama})";
            $keterangan = '';

            if (str_starts_with($log->aksi, 'laundry_')) {
                $laundryId = $log->subjek_id ? (int) $log->subjek_id : null;
                $penghuniId = ! empty($snap['penghuni_id']) ? (int) $snap['penghuni_id'] : null;

                $judul = match ($log->aksi) {
                    'laundry_tambah' => "Laundry baru dicatat ({$aktorNama})",
                    'laundry_ubah' => "Catatan laundry diubah ({$aktorNama})",
                    'laundry_hapus' => "Catatan laundry dihapus ({$aktorNama})",
                    'laundry_pulihkan' => "Catatan laundry dipulihkan ({$aktorNama})",
                    'laundry_tetapkan_pemilik' => "Pemilik cucian ditetapkan ({$aktorNama})",
                    'laundry_lengkapi_harga' => "Harga laundry dilengkapi ({$aktorNama})",
                    default => "Aktivitas laundry ({$aktorNama})",
                };
                $tipe = $log->aksi === 'laundry_hapus' ? 'warn' : 'activity';
                $keterangan = $this->ringkasLaundry($log->aksi, $snap, $penghuniMap, $kamarMap);
                $url = $log->aksi === 'laundry_hapus'
                    ? route('laundry.terhapus')
                    : route('laundry.index');
            } elseif (str_starts_with($log->aksi, 'penghuni_')) {
                $penghuniId = $log->subjek_id ? (int) $log->subjek_id : null;
                $namaPenghuni = (string) ($snap['nama'] ?? ($penghuniId ? $penghuniMap->get($penghuniId) : null) ?? 'Penghuni');
                $kamarNomor = $snap['kamar'] ?? ($snap['kamar_tujuan'] ?? null);

                $judul = match ($log->aksi) {
                    'penghuni_masuk' => "Penghuni baru masuk ({$aktorNama})",
                    'penghuni_pindah_kamar' => "Penghuni pindah kamar ({$aktorNama})",
                    'penghuni_batalkan_pindah' => "Pindah kamar dibatalkan ({$aktorNama})",
                    'penghuni_keluar' => "Penghuni keluar ({$aktorNama})",
                    'penghuni_batalkan_keluar' => "Checkout dibatalkan ({$aktorNama})",
                    'penghuni_ganti_nomor' => "Nomor WA penghuni diubah ({$aktorNama})",
                    default => "Data penghuni diubah ({$aktorNama})",
                };
                $tipe = in_array($log->aksi, ['penghuni_keluar', 'penghuni_batalkan_pindah'], true) ? 'warn' : 'activity';
                $keterangan = $kamarNomor
                    ? "{$namaPenghuni} · Kamar {$kamarNomor}"
                    : $namaPenghuni;
                $url = $penghuniId ? route('penghuni.show', $penghuniId) : route('penghuni.index');
            } elseif (str_starts_with($log->aksi, 'kuota_')) {
                $penghuniId = $log->subjek_id ? (int) $log->subjek_id : null;
                $namaPenghuni = (string) (($penghuniId ? $penghuniMap->get($penghuniId) : null) ?? 'Penghuni');

                $judul = $log->aksi === 'kuota_simpan_penyesuaian'
                    ? "Tagihan kuota disesuaikan ({$aktorNama})"
                    : "Tagihan kuota direset otomatis ({$aktorNama})";
                $tipe = $log->aksi === 'kuota_simpan_penyesuaian' ? 'activity' : 'info';
                $nominal = isset($snap['nominal']) ? ' · Rp'.number_format((int) $snap['nominal'], 0, ',', '.') : '';
                $bulan = isset($snap['bulan']) ? " ({$snap['bulan']})" : '';
                $keterangan = "{$namaPenghuni}{$bulan}{$nominal}";
                $url = $penghuniId ? route('penghuni.show', $penghuniId) : route('laporan.index');
            } else {
                $tipe = 'info';
                $judul = match ($log->aksi) {
                    'pengaturan_aturan_ubah' => "Aturan tarif & kuota diubah ({$aktorNama})",
                    'pengaturan_izin_penjaga_ubah' => "Izin penjaga diubah ({$aktorNama})",
                    'pengaturan_template_wa_ubah' => "Template WhatsApp diubah ({$aktorNama})",
                    default => "Pengaturan diubah ({$aktorNama})",
                };
                $keterangan = $properti->nama;
                $url = route('pengaturan.index');
            }

            $hasil[] = [
                'id' => "audit_{$log->id}",
                'tipe' => $tipe,
                'judul' => $judul,
                'keterangan' => $keterangan,
                'waktu_label' => $waktuLabel,
                'timestamp_ms' => $waktu->getTimestampMs(),
                'aktor_id' => $log->user_id ? (int) $log->user_id : null,
                'penghuni_id' => $penghuniId,
                'laundry_id' => $laundryId,
                'url' => $url,
            ];
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  Collection<int, string>  $penghuniMap
     * @param  Collection<int, string>  $kamarMap
     */
    private function ringkasLaundry(string $aksi, array $snap, Collection $penghuniMap, Collection $kamarMap): string
    {
        if ($aksi === 'laundry_lengkapi_harga') {
            $jumlah = (int) ($snap['jumlah'] ?? 0);

            return "{$jumlah} catatan laundry dilengkapi harganya.";
        }

        $jenis = (string) ($snap['jenis'] ?? 'penghuni');
        $penghuniId = ! empty($snap['penghuni_id']) ? (int) $snap['penghuni_id'] : null;
        $kamarId = ! empty($snap['kamar_id']) ? (int) $snap['kamar_id'] : null;

        $label = match ($jenis) {
            'gedung' => 'Laundry Gedung',
            'tanpa_nama' => ! empty($snap['nama_tanpa_nama'])
                ? "Tanpa Nama · {$snap['nama_tanpa_nama']}"
                : 'Tanpa Nama',
            default => trim(
                ($kamarId && $kamarMap->has($kamarId) ? 'Kamar '.$kamarMap->get($kamarId).' · ' : '')
                .($penghuniId && $penghuniMap->has($penghuniId) ? $penghuniMap->get($penghuniId) : 'Penghuni')
            ),
        };

        $detail = [$label];
        if (! empty($snap['berat_kg'])) {
            $berat = str_replace('.', ',', rtrim(rtrim((string) $snap['berat_kg'], '0'), '.'));
            $detail[] = "{$berat} kg";
        }
        if (isset($snap['biaya_utama']) && (int) $snap['biaya_utama'] > 0) {
            $detail[] = 'Rp'.number_format((int) $snap['biaya_utama'], 0, ',', '.');
        }
        if (isset($snap['item_biaya']) && (int) $snap['item_biaya'] > 0) {
            $detail[] = '+Terpisah Rp'.number_format((int) $snap['item_biaya'], 0, ',', '.');
        }

        return implode(' · ', $detail);
    }
}
