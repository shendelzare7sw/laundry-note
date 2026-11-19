<?php

namespace Database\Seeders;

use App\Models\Kamar;
use App\Models\Properti;
use Illuminate\Database\Seeder;

class KamarSeeder extends Seeder
{
    /**
     * Daftar rentang nomor kamar bawaan untuk Dbata Residence.
     *
     * @return array<int, array{lantai: int, dari: int, sampai: int}>
     */
    public static function daftarLantaiBawaan(): array
    {
        return [
            ['lantai' => 1, 'dari' => 101, 'sampai' => 109],
            ['lantai' => 2, 'dari' => 201, 'sampai' => 210],
            ['lantai' => 3, 'dari' => 301, 'sampai' => 310],
            ['lantai' => 4, 'dari' => 401, 'sampai' => 402],
        ];
    }

    public function run(): void
    {
        $properti = Properti::query()->where('nama', 'Dbata Residence')->first();

        if ($properti === null) {
            return;
        }

        foreach (self::daftarLantaiBawaan() as $rentang) {
            for ($nomor = $rentang['dari']; $nomor <= $rentang['sampai']; $nomor++) {
                Kamar::query()->updateOrCreate(
                    [
                        'properti_id' => $properti->id,
                        'nomor' => (string) $nomor,
                    ],
                    [
                        'lantai' => $rentang['lantai'],
                        'aktif' => true,
                    ]
                );
            }
        }
    }
}
