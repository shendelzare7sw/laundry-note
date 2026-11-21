<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimpanLaundryRequest;
use App\Http\Requests\TetapkanPemilikRequest;
use App\Models\AuditLog;
use App\Models\Laundry;
use App\Models\LaundryItem;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\HargaService;
use App\Services\LaundryQueryService;
use App\Services\LaundryService;
use App\Services\PengaturanService;
use App\Support\Tampil;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LaundryController extends Controller
{
    public function __construct(
        private readonly LaundryService $laundryService,
        private readonly LaundryQueryService $queryService,
        private readonly PengaturanService $pengaturanService,
        private readonly HargaService $hargaService,
    ) {}

    public function index(Request $request): View
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('viewAny', [Laundry::class, $propertiAktif]);

        $filter = [
            'periode' => $request->string('periode')->toString() ?: 'bulan_ini',
            'dari' => $request->string('dari')->toString() ?: null,
            'sampai' => $request->string('sampai')->toString() ?: null,
            'cari' => trim($request->string('cari')->toString()),
            'jenis' => $request->string('jenis')->toString(),
        ];

        $hasil = $this->queryService->daftarDanRingkasan($propertiAktif, $filter);
        $peringatanKuota = $this->queryService->peringatanKuotaBulanIni($propertiAktif);
        $user = $request->user();

        $bolehLengkapiHarga = $user->can('lengkapiHarga', [Laundry::class, $propertiAktif]);
        $bolehLihatTerhapus = $user->can('lihatTerhapus', [Laundry::class, $propertiAktif]);

        $pratinjauLengkapiHarga = $bolehLengkapiHarga
            ? $this->laundryService->pratinjauLengkapiHarga($propertiAktif)
            : null;

        $jumlahTerhapus = $bolehLihatTerhapus
            ? Laundry::onlyTrashed()->where('properti_id', $propertiAktif->id)->count()
            : 0;

        $adaTanpaNama = $hasil['daftar']->getCollection()->contains('jenis', 'tanpa_nama');
        $daftarPenghuniAktif = $adaTanpaNama
            ? $this->queryService->penghuniAktifPadaTanggal($propertiAktif, now('Asia/Jakarta')->toDateString())
            : collect();

        return view('laundry.index', [
            'propertiAktif' => $propertiAktif,
            'filter' => $filter,
            'rentang' => $hasil['rentang'],
            'daftar' => $hasil['daftar'],
            'ringkasan' => $hasil['ringkasan'],
            'metaBaris' => $hasil['metaBaris'],
            'peringatanKuota' => $peringatanKuota,
            'bolehTambah' => $user->can('create', [Laundry::class, $propertiAktif]),
            'bolehLengkapiHarga' => $bolehLengkapiHarga,
            'bolehLihatTerhapus' => $bolehLihatTerhapus,
            'pratinjauLengkapiHarga' => $pratinjauLengkapiHarga,
            'jumlahTerhapus' => $jumlahTerhapus,
            'daftarPenghuniAktif' => $daftarPenghuniAktif,
        ]);
    }

    public function create(Request $request): View
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('create', [Laundry::class, $propertiAktif]);

        $hariIni = now('Asia/Jakarta')->toDateString();
        $tanggal = $this->normalisasiTanggal(old('tanggal', $request->query('tanggal')), $hariIni, $hariIni);

        $penghuniIdInput = (int) old('penghuni_id', $request->query('penghuni_id', 0));
        $penghuniTerpilihId = $penghuniIdInput > 0
            ? Penghuni::query()
                ->where('properti_id', $propertiAktif->id)
                ->where('status', '!=', 'booking')
                ->whereKey($penghuniIdInput)
                ->value('id')
            : null;

        $aturanSaatIni = $this->ringkasanAturan($propertiAktif->id, $tanggal);
        $daftarPenghuni = $this->queryService->penghuniAktifPadaTanggal($propertiAktif, $tanggal, $penghuniTerpilihId);

        return view('laundry.form', [
            'propertiAktif' => $propertiAktif,
            'laundry' => null,
            'tanggalAwal' => $tanggal,
            'maksTanggal' => $hariIni,
            'penghuniAwalId' => $penghuniTerpilihId,
            'ref' => (string) old('ref', (string) Str::uuid()),
            'aturanSaatIni' => $aturanSaatIni,
            'daftarPenghuni' => $daftarPenghuni,
            'daftarItemPilihan' => LaundryItem::PILIHAN_CEPAT,
            'maksItem' => LaundryItem::MAKS_PER_CATATAN,
            'riwayatAudit' => collect(),
        ]);
    }

    public function store(SimpanLaundryRequest $request): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->laundryService->catat($propertiAktif, $request->validated(), $request->user());

        return redirect()
            ->route('laundry.index')
            ->with('toast', ['tipe' => 'ok', 'pesan' => 'Catatan laundry berhasil disimpan.']);
    }

    public function edit(Request $request, Laundry $laundry): View
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($laundry, $propertiAktif);
        Gate::authorize('update', $laundry);

        $laundry->load(['item', 'penghuni', 'kamar', 'pencatat']);

        $hariIni = now('Asia/Jakarta')->toDateString();
        $tanggal = $this->normalisasiTanggal(old('tanggal'), $laundry->tanggal->toDateString(), $hariIni);
        $aturanSaatIni = $this->ringkasanAturan($propertiAktif->id, $tanggal);
        $daftarPenghuni = $this->queryService->penghuniAktifPadaTanggal($propertiAktif, $tanggal, $laundry->penghuni_id);

        $riwayatAudit = AuditLog::query()
            ->where('properti_id', $propertiAktif->id)
            ->where('subjek_type', Laundry::class)
            ->where('subjek_id', $laundry->id)
            ->with('pengguna')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('laundry.form', [
            'propertiAktif' => $propertiAktif,
            'laundry' => $laundry,
            'tanggalAwal' => $tanggal,
            'maksTanggal' => $hariIni,
            'penghuniAwalId' => $laundry->penghuni_id,
            'ref' => $laundry->ref ?? (string) Str::uuid(),
            'aturanSaatIni' => $aturanSaatIni,
            'daftarPenghuni' => $daftarPenghuni,
            'daftarItemPilihan' => LaundryItem::PILIHAN_CEPAT,
            'maksItem' => LaundryItem::MAKS_PER_CATATAN,
            'riwayatAudit' => $riwayatAudit,
        ]);
    }

    public function update(SimpanLaundryRequest $request, Laundry $laundry): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($laundry, $propertiAktif);

        $this->laundryService->ubah($laundry, $request->validated(), $request->user());

        return redirect()
            ->route('laundry.index')
            ->with('toast', ['tipe' => 'ok', 'pesan' => 'Catatan laundry berhasil diperbarui.']);
    }

    public function destroy(Request $request, Laundry $laundry): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($laundry, $propertiAktif);
        Gate::authorize('delete', $laundry);

        $this->laundryService->hapus($laundry, $request->user());

        $toast = ['tipe' => 'ok', 'pesan' => 'Catatan laundry dihapus.'];

        if ($request->user()->can('restore', $laundry)) {
            $toast['aksi_url'] = route('laundry.pulihkan', $laundry->id);
            $toast['aksi_label'] = 'Batalkan';
        }

        return redirect()
            ->route('laundry.index')
            ->with('toast', $toast);
    }

    public function terhapus(Request $request): View
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('lihatTerhapus', [Laundry::class, $propertiAktif]);

        $daftarTerhapus = Tampil::ambil(
            Laundry::onlyTrashed()
                ->where('properti_id', $propertiAktif->id)
                ->with(['penghuni', 'kamar', 'pencatat', 'item'])
                ->orderByDesc('deleted_at')
                ->orderByDesc('id'),
            15,
        );

        return view('laundry.terhapus', [
            'propertiAktif' => $propertiAktif,
            'daftarTerhapus' => $daftarTerhapus,
        ]);
    }

    public function pulihkan(Request $request, int $laundryId): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $laundry = Laundry::onlyTrashed()->findOrFail($laundryId);

        $this->pastikanMilikPropertiAktif($laundry, $propertiAktif);
        Gate::authorize('restore', $laundry);

        $this->laundryService->pulihkan($laundry, $request->user());

        $tujuan = str_contains((string) url()->previous(), '/terhapus')
            ? route('laundry.terhapus')
            : route('laundry.index');

        return redirect($tujuan)
            ->with('toast', ['tipe' => 'ok', 'pesan' => 'Catatan laundry berhasil dipulihkan.']);
    }

    public function tetapkanPemilik(TetapkanPemilikRequest $request, Laundry $laundry): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($laundry, $propertiAktif);

        $penghuni = Penghuni::query()
            ->where('properti_id', $propertiAktif->id)
            ->findOrFail($request->integer('penghuni_id'));

        $this->laundryService->tetapkanPemilik($laundry, $penghuni, $request->user());

        return redirect()
            ->route('laundry.index')
            ->with('toast', ['tipe' => 'ok', 'pesan' => "Catatan berhasil ditetapkan ke {$penghuni->nama}."]);
    }

    public function lengkapiHarga(Request $request): RedirectResponse
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('lengkapiHarga', [Laundry::class, $propertiAktif]);

        $jumlah = $this->laundryService->lengkapiHarga($propertiAktif, $request->user());

        $toast = $jumlah > 0
            ? ['tipe' => 'ok', 'pesan' => "Biaya otomatis berhasil dilengkapi untuk {$jumlah} catatan laundry."]
            : ['tipe' => 'warn', 'pesan' => 'Semua catatan berberat sudah memiliki biaya utama.'];

        return redirect()
            ->route('laundry.index')
            ->with('toast', $toast);
    }

    public function infoKuota(Request $request): JsonResponse
    {
        $propertiAktif = $this->propertiAktif();
        Gate::authorize('create', [Laundry::class, $propertiAktif]);

        $hariIni = now('Asia/Jakarta')->toDateString();
        $tanggal = $this->normalisasiTanggal($request->query('tanggal'), $hariIni, $hariIni);

        $penghuniId = (int) $request->query('penghuni_id', 0);
        $penghuni = $penghuniId > 0
            ? Penghuni::query()->where('properti_id', $propertiAktif->id)->find($penghuniId)
            : null;

        $kecualiId = (int) $request->query('kecuali_id', 0);
        $kecuali = $kecualiId > 0
            ? Laundry::query()->where('properti_id', $propertiAktif->id)->find($kecualiId)
            : null;

        $beratRaw = trim((string) $request->query('berat_kg', ''));
        $beratValid = preg_match('/^\d+(?:[.,]\d{1,2})?$/', $beratRaw) === 1 ? $beratRaw : null;

        $pratinjau = $this->laundryService->pratinjau($propertiAktif, $tanggal, $penghuni, $beratValid, $kecuali, $request->user());

        return response()->json([
            ...$pratinjau,
            'min_kg_centi' => $this->pengaturanService->minKgCenti($propertiAktif->id, $tanggal),
            'daftar_penghuni' => $this->queryService
                ->penghuniAktifPadaTanggal($propertiAktif, $tanggal, $penghuni?->id)
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array{tarif_per_kg: int, min_kg: string, min_kg_centi: int, kuota_kg: string, mode_kuota: string, ambang_mendekati_persen: int}
     */
    private function ringkasanAturan(int $propertiId, string $tanggal): array
    {
        $semua = $this->pengaturanService->semuaPada($propertiId, $tanggal);
        $minKg = (string) ($semua['min_kg'] ?? '2');

        return [
            'tarif_per_kg' => (int) ($semua['tarif_per_kg'] ?? 8000),
            'min_kg' => $minKg,
            'min_kg_centi' => $this->hargaService->keCentiKg($minKg),
            'kuota_kg' => (string) ($semua['kuota_kg'] ?? '20'),
            'mode_kuota' => (string) ($semua['mode_kuota'] ?? 'penghuni'),
            'ambang_mendekati_persen' => (int) ($semua['ambang_mendekati_persen'] ?? 75),
        ];
    }

    private function normalisasiTanggal(mixed $nilai, string $default, string $maks): string
    {
        if (! is_string($nilai)) {
            return $default;
        }

        $bersih = trim($nilai);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $bersih)) {
            return $default;
        }

        $tgl = CarbonImmutable::createFromFormat('!Y-m-d', $bersih, 'Asia/Jakarta');

        if ($tgl === false || $tgl->format('Y-m-d') !== $bersih || $bersih > $maks) {
            return $default;
        }

        return $bersih;
    }

    private function propertiAktif(): Properti
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        if (! $properti instanceof Properti) {
            abort(403, 'Properti aktif belum dipilih.');
        }

        return $properti;
    }

    private function pastikanMilikPropertiAktif(Laundry $laundry, Properti $propertiAktif): void
    {
        if ($laundry->properti_id !== $propertiAktif->id) {
            abort(403, 'Anda tidak memiliki akses ke catatan laundry dari properti lain.');
        }
    }
}
