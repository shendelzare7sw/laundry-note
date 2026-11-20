<?php

namespace App\Services;

use InvalidArgumentException;

class HargaService
{
    /**
     * Konversi nilai kilogram menjadi satuan integer 0,01 kg (centi-kg) tanpa floating-point.
     */
    public function keCentiKg(int|string|float $beratKg): int
    {
        if (is_int($beratKg)) {
            return $beratKg * 100;
        }

        $teks = str_replace(',', '.', trim((string) $beratKg));

        if ($teks === '' || ! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $teks, $cocok)) {
            throw new InvalidArgumentException("Format berat kg tidak valid: {$beratKg}");
        }

        $negatif = $cocok[1] === '-';
        $bilBulat = (int) $cocok[2];
        $pecahan = $cocok[3] ?? '';
        $pecahanPadded = str_pad($pecahan, 3, '0');
        $duaDigit = (int) substr($pecahanPadded, 0, 2);
        $digitKetiga = (int) $pecahanPadded[2];

        $total = ($bilBulat * 100) + $duaDigit;

        if ($digitKetiga >= 5) {
            $total++;
        }

        return $negatif ? -$total : $total;
    }

    /**
     * Konversi satuan centi-kg (integer) menjadi representasi string desimal standar atau angka Indonesia.
     */
    public function dariCentiKg(int $centiKg, bool $komaIndonesia = false): string
    {
        $negatif = $centiKg < 0;
        $nilaiAbsolut = abs($centiKg);
        $desimal = bcdiv((string) $nilaiAbsolut, '100', 2);
        $ringkas = rtrim(rtrim($desimal, '0'), '.');

        if ($ringkas === '') {
            $ringkas = '0';
        }

        if ($komaIndonesia) {
            $ringkas = str_replace('.', ',', $ringkas);
        }

        return $negatif ? '-'.$ringkas : $ringkas;
    }

    /**
     * Hitung biaya utama laundry untuk satu catatan berat (minimal min_kg), dibulatkan ke rupiah terdekat.
     */
    public function hitungBiayaUtama(
        int|string|float $beratKg,
        int $tarifPerKg,
        int|string|float $minKg = 2
    ): int {
        $beratCenti = $this->keCentiKg($beratKg);

        if ($beratCenti <= 0) {
            return 0;
        }

        $minCenti = $this->keCentiKg($minKg);
        $beratDihitungCenti = max($beratCenti, $minCenti);

        return intdiv(($beratDihitungCenti * $tarifPerKg) + 50, 100);
    }

    /**
     * Bulatkan nominal rupiah secara half-up ke kelipatan pembulatan (mis. 1000).
     */
    public function bulatkanRupiah(int $nominal, int $kelipatan = 1000): int
    {
        if ($nominal <= 0) {
            return 0;
        }

        if ($kelipatan <= 1) {
            return $nominal;
        }

        return intdiv($nominal + intdiv($kelipatan, 2), $kelipatan) * $kelipatan;
    }

    /**
     * Hitung tagihan otomatis lewat kuota dari kelebihan centi-kg, tarif, toleransi, dan pembulatan.
     */
    public function hitungTagihanLewatKuota(
        int $kelebihanCenti,
        int $tarifPerKg,
        int $toleransiCenti,
        int $pembulatan = 1000
    ): int {
        if ($kelebihanCenti <= 0 || $kelebihanCenti < $toleransiCenti) {
            return 0;
        }

        $nominalMentah = intdiv(($kelebihanCenti * $tarifPerKg) + 50, 100);

        return $this->bulatkanRupiah($nominalMentah, $pembulatan);
    }
}
