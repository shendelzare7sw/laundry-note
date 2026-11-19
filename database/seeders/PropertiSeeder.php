<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Database\Seeder;

class PropertiSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('role', Peran::Owner->value)->orderBy('id')->first();

        Properti::query()->updateOrCreate(
            ['nama' => 'Dbata Residence'],
            [
                'owner_id' => $owner?->id,
                'alamat' => null,
                'nama_properti_laporan' => 'Dbata Residence',
                'status' => 'aktif',
            ]
        );
    }
}
