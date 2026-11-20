<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimpanAturanRequest;
use App\Http\Requests\SimpanIzinPenjagaRequest;
use App\Http\Requests\SimpanTemplateWaRequest;
use App\Models\Pengaturan;
use App\Models\Properti;
use App\Services\PengaturanService;
use App\Support\Tampil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PengaturanController extends Controller
{
    public function __construct(
        private readonly PengaturanService $pengaturanService,
    ) {}

    public function index(Request $request): View
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('manage', [Pengaturan::class, $propertiAktif]);

        $tab = $request->string('tab')->toString();

        if (! in_array($tab, ['aturan', 'izin', 'whatsapp'], true)) {
            $tab = 'aturan';
        }

        $hariIni = now('Asia/Jakarta')->toDateString();
        $nilaiAktif = $this->pengaturanService->semuaPada($propertiAktif, $hariIni);

        $riwayatVersi = Tampil::ambil(
            Pengaturan::query()
                ->where('properti_id', $propertiAktif->id)
                ->whereIn('kunci', PengaturanService::KUNCI_ATURAN)
                ->with('pengubah')
                ->orderByDesc('berlaku_mulai')
                ->orderByDesc('updated_at')
                ->orderBy('kunci'),
            15,
        );

        return view('pengaturan.index', [
            'propertiAktif' => $propertiAktif,
            'tab' => $tab,
            'hariIni' => $hariIni,
            'nilaiAktif' => $nilaiAktif,
            'riwayatVersi' => $riwayatVersi,
        ]);
    }

    public function simpanAturan(SimpanAturanRequest $request): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $data = $request->validated();

        $this->pengaturanService->simpanAturanBerversi(
            properti: $propertiAktif,
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

        return redirect()
            ->route('pengaturan.index', ['tab' => 'aturan'])
            ->with('toast', ['tipe' => 'ok', 'pesan' => 'Aturan tarif & kuota berhasil disimpan untuk tanggal berlaku '.$data['berlaku_mulai'].'.']);
    }

    public function simpanIzinPenjaga(SimpanIzinPenjagaRequest $request): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $data = $request->validated();

        $this->pengaturanService->simpanIzinPenjaga(
            properti: $propertiAktif,
            nilaiBaru: [
                'jam_edit_penjaga' => (string) $data['jam_edit_penjaga'],
                'penjaga_kelola_kontrak' => $request->boolean('penjaga_kelola_kontrak') ? '1' : '0',
                'penjaga_ubah_catatan_lain' => $request->boolean('penjaga_ubah_catatan_lain') ? '1' : '0',
            ],
            aktor: $request->user(),
        );

        return redirect()
            ->route('pengaturan.index', ['tab' => 'izin'])
            ->with('toast', ['tipe' => 'ok', 'pesan' => 'Pengaturan izin & batas edit penjaga berhasil diperbarui.']);
    }

    public function simpanTemplateWa(SimpanTemplateWaRequest $request): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $data = $request->validated();

        $this->pengaturanService->simpanTemplateWa(
            properti: $propertiAktif,
            templates: [
                'template_wa_tagihan' => (string) $data['template_wa_tagihan'],
                'template_wa_peringatan' => (string) $data['template_wa_peringatan'],
                'template_wa_rekap' => (string) $data['template_wa_rekap'],
            ],
            aktor: $request->user(),
        );

        return redirect()
            ->route('pengaturan.index', ['tab' => 'whatsapp'])
            ->with('toast', ['tipe' => 'ok', 'pesan' => 'Template pesan WhatsApp berhasil disimpan.']);
    }

    public function resetTemplateWa(Request $request): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('manage', [Pengaturan::class, $propertiAktif]);

        $kunci = $request->filled('kunci') ? $request->string('kunci')->toString() : null;
        $this->pengaturanService->resetTemplateWa($propertiAktif, $kunci, $request->user());

        return redirect()
            ->route('pengaturan.index', ['tab' => 'whatsapp'])
            ->with('toast', ['tipe' => 'ok', 'pesan' => 'Template pesan WhatsApp dikembalikan ke bawaan awal.']);
    }

    private function propertiAktif(): Properti
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        abort_unless($properti instanceof Properti, 403, 'Silakan pilih properti aktif terlebih dahulu.');

        return $properti;
    }
}
