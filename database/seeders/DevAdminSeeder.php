<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DevAdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = Str::lower((string) env('DEV_ADMIN_USERNAME', 'devadmin'));
        $password = (string) env('DEV_ADMIN_PASSWORD', '');

        if ($password === '') {
            $password = 'password123';
        }

        User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => (string) env('DEV_ADMIN_NAME', 'Developer Admin'),
                'password' => $password,
                'role' => Peran::DevAdmin,
                'properti_id' => null,
                'aktif' => true,
            ]
        );
    }
}
