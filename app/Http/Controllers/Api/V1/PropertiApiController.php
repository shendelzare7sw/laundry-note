<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Peran;
use App\Http\Controllers\Controller;
use App\Http\Requests\PropertiRequest;
use App\Http\Requests\WizardAturanRequest;
use App\Http\Requests\WizardKamarRequest;
use App\Http\Resources\Api\V1\KamarResource;
use App\Http\Resources\Api\V1\PropertiResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Kamar;
use App\Models\Pengaturan;
use App\Models\Properti;
use App\Models\User;
use App\Services\PropertiWizardService;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PropertiApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $daftar = match (true) {
            $user->isDevAdmin() => Properti::query()->with('adminAktif')->withCount(['kamar', 'penghuniAktif'])->orderBy('id')->get(),
            $user->isOwner() => $user->propertiDimiliki()->with('adminAktif')->withCount(['kamar', 'penghuniAktif'])->orderBy('id')->get(),
            $user->properti_id !== null => Properti::query()->whereKey($user->properti_id)->with('adminAktif')->withCount(['kamar', 'penghuniAktif'])->get(),
            default => collect(),
        };

        return response()->json([
            'boleh_tambah' => $user->can('create', Properti::class),
            'data' => PropertiResource::collection($daftar)->resolve($request),
        ]);
    }

    public function store(PropertiRequest $request, PropertiWizardService $wizard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ownerId = $user->isOwner()
            ? $user->id
            : User::query()->where('role', Peran::Owner->value)->value('id');

        $properti = Properti::query()->create([
            'owner_id' => $ownerId,
            'nama' => $request->string('nama')->toString(),
            'alamat' => $request->input('alamat'),
            'no_wa_admin' => $request->input('no_wa_admin'),
            'deskripsi' => $request->input('deskripsi'),
            'nama_properti_laporan' => $request->string('nama_properti_laporan')->toString(),
            'status' => $request->string('status')->toString(),
        ]);

        $wizard->salinPengaturanBawaan($properti, $user);
        $properti->load('adminAktif')->loadCount(['kamar', 'penghuniAktif']);

        return response()->json([
            'message' => "Properti \"{$properti->nama}\" berhasil ditambahkan.",
            'data' => (new PropertiResource($properti))->resolve($request),
        ], 201);
    }

    public function update(PropertiRequest $request, Properti $properti, PropertiWizardService $wizard): JsonResponse
    {
        $statusBaru = $request->string('status')->toString();

        $properti->update([
            'nama' => $request->string('nama')->toString(),
            'alamat' => $request->input('alamat'),
            'no_wa_admin' => $request->input('no_wa_admin'),
            'deskripsi' => $request->input('deskripsi'),
            'nama_properti_laporan' => $request->string('nama_properti_laporan')->toString(),
        ]);

        $wizard->ubahStatusProperti($properti, $statusBaru, $request->user(), false);
        $properti->refresh()->load('adminAktif')->loadCount(['kamar', 'penghuniAktif']);

        return response()->json([
            'message' => "Properti \"{$properti->nama}\" berhasil diperbarui.",
            'data' => (new PropertiResource($properti))->resolve($request),
        ]);
    }

    public function setupShow(Request $request, Properti $properti): JsonResponse
    {
        Gate::authorize('update', $properti);

        $properti->load([
            'kamar' => fn ($q) => $q->orderBy('lantai')->orderBy('nomor'),
            'adminAktif',
            'penjaga',
        ])->loadCount(['kamar', 'penghuniAktif']);

        $pengaturanTersimpan = Pengaturan::query()
            ->untukProperti($properti)
            ->orderByDesc('berlaku_mulai')
            ->get()
            ->unique('kunci')
            ->pluck('nilai', 'kunci')
            ->all();

        $aturan = array_merge(
            PengaturanSeeder::nilaiBawaan($properti->nama_properti_laporan),
            $pengaturanTersimpan
        );

        $kamarPerLantai = $properti->kamar
            ->groupBy('lantai')
            ->map(fn ($items, $lantai): array => [
                'lantai' => (int) $lantai,
                'jumlah' => $items->count(),
                'nomor' => $items->pluck('nomor')->values()->all(),
            ])
            ->values()
            ->all();

        return response()->json([
            'properti' => (new PropertiResource($properti))->resolve($request),
            'siap_kamar' => $properti->kamar->where('aktif', true)->isNotEmpty(),
            'siap_aturan' => $pengaturanTersimpan !== [],
            'siap_admin' => $properti->adminAktif !== null,
            'kamar_per_lantai' => $kamarPerLantai,
            'aturan' => [
                'tarif_per_kg' => (int) ($aturan['tarif_per_kg'] ?? 8000),
                'min_kg' => (string) ($aturan['min_kg'] ?? '2'),
                'kuota_kg' => (string) ($aturan['kuota_kg'] ?? '20'),
                'ambang_mendekati_persen' => (int) ($aturan['ambang_mendekati_persen'] ?? 75),
                'toleransi_kg' => (string) ($aturan['toleransi_kg'] ?? '1'),
                'pembulatan' => (int) ($aturan['pembulatan'] ?? 1000),
                'mode_kuota' => (string) ($aturan['mode_kuota'] ?? 'penghuni'),
                'jam_edit_penjaga' => (int) ($aturan['jam_edit_penjaga'] ?? 24),
                'penjaga_kelola_kontrak' => ($aturan['penjaga_kelola_kontrak'] ?? '0') === '1',
            ],
            'admin_aktif' => $properti->adminAktif ? (new UserResource($properti->adminAktif))->resolve($request) : null,
            'penjaga' => UserResource::collection($properti->penjaga)->resolve($request),
        ]);
    }

    public function setupKamar(WizardKamarRequest $request, Properti $properti, PropertiWizardService $wizard): JsonResponse
    {
        $jumlah = $wizard->generateKamarPerLantai(
            $properti,
            $request->validated('lantai'),
            $request->user()
        );

        return response()->json([
            'jumlah_dibuat' => $jumlah,
            'message' => "{$jumlah} kamar baru berhasil ditambahkan.",
        ]);
    }

    public function setupAturan(WizardAturanRequest $request, Properti $properti, PropertiWizardService $wizard): JsonResponse
    {
        $data = $request->validated();
        $data['penjaga_kelola_kontrak'] = $request->boolean('penjaga_kelola_kontrak') ? '1' : '0';

        $wizard->simpanAturan($properti, $data, $request->user());

        return response()->json([
            'message' => 'Aturan laundry properti berhasil disimpan.',
        ]);
    }

    public function setupAktifkan(Request $request, Properti $properti, PropertiWizardService $wizard): JsonResponse
    {
        Gate::authorize('update', $properti);

        $hasil = $wizard->aktifkanProperti($properti, $request->user());

        if (! $hasil['berhasil']) {
            return response()->json([
                'message' => $hasil['alasan'][0] ?? 'Properti belum memenuhi syarat aktivasi.',
                'errors' => ['aktivasi' => $hasil['alasan']],
            ], 422);
        }

        $properti->refresh()->load('adminAktif')->loadCount(['kamar', 'penghuniAktif']);

        return response()->json([
            'message' => "Properti \"{$properti->nama}\" sekarang aktif.",
            'data' => (new PropertiResource($properti))->resolve($request),
        ]);
    }

    public function kamar(Request $request): JsonResponse
    {
        /** @var Properti $properti */
        $properti = app('properti.aktif');
        $hanyaKosong = $request->boolean('kosong');

        $daftar = Kamar::query()
            ->where('properti_id', $properti->id)
            ->where('aktif', true)
            ->with('penempatanAktif.penghuni')
            ->when($hanyaKosong, fn ($q) => $q->whereDoesntHave('penempatanAktif'))
            ->orderBy('lantai')
            ->orderBy('nomor')
            ->get();

        return response()->json([
            'data' => KamarResource::collection($daftar)->resolve($request),
        ]);
    }
}
