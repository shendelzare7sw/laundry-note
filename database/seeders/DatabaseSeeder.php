<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DevAdminSeeder::class,
            OwnerSeeder::class,
            PropertiSeeder::class,
            KamarSeeder::class,
            PengaturanSeeder::class,
        ]);
    }
}
