<?php

namespace Tests\Feature;

use App\Enums\Peran;
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
    }
}
