<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kamar;
use App\Models\Penempatan;
use App\Models\Pengaturan;
use App\Models\Penghuni;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenempatanDanKamarTest extends TestCase
{
    use RefreshDatabase;

    private function buatProperti(string $nama = 'Dbata Residence'): Properti
    {
        $owner = User::factory()->create([
            'username' => 'owner_'.uniqid(),
            'role' => Peran::Owner,
        ]);

        return Properti::query()->create([
            'owner_id' => $owner->id,
            'nama' => $nama,
            'nama_properti_laporan' => $nama,
            'status' => 'aktif',
        ]);
    }

    public function test_nomor_kamar_unik_per_properti_tetapi_boleh_sama_antar_properti(): void
    {
        $propertiA = $this->buatProperti('Properti A');
        $propertiB = $this->buatProperti('Properti B');

        Kamar::query()->create([
            'properti_id' => $propertiA->id,
            'nomor' => '101',
            'lantai' => 1,
        ]);

        // Nomor yang sama di properti berbeda diperbolehkan
        $kamarB = Kamar::query()->create([
            'properti_id' => $propertiB->id,
            'nomor' => '101',
            'lantai' => 1,
        ]);
        $this->assertDatabaseHas('kamar', ['id' => $kamarB->id, 'nomor' => '101']);

        // Nomor duplikat di properti yang sama ditolak DB
        $this->expectException(QueryException::class);
        Kamar::query()->create([
            'properti_id' => $propertiA->id,
            'nomor' => '101',
            'lantai' => 1,
        ]);
    }

    public function test_indeks_unik_menolak_dua_penempatan_terbuka_pada_kamar_atau_penghuni_sama(): void
    {
        $properti = $this->buatProperti();

        $kamar1 = Kamar::query()->create(['properti_id' => $properti->id, 'nomor' => '101', 'lantai' => 1]);
        $kamar2 = Kamar::query()->create(['properti_id' => $properti->id, 'nomor' => '102', 'lantai' => 1]);

        $penghuni1 = Penghuni::query()->create([
            'properti_id' => $properti->id,
            'nama' => 'Penghuni Satu',
            'tanggal_masuk' => '2026-11-01',
        ]);
        $penghuni2 = Penghuni::query()->create([
            'properti_id' => $properti->id,
            'nama' => 'Penghuni Dua',
            'tanggal_masuk' => '2026-11-01',
        ]);

        Penempatan::query()->create([
            'properti_id' => $properti->id,
            'penghuni_id' => $penghuni1->id,
            'kamar_id' => $kamar1->id,
            'mulai' => '2026-11-01',
            'selesai' => null,
            'sebab' => 'masuk',
        ]);

        // Dua penempatan terbuka di kamar yang sama ditolak DB
        $tolakKamar = false;
        try {
            Penempatan::query()->create([
                'properti_id' => $properti->id,
                'penghuni_id' => $penghuni2->id,
                'kamar_id' => $kamar1->id,
                'mulai' => '2026-11-05',
                'selesai' => null,
                'sebab' => 'masuk',
            ]);
        } catch (QueryException) {
            $tolakKamar = true;
        }
        $this->assertTrue($tolakKamar, 'Dua penempatan terbuka di kamar yang sama harus ditolak.');

        // Dua penempatan terbuka untuk penghuni yang sama ditolak DB
        $tolakPenghuni = false;
        try {
            Penempatan::query()->create([
                'properti_id' => $properti->id,
                'penghuni_id' => $penghuni1->id,
                'kamar_id' => $kamar2->id,
                'mulai' => '2026-11-05',
                'selesai' => null,
                'sebab' => 'pindah',
            ]);
        } catch (QueryException) {
            $tolakPenghuni = true;
        }
        $this->assertTrue($tolakPenghuni, 'Dua penempatan terbuka untuk penghuni yang sama harus ditolak.');
    }

    public function test_pengaturan_global_dengan_properti_id_null_tetap_unik(): void
    {
        Pengaturan::query()->create([
            'properti_id' => null,
            'kunci' => 'tarif_per_kg',
            'nilai' => '8000',
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->expectException(QueryException::class);

        Pengaturan::query()->create([
            'properti_id' => null,
            'kunci' => 'tarif_per_kg',
            'nilai' => '9000',
            'berlaku_mulai' => '2026-01-01',
        ]);
    }
}
