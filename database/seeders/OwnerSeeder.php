<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $username = Str::lower((string) env('OWNER_USERNAME', 'owner'));
        $password = (string) env('OWNER_PASSWORD', '');

        if ($password === '') {
            $password = 'password123';
        }

        User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => (string) env('OWNER_NAME', 'Pemilik Kos'),
                'password' => $password,
                'role' => Peran::Owner,
                'properti_id' => null,
                'aktif' => true,
            ]
        );
    }
}
