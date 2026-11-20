<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Laundry;
use App\Models\Penempatan;
use App\Models\Penghuni;
use App\Models\PenyesuaianLewatKuota;
use App\Models\Properti;
use App\Models\User;
use App\Services\HargaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LaundryNovember2026Seeder extends Seeder
{
    public function run(): void
    {
        $properti = Properti::query()
            ->whereIn('nama', ['Dbata Residence', "D'bata Residence"])
            ->first()
            ?? Properti::query()->orderBy('id')->first();

        if ($properti === null) {
            return;
        }

        $aktor = User::query()
            ->where('properti_id', $properti->id)
            ->where('role', Peran::Admin)
            ->first()
            ?? User::query()->where('role', Peran::Owner)->first()
            ?? User::query()->orderBy('id')->first();

        if ($aktor === null) {
            return;
        }

        $hargaService = app(HargaService::class);

        $penghuniByKamar = Penghuni::query()
            ->where('properti_id', $properti->id)
            ->with(['penempatanAktif.kamar', 'penempatan.kamar'])
            ->get()
            ->keyBy(fn (Penghuni $p): string => (string) ($p->penempatanAktif?->kamar?->nomor ?? $p->penempatan->first()?->kamar?->nomor ?? ''));

        $dataPenghuni = [
            '101' => [
                ['2026-11-06', '2.60', 20800, []],
                ['2026-11-10', '3.40', 27200, []],
                ['2026-11-13', '2.60', 20800, []],
                ['2026-11-17', '2.20', 17600, []],
            ],
            '102' => [
                ['2026-11-03', '2.00', 16000, []],
                ['2026-11-10', '3.10', 24800, []],
                ['2026-11-20', '2.00', 16000, []],
            ],
            '103' => [
                ['2026-11-03', '2.00', 16000, []],
                ['2026-11-13', '2.10', 16800, []],
                ['2026-11-17', '2.00', 16000, []],
            ],
            '104' => [
                ['2026-11-03', '6.40', 51200, []],
                ['2026-11-13', '5.00', 40000, []],
                ['2026-11-17', '2.30', 18400, []],
            ],
            '105' => [
                ['2026-11-03', '3.00', 24000, []],
                ['2026-11-10', '5.10', 40800, []],
                ['2026-11-13', '3.40', 27200, []],
                ['2026-11-17', '2.60', 20800, []],
            ],
            '106' => [
                ['2026-11-03', '2.00', 16000, []],
                ['2026-11-06', '2.00', 16000, []],
                ['2026-11-10', '2.00', 16000, []],
                ['2026-11-13', '2.00', 16000, []],
                ['2026-11-17', '2.00', 16000, []],
                ['2026-11-20', '2.30', null, []],
            ],
            '107' => [
                ['2026-11-03', '2.60', 20800, []],
                ['2026-11-06', '2.00', 16000, []],
                ['2026-11-10', '2.00', 16000, []],
                ['2026-11-13', '2.00', 16000, []],
                ['2026-11-17', '2.00', 16000, []],
                ['2026-11-20', '2.00', 16000, []],
            ],
            '108' => [
                ['2026-11-10', '11.90', 95200, []],
                ['2026-11-20', '6.70', null, []],
            ],
            '109' => [
                ['2026-11-03', '3.50', 28000, []],
                ['2026-11-10', '3.20', 25600, []],
                ['2026-11-17', '3.50', 28000, []],
            ],
            '201' => [
                ['2026-11-03', '3.60', 28800, []],
                ['2026-11-06', '2.00', 16000, []],
                ['2026-11-10', '3.90', 31200, []],
                ['2026-11-17', '3.00', 24000, []],
                ['2026-11-20', '2.60', null, []],
            ],
            '202' => [
                ['2026-11-06', '2.70', 21600, []],
                ['2026-11-10', '2.60', 20800, []],
                ['2026-11-13', '2.10', 16800, []],
            ],
            '203' => [
                ['2026-11-03', '4.40', 35200, []],
                ['2026-11-10', '2.40', 20000, [['nama' => 'Bed cover', 'biaya' => 19200]]],
            ],
            '204' => [
                ['2026-11-03', '2.00', 16000, []],
                ['2026-11-10', '3.10', null, []],
                ['2026-11-17', '2.50', 20000, []],
            ],
            '205' => [
                ['2026-11-03', '3.40', 27200, []],
                ['2026-11-10', '2.00', 16000, []],
                ['2026-11-17', '2.00', 16000, [['nama' => 'Bed cover', 'biaya' => 20000]]],
                ['2026-11-20', '2.00', 16000, []],
            ],
            '206' => [
                ['2026-11-03', '6.00', 48000, []],
                ['2026-11-20', '5.00', null, []],
            ],
            '207' => [
                ['2026-11-06', '2.10', 16800, []],
                ['2026-11-20', '4.50', null, []],
            ],
            '208' => [
                ['2026-11-03', '2.10', 16800, []],
                ['2026-11-20', '2.60', null, []],
            ],
            '209' => [
                ['2026-11-03', '2.00', 16000, []],
                ['2026-11-06', '2.00', 16000, []],
                ['2026-11-10', '2.00', 16000, []],
                ['2026-11-13', '2.00', 16000, []],
                ['2026-11-17', '2.00', 16000, []],
                ['2026-11-20', '2.00', 16000, []],
            ],
            '210' => [
                ['2026-11-03', '5.40', 43200, []],
                ['2026-11-10', '5.00', 40000, []],
                ['2026-11-17', '2.20', 17600, []],
            ],
            '301' => [
                ['2026-11-03', '2.00', 16000, []],
                ['2026-11-06', '2.00', 16000, []],
                ['2026-11-10', '2.00', 16000, []],
                ['2026-11-13', '2.10', 16800, [['nama' => 'Bed cover', 'biaya' => 20000]]],
                ['2026-11-17', '3.10', 24800, []],
                ['2026-11-20', '2.60', null, []],
            ],
            '302' => [
                ['2026-11-03', '7.80', 62400, []],
                ['2026-11-06', '4.40', 35200, []],
                ['2026-11-10', '2.80', 22400, [['nama' => 'Selimut', 'biaya' => 15000]]],
                ['2026-11-13', '2.80', 19200, []],
                ['2026-11-17', '3.20', 25600, []],
                ['2026-11-20', '2.70', null, []],
            ],
            '303' => [
                ['2026-11-03', '3.10', 24800, []],
                ['2026-11-06', '2.60', 20800, []],
                ['2026-11-10', '3.30', 26400, []],
                ['2026-11-13', '2.00', 16000, []],
                ['2026-11-17', '2.00', 16000, []],
                ['2026-11-20', '2.00', 16000, []],
            ],
            '304' => [
                ['2026-11-20', '2.00', 16000, []],
            ],
            '305' => [
                ['2026-11-10', '5.30', 42400, []],
                ['2026-11-17', '2.00', 16000, []],
            ],
            '306' => [
                ['2026-11-20', '2.00', 16000, [['nama' => 'Selimut', 'biaya' => 15000]]],
            ],
            '307' => [
                ['2026-11-13', '4.00', 32000, []],
                ['2026-11-17', '3.90', 31200, []],
            ],
            '309' => [
                ['2026-11-10', '5.60', 44800, []],
                ['2026-11-17', '6.30', 50400, []],
                ['2026-11-20', '2.60', null, []],
            ],
            '310' => [
                ['2026-11-03', '3.50', 28000, []],
                ['2026-11-06', '4.10', 32800, []],
                ['2026-11-10', '2.00', 16000, []],
                ['2026-11-17', '2.60', 20800, []],
            ],
            '401' => [
                ['2026-11-03', '4.30', 34400, []],
                ['2026-11-13', '3.80', 30400, []],
                ['2026-11-17', '5.30', 42400, []],
                ['2026-11-20', '2.00', 16000, []],
            ],
            '402' => [
                ['2026-11-03', '2.20', 17600, []],
                ['2026-11-06', '2.10', 16800, []],
                ['2026-11-10', '2.30', 18400, []],
                ['2026-11-13', '2.40', 19200, []],
                ['2026-11-17', '3.00', 24000, []],
                ['2026-11-20', '2.00', 16000, []],
            ],
        ];

        DB::transaction(function () use ($properti, $aktor, $hargaService, $penghuniByKamar, $dataPenghuni): void {
            foreach ($dataPenghuni as $nomorKamar => $daftarEntri) {
                /** @var Penghuni|null $penghuni */
                $penghuni = $penghuniByKamar->get($nomorKamar);

                if ($penghuni === null) {
                    continue;
                }

                foreach ($daftarEntri as [$tanggal, $beratKg, $biayaUtama, $daftarItem]) {
                    $penempatan = Penempatan::query()
                        ->where('penghuni_id', $penghuni->id)
                        ->whereDate('mulai', '<=', $tanggal)
                        ->where(fn ($q) => $q->whereNull('selesai')->orWhereDate('selesai', '>=', $tanggal))
                        ->orderByDesc('mulai')
                        ->first();

                    $kamarId = $penempatan?->kamar_id ?? $penghuni->penempatanAktif?->kamar_id;
                    $biayaOtomatis = $hargaService->hitungBiayaUtama($beratKg, 8000, '2');
                    $biayaManual = $biayaUtama !== null && $biayaUtama !== $biayaOtomatis;
                    $totalItemBiaya = (int) collect($daftarItem)->sum('biaya');

                    $ref = "seed-nov2026-{$nomorKamar}-{$tanggal}";

                    $laundry = Laundry::query()->updateOrCreate(
                        [
                            'properti_id' => $properti->id,
                            'ref' => $ref,
                        ],
                        [
                            'tanggal' => $tanggal,
                            'jenis' => 'penghuni',
                            'penghuni_id' => $penghuni->id,
                            'kamar_id' => $kamarId,
                            'berat_kg' => $beratKg,
                            'biaya_utama' => $biayaUtama,
                            'biaya_manual' => $biayaManual,
                            'item_biaya' => $totalItemBiaya,
                            'dicatat_oleh' => $aktor->id,
                        ]
                    );

                    $laundry->item()->delete();

                    foreach (array_values($daftarItem) as $urutan => $item) {
                        $laundry->item()->create([
                            'nama' => $item['nama'],
                            'jumlah' => 1,
                            'berat_kg' => null,
                            'biaya' => $item['biaya'],
                            'urutan' => $urutan,
                        ]);
                    }
                }
            }

            // GDG - Laundry Gedung (tidak dihitung kuota)
            Laundry::query()->updateOrCreate(
                [
                    'properti_id' => $properti->id,
                    'ref' => 'seed-nov2026-gdg-2026-11-13',
                ],
                [
                    'tanggal' => '2026-11-13',
                    'jenis' => 'gedung',
                    'penghuni_id' => null,
                    'kamar_id' => null,
                    'berat_kg' => '2.00',
                    'biaya_utama' => 16000,
                    'biaya_manual' => false,
                    'item_biaya' => 0,
                    'catatan' => 'Laundry Gedung',
                    'dicatat_oleh' => $aktor->id,
                ]
            );

            // Penyesuaian manual tagihan lewat kuota Glenn (302) November 2026 = Rp8.000
            $glenn = $penghuniByKamar->get('302');

            if ($glenn !== null) {
                PenyesuaianLewatKuota::query()->updateOrCreate(
                    [
                        'penghuni_id' => $glenn->id,
                        'bulan' => '2026-11',
                    ],
                    [
                        'properti_id' => $properti->id,
                        'nominal' => 8000,
                        'catatan' => 'Penyesuaian tagihan lewat kuota November 2026',
                        'oleh' => $aktor->id,
                    ]
                );
            }
        });
    }
}
