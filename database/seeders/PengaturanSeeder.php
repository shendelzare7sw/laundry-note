<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use App\Models\Properti;
use Illuminate\Database\Seeder;

class PengaturanSeeder extends Seeder
{
    public const TANGGAL_BAWAAN = '2026-01-01';

    /**
     * Nilai awal aturan laundry dan template WhatsApp.
     *
     * @return array<string, string>
     */
    public static function nilaiBawaan(?string $namaProperti = null): array
    {
        return [
            'nama_properti' => $namaProperti ?? 'Laundry-Kos',
            'tarif_per_kg' => '8000',
            'min_kg' => '2',
            'kuota_kg' => '20',
            'ambang_mendekati_persen' => '75',
            'toleransi_kg' => '1',
            'pembulatan' => '1000',
            'mode_kuota' => 'penghuni',
            'kuota_prorata_bulan_masuk' => '0',
            'jam_edit_penjaga' => '24',
            'penjaga_kelola_kontrak' => '0',
            'template_wa_tagihan' => implode("\n", [
                'Halo *{nama}* (Kamar {kamar}),',
                '',
                'Rincian laundry {periode}:',
                '{rincian}',
                '',
                'Pemakaian: {kuota_terpakai}/{kuota} kg (kuota gratis)',
                '{tagihan_lewat}',
                '💰 *Total Tagihan: {total}*',
                '',
                'Silakan lakukan pembayaran melalui QRIS berikut dan kirim bukti pembayarannya.',
                'Terima kasih 🙏',
            ]),
            'template_wa_peringatan' => implode("\n", [
                'Halo *{nama}* (Kamar {kamar}),',
                '',
                'Informasi kuota laundry {periode}: pemakaian Anda sudah mencapai {kuota_terpakai}/{kuota} kg.',
                'Sisa kuota gratis bulan ini: {sisa} kg.',
            ]),
            'template_wa_rekap' => "*REKAP LAUNDRY {gedung} — {periode}*\n\n{rincian}",
        ];
    }

    public function run(): void
    {
        foreach (self::nilaiBawaan() as $kunci => $nilai) {
            Pengaturan::query()->updateOrCreate(
                [
                    'properti_id' => null,
                    'kunci' => $kunci,
                    'berlaku_mulai' => self::TANGGAL_BAWAAN,
                ],
                [
                    'nilai' => $nilai,
                ]
            );
        }

        $properti = Properti::query()->where('nama', 'Dbata Residence')->first();

        if ($properti === null) {
            return;
        }

        foreach (self::nilaiBawaan($properti->nama_properti_laporan) as $kunci => $nilai) {
            Pengaturan::query()->updateOrCreate(
                [
                    'properti_id' => $properti->id,
                    'kunci' => $kunci,
                    'berlaku_mulai' => self::TANGGAL_BAWAAN,
                ],
                [
                    'nilai' => $nilai,
                ]
            );
        }
    }
}
