<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanLaundryRequest;
use App\Http\Requests\TetapkanPemilikRequest;
use App\Http\Resources\Api\V1\LaundryResource;
use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Services\LaundryQueryService;
use App\Services\LaundryService;
use App\Services\PengaturanService;
use App\Support\Tampil;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LaundryApiController extends Controller
{
    public function __construct(
        private readonly LaundryService $laundryService,
        private readonly LaundryQueryService $queryService,
        private readonly PengaturanService $pengaturanService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $properti = $this->propertiAktif();
        Gate::authorize('viewAny', [Laundry::class, $properti]);

        $filter = [
            'periode' => $request->string('periode', 'bulan_ini')->toString(),
            'dari' => $request->query('dari'),
            'sampai' => $request->query('sampai'),
            'cari' => $request->string('cari')->toString(),
            'jenis' => $request->string('jenis')->toString(),
        ];

        $langkah = 20;
        $hasil = $this->queryService->daftarDanRingkasan($properti, $filter, $langkah);
        $daftar = $hasil['daftar'];
        $metaBaris = $hasil['metaBaris'];

        $user = $request->user();
        $bolehLengkapi = $user->can('lengkapiHarga', [Laundry::class, $properti]);
        $bolehTerhapus = $user->can('lihatTerhapus', [Laundry::class, $properti]);

        $items = $daftar->getCollection()->map(
            fn (Laundry $baris): array => (new LaundryResource($baris, $metaBaris[$baris->id] ?? null))->resolve($request)
        )->values()->all();

        $ditampilkan = $daftar->count();
        $total = $daftar->total();

        return response()->json([
            'rentang' => $hasil['rentang'],
            'ringkasan' => $hasil['ringkasan'],
            'meta' => [
                'ditampilkan' => $ditampilkan,
                'total' => $total,
                'langkah' => $langkah,
                'ada_lagi' => $ditampilkan < $total && $ditampilkan < Tampil::MAKS,
                'tampil_berikutnya' => min(Tampil::MAKS, $ditampilkan + $langkah),
            ],
            'pratinjau_lengkapi_harga' => $bolehLengkapi
                ? $this->laundryService->pratinjauLengkapiHarga($properti)
                : ['jumlah' => 0, 'total' => 0, 'daftar' => []],
            'jumlah_terhapus' => $bolehTerhapus
                ? Laundry::onlyTrashed()->where('properti_id', $properti->id)->count()
                : 0,
            'data' => $items,
        ]);
    }

    public function infoKuota(Request $request): JsonResponse
    {
        $properti = $this->propertiAktif();
        Gate::authorize('create', [Laundry::class, $properti]);

        $hariIni = now('Asia/Jakarta')->toDateString();
        $tanggal = $this->normalisasiTanggal($request->query('tanggal'), $hariIni, $hariIni);

        $penghuniId = (int) $request->query('penghuni_id', 0);
        $penghuni = $penghuniId > 0
            ? Penghuni::query()->where('properti_id', $properti->id)->find($penghuniId)
            : null;

        $kecualiId = (int) $request->query('kecuali_id', 0);
        $kecuali = $kecualiId > 0
            ? Laundry::query()->where('properti_id', $properti->id)->find($kecualiId)
            : null;

        $berat = is_string($request->query('berat_kg')) ? $request->query('berat_kg') : null;
        $pratinjau = $this->laundryService->pratinjau($properti, $tanggal, $penghuni, $berat, $kecuali);
        $daftarPenghuni = $this->queryService->penghuniAktifPadaTanggal($properti, $tanggal, $penghuni?->id);

        return response()->json([
            ...$pratinjau,
            'min_kg_centi' => $this->pengaturanService->minKgCenti($properti, $tanggal),
            'daftar_penghuni' => $daftarPenghuni->values()->all(),
        ]);
    }

    public function store(SimpanLaundryRequest $request): JsonResponse
    {
        $properti = $this->propertiAktif();
        $laundry = $this->laundryService->catat($properti, $request->validated(), $request->user());
        $laundry->loadMissing(['penghuni', 'kamar', 'pencatat', 'item']);

        $meta = $this->queryService->penandaMerahDanKuotaBaris($properti, collect([$laundry]));

        return response()->json([
            'message' => 'Catatan laundry berhasil disimpan.',
            'data' => (new LaundryResource($laundry, $meta[$laundry->id] ?? null))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Laundry $laundry): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($laundry, $properti);
        Gate::authorize('viewAny', [Laundry::class, $properti]);

        $laundry->load(['penghuni', 'kamar', 'pencatat', 'item']);
        $meta = $this->queryService->penandaMerahDanKuotaBaris($properti, collect([$laundry]));

        return response()->json([
            'data' => (new LaundryResource($laundry, $meta[$laundry->id] ?? null))->resolve($request),
        ]);
    }

    public function update(SimpanLaundryRequest $request, Laundry $laundry): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($laundry, $properti);
        Gate::authorize('update', $laundry);

        $hasil = $this->laundryService->ubah($laundry, $request->validated(), $request->user());
        $hasil->loadMissing(['penghuni', 'kamar', 'pencatat', 'item']);
        $meta = $this->queryService->penandaMerahDanKuotaBaris($properti, collect([$hasil]));

        return response()->json([
            'message' => 'Catatan laundry berhasil diperbarui.',
            'data' => (new LaundryResource($hasil, $meta[$hasil->id] ?? null))->resolve($request),
        ]);
    }

    public function destroy(Request $request, Laundry $laundry): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($laundry, $properti);
        Gate::authorize('delete', $laundry);

        $this->laundryService->hapus($laundry, $request->user());

        return response()->json([
            'message' => 'Catatan laundry dihapus.',
            'boleh_pulihkan' => $request->user()->can('restore', $laundry),
        ]);
    }

    public function terhapus(Request $request): JsonResponse
    {
        $properti = $this->propertiAktif();
        Gate::authorize('lihatTerhapus', [Laundry::class, $properti]);

        $langkah = 15;
        $daftarTerhapus = Tampil::ambil(
            Laundry::onlyTrashed()
                ->where('properti_id', $properti->id)
                ->with(['penghuni', 'kamar', 'pencatat', 'item'])
                ->orderByDesc('deleted_at')
                ->orderByDesc('id'),
            $langkah,
        );

        $ditampilkan = $daftarTerhapus->count();
        $total = $daftarTerhapus->total();

        return response()->json([
            'meta' => [
                'ditampilkan' => $ditampilkan,
                'total' => $total,
                'langkah' => $langkah,
                'ada_lagi' => $ditampilkan < $total && $ditampilkan < Tampil::MAKS,
                'tampil_berikutnya' => min(Tampil::MAKS, $ditampilkan + $langkah),
            ],
            'data' => $daftarTerhapus->getCollection()->map(
                fn (Laundry $l): array => (new LaundryResource($l))->resolve($request)
            )->values()->all(),
        ]);
    }

    public function pulihkan(Request $request, int $laundryId): JsonResponse
    {
        $properti = $this->propertiAktif();
        $laundry = Laundry::onlyTrashed()->findOrFail($laundryId);
        $this->pastikanMilikPropertiAktif($laundry, $properti);
        Gate::authorize('restore', $laundry);

        $hasil = $this->laundryService->pulihkan($laundry, $request->user());
        $hasil->loadMissing(['penghuni', 'kamar', 'pencatat', 'item']);

        return response()->json([
            'message' => 'Catatan laundry berhasil dipulihkan.',
            'data' => (new LaundryResource($hasil))->resolve($request),
        ]);
    }

    public function tetapkanPemilik(TetapkanPemilikRequest $request, Laundry $laundry): JsonResponse
    {
        $properti = $this->propertiAktif();
        $this->pastikanMilikPropertiAktif($laundry, $properti);

        $penghuni = Penghuni::query()
            ->where('properti_id', $properti->id)
            ->findOrFail($request->integer('penghuni_id'));

        $hasil = $this->laundryService->tetapkanPemilik($laundry, $penghuni, $request->user());
        $hasil->loadMissing(['penghuni', 'kamar', 'pencatat', 'item']);

        return response()->json([
            'message' => "Catatan berhasil ditetapkan ke {$penghuni->nama}.",
            'data' => (new LaundryResource($hasil))->resolve($request),
        ]);
    }

    public function lengkapiHarga(Request $request): JsonResponse
    {
        $properti = $this->propertiAktif();
        Gate::authorize('lengkapiHarga', [Laundry::class, $properti]);

        $jumlah = $this->laundryService->lengkapiHarga($properti, $request->user());

        return response()->json([
            'jumlah_diperbarui' => $jumlah,
            'message' => $jumlah > 0
                ? "Biaya otomatis berhasil dilengkapi untuk {$jumlah} catatan laundry."
                : 'Semua catatan berberat sudah memiliki biaya utama.',
        ]);
    }

    private function normalisasiTanggal(mixed $input, string $default, string $maks): string
    {
        if (! is_string($input) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($input))) {
            return $default;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', trim($input), 'Asia/Jakarta')->toDateString();

            return $parsed > $maks ? $maks : $parsed;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function propertiAktif(): Properti
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');

        return $properti;
    }

    private function pastikanMilikPropertiAktif(Laundry $laundry, Properti $properti): void
    {
        abort_if($laundry->properti_id !== $properti->id, 404, 'Catatan laundry tidak ditemukan pada properti aktif.');
    }
}
