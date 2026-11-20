<?php

namespace App\Models;

use App\Models\Concerns\MilikProperti;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'properti_id',
    'nama',
    'alias',
    'no_wa',
    'status',
    'tanggal_masuk',
    'tanggal_masuk_perkiraan',
    'jangka_waktu_sewa',
    'tanggal_keluar_rencana',
    'tanggal_keluar',
    'alasan_keluar',
    'catatan',
])]
class Penghuni extends Model
{
    use HasFactory, MilikProperti, SoftDeletes;

    /**
     * @var array<string, string>
     */
    public const DAFTAR_JANGKA_WAKTU = [
        'tidak_tentu' => 'Tidak Tentu',
        'bulanan' => 'Bulanan',
        '3_bulan' => '3 Bulan',
        '6_bulan' => '6 Bulan',
        '1_tahun' => '1 Tahun',
    ];

    protected $table = 'penghuni';

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_masuk_perkiraan' => 'boolean',
            'tanggal_keluar_rencana' => 'date',
            'tanggal_keluar' => 'date',
        ];
    }

    public function labelJangkaWaktu(): string
    {
        return self::DAFTAR_JANGKA_WAKTU[$this->jangka_waktu_sewa ?? 'tidak_tentu'] ?? 'Tidak Tentu';
    }

    public static function hitungTanggalKeluarRencana(
        ?string $tanggalMasuk,
        string $jangkaWaktu,
        ?string $inputRencana = null
    ): ?string {
        $inputBersih = $inputRencana !== null ? trim($inputRencana) : '';

        if ($inputBersih !== '') {
            return CarbonImmutable::parse($inputBersih, 'Asia/Jakarta')->toDateString();
        }

        if ($tanggalMasuk === null || trim($tanggalMasuk) === '') {
            return null;
        }

        $tambahBulan = match ($jangkaWaktu) {
            'bulanan' => 1,
            '3_bulan' => 3,
            '6_bulan' => 6,
            '1_tahun' => 12,
            default => 0,
        };

        if ($tambahBulan <= 0) {
            return null;
        }

        return CarbonImmutable::parse($tanggalMasuk, 'Asia/Jakarta')
            ->addMonthsNoOverflow($tambahBulan)
            ->toDateString();
    }

    public function penempatan(): HasMany
    {
        return $this->hasMany(Penempatan::class, 'penghuni_id')->orderBy('mulai');
    }

    public function penempatanAktif(): HasOne
    {
        return $this->hasOne(Penempatan::class, 'penghuni_id')->whereNull('selesai');
    }

    public function laundry(): HasMany
    {
        return $this->hasMany(Laundry::class, 'penghuni_id');
    }

    public function penyesuaianLewatKuota(): HasMany
    {
        return $this->hasMany(PenyesuaianLewatKuota::class, 'penghuni_id');
    }
}
