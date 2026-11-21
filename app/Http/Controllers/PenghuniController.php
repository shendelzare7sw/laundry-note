<?php

namespace App\Http\Controllers;

use App\Http\Requests\GantiNomorRequest;
use App\Http\Requests\KeluarPenghuniRequest;
use App\Http\Requests\MasukPenghuniRequest;
use App\Http\Requests\PenyesuaianKuotaRequest;
use App\Http\Requests\PindahKamarRequest;
use App\Http\Requests\UbahPenghuniRequest;
use App\Models\Kamar;
use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\KuotaService;
use App\Services\PenempatanService;
use App\Services\PengaturanService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PenghuniController extends Controller
{
    public function __construct(
        private readonly KuotaService $kuotaService,
        private readonly PenempatanService $penempatanService,
        private readonly PengaturanService $pengaturanService,
        private readonly WhatsAppService $waService,
    ) {}

    public function index(Request $request): View
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('viewAny', [Penghuni::class, $propertiAktif]);

        $tabInput = $request->string('tab')->toString();
        $tab = match ($tabInput) {
            'semua', 'booking', 'aktif', 'keluar' => $tabInput,
            'selesai' => 'keluar',
            default => 'semua',
        };
        $cari = trim($request->string('cari')->toString());
        $bulanInput = $request->string('bulan')->toString();
        $rentangBulan = $this->kuotaService->rentangBulan(
            preg_match('/^\d{4}-\d{2}$/', $bulanInput) ? $bulanInput : null
        );

        $hitunganStatus = Penghuni::query()
            ->where('properti_id', $propertiAktif->id)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $jumlahBooking = (int) ($hitunganStatus->get('booking') ?? 0);
        $jumlahAktif = (int) ($hitunganStatus->get('aktif') ?? 0);
        $jumlahKeluar = (int) ($hitunganStatus->get('keluar') ?? 0);

        $daftarPenghuni = Penghuni::query()
            ->where('properti_id', $propertiAktif->id)
            ->with([
                'penempatanAktif.kamar',
                'penempatan' => fn ($query) => $query->with('kamar')->orderByDesc('mulai')->orderByDesc('id'),
            ])
            ->when($tab !== 'semua', fn ($query) => $query->where('status', $tab))
            ->when($cari !== '', function ($query) use ($cari): void {
                $pola = '%'.$cari.'%';
                $query->where(function ($subQuery) use ($pola): void {
                    $subQuery->where('nama', 'like', $pola)
                        ->orWhere('alias', 'like', $pola)
                        ->orWhere('no_wa', 'like', $pola)
                        ->orWhereHas('penempatan.kamar', fn ($qKamar) => $qKamar->where('nomor', 'like', $pola));
                });
            })
            ->orderByRaw("CASE WHEN status = 'aktif' THEN 0 WHEN status = 'booking' THEN 1 ELSE 2 END")
            ->orderBy('nama')
            ->get()
            ->sortBy(function (Penghuni $penghuni): string {
                $kamarTerkini = $penghuni->penempatanAktif?->kamar
                    ?? $penghuni->penempatan->first()?->kamar;
                $nomor = $kamarTerkini?->nomor ?? 'ZZZ';
                $urutanStatus = match ($penghuni->status) {
                    'aktif' => '0-',
                    'booking' => '1-',
                    default => '2-',
                };

                return $urutanStatus.str_pad($nomor, 6, '0', STR_PAD_LEFT).'-'.$penghuni->nama;
            })
            ->values();

        $ringkasanKuota = $this->kuotaService->ringkasanSemua(
            properti: $propertiAktif,
            bulan: $rentangBulan['bulan'],
            penghuniIds: $daftarPenghuni->pluck('id')->all(),
        );

        $bolehKelolaKontrak = $request->user()->can('kelolaKontrak', [Penghuni::class, $propertiAktif]);

        $kamarKosong = $bolehKelolaKontrak
            ? Kamar::query()
                ->where('properti_id', $propertiAktif->id)
                ->where('aktif', true)
                ->whereDoesntHave('penempatanAktif')
                ->orderBy('lantai')
                ->orderBy('nomor')
                ->get()
            : collect();

        return view('penghuni.index', [
            'propertiAktif' => $propertiAktif,
            'daftarPenghuni' => $daftarPenghuni,
            'ringkasanKuota' => $ringkasanKuota,
            'rentangBulan' => $rentangBulan,
            'tab' => $tab,
            'cari' => $cari,
            'jumlahBooking' => $jumlahBooking,
            'jumlahAktif' => $jumlahAktif,
            'jumlahKeluar' => $jumlahKeluar,
            'jumlahSemua' => $jumlahBooking + $jumlahAktif + $jumlahKeluar,
            'kamarKosong' => $kamarKosong,
            'bolehKelolaKontrak' => $bolehKelolaKontrak,
            'penjagaBolehKontrak' => $this->pengaturanService->penjagaBolehKelolaKontrak($propertiAktif),
        ]);
    }

    public function store(MasukPenghuniRequest $request): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $hasil = $this->penempatanService->masuk($propertiAktif, $request->validated(), $request->user());

        $pesan = "Penghuni {$hasil['penghuni']->nama} berhasil didaftarkan.";
        $tipe = 'ok';

        if ($hasil['peringatan'] !== null) {
            $pesan .= ' '.$hasil['peringatan'];
            $tipe = 'warn';
        }

        return redirect()
            ->route('penghuni.show', $hasil['penghuni'])
            ->with('toast', ['tipe' => $tipe, 'pesan' => $pesan]);
    }

    public function show(Request $request, Penghuni $penghuni): View
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $propertiAktif);
        Gate::authorize('view', $penghuni);

        $penghuni->load([
            'penempatanAktif.kamar',
            'penempatan' => fn ($query) => $query->with(['kamar', 'pembuat'])->orderByDesc('mulai')->orderByDesc('id'),
        ]);

        $bulanInput = $request->string('bulan')->toString();
        $bulanAktif = preg_match('/^\d{4}-\d{2}$/', $bulanInput) ? $bulanInput : null;

        $kuotaBulanIni = $this->kuotaService->statusBulan($penghuni, $bulanAktif);
        $riwayatKuota = $this->kuotaService->riwayatBulanan($penghuni, 6, $kuotaBulanIni['bulan']);

        $catatanLaundry = Laundry::query()
            ->with(['kamar', 'item'])
            ->where('properti_id', $propertiAktif->id)
            ->where('penghuni_id', $penghuni->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $user = $request->user();
        $bolehKelolaKontrak = $user->can('kelolaKontrak', $penghuni);
        $bolehKelolaPenghuni = $user->can('kelolaPenghuni', $penghuni);
        $bolehSesuaikanKuota = $user->can('sesuaikanKuota', $penghuni);

        $kamarTersedia = $bolehKelolaKontrak
            ? Kamar::query()
                ->where('properti_id', $propertiAktif->id)
                ->where('aktif', true)
                ->whereDoesntHave('penempatanAktif')
                ->orderBy('lantai')
                ->orderBy('nomor')
                ->get()
            : collect();

        $penempatanTerbaru = $penghuni->penempatan->first();
        $bisaBatalkanPindah = $bolehKelolaKontrak
            && in_array($penghuni->status, ['aktif', 'booking'], true)
            && $penempatanTerbaru !== null
            && $penempatanTerbaru->selesai === null
            && $penempatanTerbaru->sebab === 'pindah'
            && $penghuni->penempatan->count() >= 2
            && ! Laundry::query()
                ->where('properti_id', $propertiAktif->id)
                ->where('penghuni_id', $penghuni->id)
                ->where('kamar_id', $penempatanTerbaru->kamar_id)
                ->whereDate('tanggal', '>=', $penempatanTerbaru->mulai->toDateString())
                ->exists();

        $waTagihan = $this->waService->pesanTagihanDariPenghuni($propertiAktif, $penghuni, $kuotaBulanIni['bulan']);
        $waPeringatan = in_array($kuotaBulanIni['status'], ['mendekati', 'lewat'], true)
            ? $this->waService->pesanPeringatanKuota($propertiAktif, $penghuni, $kuotaBulanIni, $kuotaBulanIni['bulan'])
            : null;

        return view('penghuni.show', [
            'propertiAktif' => $propertiAktif,
            'penghuni' => $penghuni,
            'kuotaBulanIni' => $kuotaBulanIni,
            'riwayatKuota' => $riwayatKuota,
            'catatanLaundry' => $catatanLaundry,
            'kamarTersedia' => $kamarTersedia,
            'bolehKelolaKontrak' => $bolehKelolaKontrak,
            'bolehKelolaPenghuni' => $bolehKelolaPenghuni,
            'bolehSesuaikanKuota' => $bolehSesuaikanKuota,
            'bisaBatalkanPindah' => $bisaBatalkanPindah,
            'penjagaBolehKontrak' => $this->pengaturanService->penjagaBolehKelolaKontrak($propertiAktif),
            'waTagihan' => $waTagihan,
            'waPeringatan' => $waPeringatan,
        ]);
    }

    public function pindahKamar(PindahKamarRequest $request, Penghuni $penghuni): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $propertiAktif);

        $kamarTujuan = Kamar::query()
            ->where('properti_id', $propertiAktif->id)
            ->findOrFail($request->integer('kamar_tujuan_id'));

        $penempatan = $this->penempatanService->pindahKamar(
            penghuni: $penghuni,
            kamarTujuan: $kamarTujuan,
            tanggalPindah: $request->string('tanggal_pindah')->toString(),
            aktor: $request->user(),
        );

        return redirect()
            ->route('penghuni.show', $penghuni)
            ->with('toast', [
                'tipe' => 'ok',
                'pesan' => "{$penghuni->nama} berhasil dipindahkan ke kamar {$penempatan->kamar?->nomor}. Kuota bulan ini tetap berlanjut.",
            ]);
    }

    public function batalkanPindah(Request $request, Penghuni $penghuni): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $propertiAktif);
        Gate::authorize('kelolaKontrak', $penghuni);

        $dipulihkan = $this->penempatanService->batalkanPindah($penghuni, $request->user());

        return redirect()
            ->route('penghuni.show', $penghuni)
            ->with('toast', [
                'tipe' => 'ok',
                'pesan' => "Perpindahan kamar dibatalkan. Penghuni kembali ke kamar {$dipulihkan->kamar?->nomor}.",
            ]);
    }

    public function keluar(KeluarPenghuniRequest $request, Penghuni $penghuni): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $propertiAktif);

        $this->penempatanService->keluar(
            penghuni: $penghuni,
            tanggalKeluar: $request->string('tanggal_keluar')->toString(),
            alasanKeluar: $request->input('alasan_keluar'),
            aktor: $request->user(),
        );

        return redirect()
            ->route('penghuni.show', $penghuni)
            ->with('toast', [
                'tipe' => 'ok',
                'pesan' => "{$penghuni->nama} ditandai keluar dan kamarnya kini kosong.",
            ]);
    }

    public function batalkanKeluar(Request $request, Penghuni $penghuni): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $propertiAktif);
        Gate::authorize('kelolaKontrak', $penghuni);

        $this->penempatanService->batalkanKeluar($penghuni, $request->user());

        return redirect()
            ->route('penghuni.show', $penghuni)
            ->with('toast', [
                'tipe' => 'ok',
                'pesan' => "Status keluar {$penghuni->nama} berhasil dibatalkan.",
            ]);
    }

    public function gantiNomor(GantiNomorRequest $request, Penghuni $penghuni): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $propertiAktif);

        $hasil = $this->penempatanService->gantiNomor(
            penghuni: $penghuni,
            noWaBaru: $request->input('no_wa'),
            aktor: $request->user(),
        );

        $pesan = "Nomor WhatsApp {$penghuni->nama} berhasil diperbarui.";
        $tipe = 'ok';

        if ($hasil['peringatan'] !== null) {
            $pesan .= ' '.$hasil['peringatan'];
            $tipe = 'warn';
        }

        return redirect()
            ->route('penghuni.show', $penghuni)
            ->with('toast', ['tipe' => $tipe, 'pesan' => $pesan]);
    }

    public function update(UbahPenghuniRequest $request, Penghuni $penghuni): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $propertiAktif);

        $this->penempatanService->ubahData($penghuni, $request->validated(), $request->user());

        return redirect()
            ->route('penghuni.show', $penghuni)
            ->with('toast', [
                'tipe' => 'ok',
                'pesan' => "Data penghuni {$penghuni->nama} berhasil diperbarui.",
            ]);
    }

    public function simpanPenyesuaianKuota(PenyesuaianKuotaRequest $request, Penghuni $penghuni): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $propertiAktif);

        $bulan = $request->string('bulan')->toString();
        $nominal = $request->boolean('reset') ? null : $request->integer('nominal');

        $this->kuotaService->simpanPenyesuaian(
            penghuni: $penghuni,
            bulan: $bulan,
            nominal: $nominal,
            catatan: $request->input('catatan'),
            aktor: $request->user(),
        );

        $pesan = $nominal === null
            ? "Penyesuaian manual bulan {$bulan} dikembalikan ke hitungan otomatis."
            : "Penyesuaian manual tagihan lewat kuota bulan {$bulan} berhasil disimpan.";

        if ($request->input('kembali_ke') === 'laporan') {
            return redirect()
                ->back()
                ->with('toast', ['tipe' => 'ok', 'pesan' => $pesan]);
        }

        return redirect()
            ->route('penghuni.show', ['penghuni' => $penghuni, 'bulan' => $bulan])
            ->with('toast', ['tipe' => 'ok', 'pesan' => $pesan]);
    }

    private function propertiAktif(): Properti
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        if (! $properti instanceof Properti) {
            abort(403, 'Properti aktif belum dipilih.');
        }

        return $properti;
    }

    private function pastikanMilikPropertiAktif(Penghuni $penghuni, Properti $propertiAktif): void
    {
        if ($penghuni->properti_id !== $propertiAktif->id) {
            abort(403, 'Anda tidak memiliki akses ke penghuni dari properti lain.');
        }
    }
}
