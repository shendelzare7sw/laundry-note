<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Pengaturan;
use App\Models\Properti;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_bersifat_idempoten(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('role', Peran::DevAdmin->value)->count());
        $this->assertSame(1, User::query()->where('role', Peran::Owner->value)->count());
        $this->assertSame(1, Properti::query()->where('nama', 'Dbata Residence')->count());

        $properti = Properti::query()->where('nama', 'Dbata Residence')->firstOrFail();

        // 31 kamar: 101-109 (9), 201-210 (10), 301-310 (10), 401-402 (2)
        $this->assertSame(31, Kamar::query()->untukProperti($properti)->count());
        $this->assertDatabaseHas('kamar', ['properti_id' => $properti->id, 'nomor' => '101', 'lantai' => 1]);
        $this->assertDatabaseHas('kamar', ['properti_id' => $properti->id, 'nomor' => '307', 'lantai' => 3]);
        $this->assertDatabaseHas('kamar', ['properti_id' => $properti->id, 'nomor' => '402', 'lantai' => 4]);

        // Pengaturan global + pengaturan Dbata Residence
        $this->assertSame(15, Pengaturan::query()->whereNull('properti_id')->count());
        $this->assertSame(15, Pengaturan::query()->untukProperti($properti)->count());
        $this->assertDatabaseHas('pengaturan', [
            'properti_id' => $properti->id,
            'kunci' => 'tarif_per_kg',
            'nilai' => '8000',
        ]);
        $this->assertDatabaseHas('pengaturan', [
            'properti_id' => $properti->id,
            'kunci' => 'penjaga_kelola_kontrak',
            'nilai' => '0',
        ]);
    }
}
