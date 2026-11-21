<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanAturanRequest;
use App\Http\Requests\SimpanIzinPenjagaRequest;
use App\Http\Requests\SimpanTemplateWaRequest;
use App\Models\Pengaturan;
use App\Models\Properti;
use App\Services\HargaService;
use App\Services\PengaturanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PengaturanApiController extends Controller
{
    public function __construct(
        private readonly PengaturanService $pengaturanService,
        private readonly HargaService $hargaService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');
        $tanggal = $request->filled('tanggal')
            ? $request->string('tanggal')->toString()
            : now('Asia/Jakarta')->toDateString();

        $nilai = $this->pengaturanService->semuaPada($properti, $tanggal);
        $jamEditRaw = trim((string) ($nilai['jam_edit_penjaga'] ?? '24'));

        return response()->json([
            'tanggal_acuan' => $tanggal,
            'boleh_ubah' => $request->user()?->can('manage', [Pengaturan::class, $properti]) ?? false,
            'aturan' => [
                'nama_properti' => (string) ($nilai['nama_properti'] ?? $properti->nama_properti_laporan),
                'tarif_per_kg' => (int) ($nilai['tarif_per_kg'] ?? 8000),
                'min_kg' => (string) ($nilai['min_kg'] ?? '2'),
                'min_kg_centi' => $this->hargaService->keCentiKg($nilai['min_kg'] ?? '2'),
                'kuota_kg' => (string) ($nilai['kuota_kg'] ?? '20'),
                'kuota_kg_centi' => $this->hargaService->keCentiKg($nilai['kuota_kg'] ?? '20'),
                'ambang_mendekati_persen' => (int) ($nilai['ambang_mendekati_persen'] ?? 75),
                'toleransi_kg' => (string) ($nilai['toleransi_kg'] ?? '1'),
                'toleransi_kg_centi' => $this->hargaService->keCentiKg($nilai['toleransi_kg'] ?? '1'),
                'pembulatan' => (int) ($nilai['pembulatan'] ?? 1000),
                'mode_kuota' => (string) ($nilai['mode_kuota'] ?? 'penghuni'),
                'kuota_prorata_bulan_masuk' => ($nilai['kuota_prorata_bulan_masuk'] ?? '0') === '1',
            ],
            'izin_penjaga' => [
                'jam_edit_penjaga' => $jamEditRaw === '' ? null : (int) $jamEditRaw,
                'penjaga_kelola_kontrak' => ($nilai['penjaga_kelola_kontrak'] ?? '0') === '1',
                'penjaga_ubah_catatan_lain' => ($nilai['penjaga_ubah_catatan_lain'] ?? '0') === '1',
            ],
            'template_wa' => [
                'template_wa_tagihan' => (string) ($nilai['template_wa_tagihan'] ?? ''),
                'template_wa_peringatan' => (string) ($nilai['template_wa_peringatan'] ?? ''),
                'template_wa_rekap' => (string) ($nilai['template_wa_rekap'] ?? ''),
            ],
        ]);
    }

    public function simpanAturan(SimpanAturanRequest $request): JsonResponse
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');
        $data = $request->validated();

        $this->pengaturanService->simpanAturanBerversi(
            properti: $properti,
            nilaiBaru: [
                'nama_properti' => (string) $data['nama_properti'],
                'tarif_per_kg' => (string) $data['tarif_per_kg'],
                'min_kg' => (string) $data['min_kg'],
                'kuota_kg' => (string) $data['kuota_kg'],
                'ambang_mendekati_persen' => (string) $data['ambang_mendekati_persen'],
                'toleransi_kg' => (string) $data['toleransi_kg'],
                'pembulatan' => (string) $data['pembulatan'],
                'mode_kuota' => (string) $data['mode_kuota'],
                'kuota_prorata_bulan_masuk' => $request->boolean('kuota_prorata_bulan_masuk') ? '1' : '0',
            ],
            berlakuMulai: (string) $data['berlaku_mulai'],
            aktor: $request->user(),
        );

        return response()->json([
            'message' => 'Aturan tarif & kuota berhasil disimpan untuk tanggal berlaku '.$data['berlaku_mulai'].'.',
        ]);
    }

    public function simpanIzinPenjaga(SimpanIzinPenjagaRequest $request): JsonResponse
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');
        $data = $request->validated();
        $jamEdit = $request->filled('jam_edit_penjaga') ? (string) $data['jam_edit_penjaga'] : '';

        $this->pengaturanService->simpanIzinPenjaga(
            properti: $properti,
            nilaiBaru: [
                'jam_edit_penjaga' => $jamEdit,
                'penjaga_kelola_kontrak' => $request->boolean('penjaga_kelola_kontrak') ? '1' : '0',
                'penjaga_ubah_catatan_lain' => $request->boolean('penjaga_ubah_catatan_lain') ? '1' : '0',
            ],
            aktor: $request->user(),
        );

        return response()->json([
            'message' => 'Pengaturan izin & batas edit penjaga berhasil diperbarui.',
        ]);
    }

    public function simpanTemplateWa(SimpanTemplateWaRequest $request): JsonResponse
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');
        $data = $request->validated();

        $this->pengaturanService->simpanTemplateWa(
            properti: $properti,
            templates: [
                'template_wa_tagihan' => (string) $data['template_wa_tagihan'],
                'template_wa_peringatan' => (string) $data['template_wa_peringatan'],
                'template_wa_rekap' => (string) $data['template_wa_rekap'],
            ],
            aktor: $request->user(),
        );

        return response()->json([
            'message' => 'Template pesan WhatsApp berhasil disimpan.',
        ]);
    }

    public function resetTemplateWa(Request $request): JsonResponse
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');
        Gate::authorize('manage', [Pengaturan::class, $properti]);

        $kunci = $request->filled('kunci') ? $request->string('kunci')->toString() : null;
        $this->pengaturanService->resetTemplateWa($properti, $kunci, $request->user());

        return response()->json([
            'message' => 'Template pesan WhatsApp dikembalikan ke bawaan awal.',
        ]);
    }
}
