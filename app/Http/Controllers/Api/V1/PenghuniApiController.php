<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GantiNomorRequest;
use App\Http\Requests\KeluarPenghuniRequest;
use App\Http\Requests\MasukPenghuniRequest;
use App\Http\Requests\PenyesuaianKuotaRequest;
use App\Http\Requests\PindahKamarRequest;
use App\Http\Requests\UbahPenghuniRequest;
use App\Http\Resources\Api\V1\LaundryResource;
use App\Http\Resources\Api\V1\PenempatanResource;
use App\Http\Resources\Api\V1\PenghuniResource;
use App\Models\Kamar;
use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\KuotaService;
use App\Services\PenempatanService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PenghuniApiController extends Controller
{
    public function __construct(
        private readonly KuotaService $kuotaService,
        private readonly PenempatanService $penempatanService,
        private readonly WhatsAppService $waService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $properti = $this->propertiAktif();
        Gate::authorize('viewAny', [Penghuni::class, $properti]);

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
            ->where('properti_id', $properti->id)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $jumlahBooking = (int) ($hitunganStatus->get('booking') ?? 0);
        $jumlahAktif = (int) ($hitunganStatus->get('aktif') ?? 0);
        $jumlahKeluar = (int) ($hitunganStatus->get('keluar') ?? 0);

        $daftarPenghuni = Penghuni::query()
            ->where('properti_id', $properti->id)
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
            ->get()
            ->sortBy(function (Penghuni $penghuni): string {
                $kamarTerkini = $penghuni->penempatanAktif?->kamar ?? $penghuni->penempatan->first()?->kamar;
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
            properti: $properti,
            bulan: $rentangBulan['bulan'],
            penghuniIds: $daftarPenghuni->pluck('id')->all(),
        );

        $data = $daftarPenghuni->map(
            fn (Penghuni $penghuni): array => (new PenghuniResource($penghuni, $ringkasanKuota->get($penghuni->id)))->resolve($request)
        )->values()->all();

        return response()->json([
            'tab' => $tab,
            'rentang_bulan' => $rentangBulan,
            'hitungan' => [
                'semua' => $jumlahBooking + $jumlahAktif + $jumlahKeluar,
                'booking' => $jumlahBooking,
                'aktif' => $jumlahAktif,
                'keluar' => $jumlahKeluar,
            ],
            'boleh_kelola_kontrak' => $request->user()->can('kelolaKontrak', [Penghuni::class, $properti]),
            'data' => $data,
        ]);
    }

    public function store(MasukPenghuniRequest $request): JsonResponse
    {
        $properti = $this->propertiAktif();
        $hasil = $this->penempatanService->masuk($properti, $request->validated(), $request->user());
        $penghuni = $hasil['penghuni']->load(['penempatanAktif.kamar', 'penempatan.kamar']);

        return response()->json([
            'message' => "Penghuni {$penghuni->nama} berhasil didaftarkan.",
            'peringatan' => $hasil['peringatan'],
            'data' => (new PenghuniResource($penghuni))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $properti);
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
            ->with(['kamar', 'item', 'pencatat'])
            ->where('properti_id', $properti->id)
            ->where('penghuni_id', $penghuni->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $user = $request->user();
        $bolehKelolaKontrak = $user->can('kelolaKontrak', $penghuni);
        $penempatanTerbaru = $penghuni->penempatan->first();
        $bisaBatalkanPindah = $bolehKelolaKontrak
            && in_array($penghuni->status, ['aktif', 'booking'], true)
            && $penempatanTerbaru !== null
            && $penempatanTerbaru->selesai === null
            && $penempatanTerbaru->sebab === 'pindah'
            && $penghuni->penempatan->count() >= 2
            && ! Laundry::query()
                ->where('properti_id', $properti->id)
                ->where('penghuni_id', $penghuni->id)
                ->where('kamar_id', $penempatanTerbaru->kamar_id)
                ->whereDate('tanggal', '>=', $penempatanTerbaru->mulai->toDateString())
                ->exists();

        $waTagihan = $this->waService->pesanTagihanDariPenghuni($properti, $penghuni, $kuotaBulanIni['bulan']);
        $waPeringatan = in_array($kuotaBulanIni['status'], ['mendekati', 'lewat'], true)
            ? $this->waService->pesanPeringatanKuota($properti, $penghuni, $kuotaBulanIni, $kuotaBulanIni['bulan'])
            : null;

        return response()->json([
            'penghuni' => (new PenghuniResource($penghuni, $kuotaBulanIni))->resolve($request),
            'penempatan' => PenempatanResource::collection($penghuni->penempatan)->resolve($request),
            'kuota_bulan_ini' => $kuotaBulanIni,
            'riwayat_kuota' => $riwayatKuota->values()->all(),
            'catatan_laundry' => $catatanLaundry->map(
                fn (Laundry $l): array => (new LaundryResource($l))->resolve($request)
            )->values()->all(),
            'wa_tagihan' => $waTagihan,
            'wa_peringatan' => $waPeringatan,
            'permissions' => [
                'boleh_kelola_kontrak' => $bolehKelolaKontrak,
                'boleh_kelola_penghuni' => $user->can('kelolaPenghuni', $penghuni),
                'boleh_sesuaikan_kuota' => $user->can('sesuaikanKuota', $penghuni),
                'bisa_batalkan_pindah' => $bisaBatalkanPindah,
            ],
        ]);
    }

    public function update(UbahPenghuniRequest $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $properti);

        $hasil = $this->penempatanService->ubahData($penghuni, $request->validated(), $request->user());
        $hasil->load(['penempatanAktif.kamar', 'penempatan.kamar']);

        return response()->json([
            'message' => "Data penghuni {$hasil->nama} berhasil diperbarui.",
            'data' => (new PenghuniResource($hasil))->resolve($request),
        ]);
    }

    public function pindahKamar(PindahKamarRequest $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $properti);

        $kamarTujuan = Kamar::query()
            ->where('properti_id', $properti->id)
            ->findOrFail($request->integer('kamar_tujuan_id'));

        $penempatan = $this->penempatanService->pindahKamar(
            penghuni: $penghuni,
            kamarTujuan: $kamarTujuan,
            tanggalPindah: $request->string('tanggal_pindah')->toString(),
            aktor: $request->user(),
        );

        return response()->json([
            'message' => "{$penghuni->nama} berhasil dipindahkan ke Kamar {$kamarTujuan->nomor}. Kuota bulan berjalan tetap utuh.",
            'penempatan' => (new PenempatanResource($penempatan->load('kamar')))->resolve($request),
        ]);
    }

    public function batalkanPindah(Request $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $properti);
        Gate::authorize('kelolaKontrak', $penghuni);

        $penempatanLama = $this->penempatanService->batalkanPindahKamar($penghuni, $request->user());

        return response()->json([
            'message' => "Perpindahan kamar {$penghuni->nama} berhasil dibatalkan.",
            'penempatan' => (new PenempatanResource($penempatanLama->load('kamar')))->resolve($request),
        ]);
    }

    public function keluar(KeluarPenghuniRequest $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $properti);

        $hasil = $this->penempatanService->keluar(
            penghuni: $penghuni,
            tanggalKeluar: $request->string('tanggal_keluar')->toString(),
            alasan: $request->input('alasan_keluar'),
            aktor: $request->user(),
        );

        return response()->json([
            'message' => "{$hasil->nama} berhasil dicatat keluar.",
            'data' => (new PenghuniResource($hasil->load(['penempatanAktif.kamar', 'penempatan.kamar'])))->resolve($request),
        ]);
    }

    public function batalkanKeluar(Request $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $properti);
        Gate::authorize('kelolaKontrak', $penghuni);

        $hasil = $this->penempatanService->batalkanKeluar($penghuni, $request->user());

        return response()->json([
            'message' => "Status keluar {$hasil->nama} berhasil dibatalkan.",
            'data' => (new PenghuniResource($hasil->load(['penempatanAktif.kamar', 'penempatan.kamar'])))->resolve($request),
        ]);
    }

    public function gantiNomor(GantiNomorRequest $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $properti);

        $hasil = $this->penempatanService->gantiNomor($penghuni, $request->input('no_wa'), $request->user());

        return response()->json([
            'message' => "Nomor WhatsApp {$penghuni->nama} berhasil diperbarui.",
            'peringatan' => $hasil['peringatan'],
            'data' => (new PenghuniResource($hasil['penghuni']->load(['penempatanAktif.kamar', 'penempatan.kamar'])))->resolve($request),
        ]);
    }

    public function simpanPenyesuaianKuota(PenyesuaianKuotaRequest $request, Penghuni $penghuni): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($penghuni, $properti);

        $bulan = $request->string('bulan')->toString();
        $nominal = $request->boolean('reset') ? null : $request->integer('nominal');

        $this->kuotaService->simpanPenyesuaian(
            penghuni: $penghuni,
            bulan: $bulan,
            nominal: $nominal,
            catatan: $request->input('catatan'),
            aktor: $request->user(),
        );

        $statusBaru = $this->kuotaService->statusBulan($penghuni, $bulan);

        return response()->json([
            'message' => $nominal === null
                ? "Penyesuaian manual bulan {$bulan} dikembalikan ke hitungan otomatis."
                : "Penyesuaian manual tagihan lewat kuota bulan {$bulan} berhasil disimpan.",
            'kuota' => $statusBaru,
        ]);
    }

    private function propertiAktif(): Properti
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');

        return $properti;
    }

    private function pastikanMilikPropertiAktif(Penghuni $penghuni, Properti $properti): void
    {
        abort_if($penghuni->properti_id !== $properti->id, 404, 'Penghuni tidak ditemukan pada properti aktif.');
    }
}
