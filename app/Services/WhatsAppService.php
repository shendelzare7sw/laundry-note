<?php

namespace App\Services;

use App\Models\Laundry;
use App\Models\Penghuni;
use App\Models\Properti;
use Carbon\CarbonImmutable;

class WhatsAppService
{
    public const BATAS_PANJANG_URL_WA = 1800;

    public function __construct(
        private readonly PengaturanService $pengaturanService,
        private readonly KuotaService $kuotaService,
        private readonly HargaService $hargaService,
    ) {}

    /**
     * Normalisasi nomor WhatsApp ke format 62... tanpa tanda tambah/spasi.
     */
    public function normalisasiNomor(?string $nomor): ?string
    {
        if ($nomor === null) {
            return null;
        }

        $hanyaDigit = preg_replace('/\D+/', '', trim($nomor)) ?? '';

        if ($hanyaDigit === '') {
            return null;
        }

        if (str_starts_with($hanyaDigit, '0')) {
            $hanyaDigit = '62'.substr($hanyaDigit, 1);
        } elseif (! str_starts_with($hanyaDigit, '62') && str_starts_with($hanyaDigit, '8')) {
            $hanyaDigit = '62'.$hanyaDigit;
        }

        if (strlen($hanyaDigit) < 9 || strlen($hanyaDigit) > 16) {
            return null;
        }

        return $hanyaDigit;
    }

    /**
     * Susun tautan wa.me ke nomor tertentu.
     */
    public function tautan(?string $nomor, string $pesan): ?string
    {
        $nomorBersih = $this->normalisasiNomor($nomor);

        if ($nomorBersih === null) {
            return null;
        }

        return 'https://wa.me/'.$nomorBersih.'?text='.rawurlencode($pesan);
    }

    /**
     * Susun tautan wa.me tanpa nomor (pilih kontak/grup) jika panjang pesan masih aman.
     */
    public function tautanTanpaNomor(string $pesan): ?string
    {
        if (mb_strlen($pesan) > self::BATAS_PANJANG_URL_WA) {
            return null;
        }

        return 'https://wa.me/?text='.rawurlencode($pesan);
    }

    public function formatRupiah(int $nominal): string
    {
        return 'Rp'.number_format($nominal, 0, ',', '.');
    }

    /**
     * Format satuan centi-kg menjadi string Indonesia minimal 1 angka desimal (mis. 20,0 atau 2,25).
     */
    public function formatKg(int $centiKg): string
    {
        $negatif = $centiKg < 0;
        $abs = abs($centiKg);
        $desimal = $abs % 10 === 0
            ? bcdiv((string) $abs, '100', 1)
            : bcdiv((string) $abs, '100', 2);

        return ($negatif ? '-' : '').str_replace('.', ',', $desimal);
    }

    /**
     * Ganti placeholder {variabel} pada template dan pastikan tidak menyisakan placeholder mentah.
     *
     * @param  array<string, string>  $variabel
     */
    public function renderTemplate(string $template, array $variabel): string
    {
        $ganti = [];

        foreach ($variabel as $kunci => $nilai) {
            $ganti['{'.$kunci.'}'] = $nilai;
        }

        $hasil = strtr($template, $ganti);
        $hasil = preg_replace('/\{[a-z0-9_]+\}/i', '', $hasil) ?? $hasil;
        $hasil = preg_replace("/[ \t]+\n/", "\n", $hasil) ?? $hasil;
        $hasil = preg_replace("/\n{3,}/", "\n\n", $hasil) ?? $hasil;

        return trim($hasil);
    }

    /**
     * Bangun pesan & tautan tagihan WhatsApp dari data kartu laporan penghuni.
     *
     * @param  array<string, mixed>  $dataKartu
     * @return array{pesan: string, nomor: ?string, url: ?string}
     */
    public function pesanTagihan(
        Properti $properti,
        Penghuni $penghuni,
        array $dataKartu,
        string $labelPeriode
    ): array {
        $template = (string) ($this->pengaturanService->nilai($properti, 'template_wa_tagihan') ?? '');
        $gedung = (string) ($this->pengaturanService->nilai($properti, 'nama_properti') ?? $properti->nama_properti_laporan);

        $barisRincian = [];

        foreach ($dataKartu['baris'] ?? [] as $baris) {
            $tanggalLabel = (string) ($baris['tanggal_label'] ?? '');

            if (($baris['tipe'] ?? 'utama') === 'item') {
                $namaItem = (string) ($baris['nama_item'] ?? 'Item terpisah');
                $biayaItem = (int) ($baris['biaya'] ?? 0);
                $barisRincian[] = "• {$tanggalLabel} ({$namaItem}): 1 item - ".$this->formatRupiah($biayaItem);

                continue;
            }

            $beratLabel = (string) ($baris['berat_label'] ?? '0,0');
            $biaya = $baris['biaya'] ?? null;
            $teksBiaya = $biaya !== null ? $this->formatRupiah((int) $biaya) : 'Belum diisi';
            $penandaLewat = ! empty($baris['merah']) ? ' [Lewat kuota]' : '';
            $barisRincian[] = "• {$tanggalLabel}: {$beratLabel} kg - {$teksBiaya}{$penandaLewat}";
        }

        $kuota = $dataKartu['kuota'] ?? [];
        $terpakaiCenti = (int) ($kuota['terpakai_centi'] ?? 0);
        $kuotaCenti = (int) ($kuota['kuota_centi'] ?? 2000);
        $sisaCenti = (int) ($kuota['sisa_centi'] ?? 0);
        $kelebihanCenti = (int) ($kuota['kelebihan_centi'] ?? 0);
        $tagihanLewat = (int) ($kuota['tagihan_lewat_kuota'] ?? 0);
        $bayarTerpisah = (int) ($dataKartu['bayar_terpisah'] ?? ($kuota['bayar_terpisah'] ?? 0));
        $totalTagihan = $tagihanLewat + $bayarTerpisah;
        $jumlahItem = (int) ($dataKartu['jumlah_item_terpisah'] ?? 0);

        $formatRpSpasi = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
        $formatKgRingkas = fn (int $centi): string => $this->hargaService->dariCentiKg($centi, true);

        // Hanya tampilkan komponen yang benar-benar ditagihkan (tagihan lewat kuota > 0 dan/atau bayar terpisah > 0).
        // Kelebihan di bawah toleransi (Rp0) dan biaya utama kuota gratis tidak dimasukkan ke tagihan WA.
        $blokTagihan = [];

        if ($tagihanLewat > 0) {
            $blokTagihan[] = implode("\n", [
                '📦 Lewat Kuota Laundry',
                'Kelebihan: '.$formatKgRingkas($kelebihanCenti).' kg',
                'Biaya: '.$formatRpSpasi($tagihanLewat),
            ]);
        }

        if ($bayarTerpisah > 0) {
            $barisTerpisah = ['🧺 Tagihan Terpisah'];
            if ($jumlahItem > 0) {
                $barisTerpisah[] = "Jumlah item: {$jumlahItem} item";
            }
            $barisTerpisah[] = 'Biaya: '.$formatRpSpasi($bayarTerpisah);
            $blokTagihan[] = implode("\n", $barisTerpisah);
        }

        $rincianTagihan = implode("\n\n", $blokTagihan);

        $pesan = $this->renderTemplate($template, [
            'nama' => $penghuni->nama,
            'kamar' => (string) ($dataKartu['label_kamar'] ?? '-'),
            'gedung' => $gedung,
            'periode' => $labelPeriode,
            'rincian' => $barisRincian !== [] ? implode("\n", $barisRincian) : '',
            'rincian_tagihan' => $rincianTagihan,
            'kuota_terpakai' => $this->formatKg($terpakaiCenti),
            'kuota' => $this->formatKg($kuotaCenti),
            'sisa' => $this->formatKg($sisaCenti),
            'lewat_kg' => $formatKgRingkas($kelebihanCenti),
            'tagihan_lewat' => $rincianTagihan,
            'terpisah' => $formatRpSpasi($bayarTerpisah),
            'total' => $formatRpSpasi($totalTagihan),
        ]);

        $nomor = $this->normalisasiNomor($penghuni->no_wa);

        return [
            'pesan' => $pesan,
            'nomor' => $nomor,
            'url' => $totalTagihan > 0 ? $this->tautan($nomor, $pesan) : null,
            'total_tagihan' => $totalTagihan,
        ];
    }

    /**
     * Bangun pesan & tautan tagihan WhatsApp langsung untuk seorang penghuni pada bulan tertentu.
     *
     * @return array{pesan: string, nomor: ?string, url: ?string}
     */
    public function pesanTagihanDariPenghuni(Properti $properti, Penghuni $penghuni, ?string $bulan = null): array
    {
        $rentang = $this->kuotaService->rentangBulan($bulan);
        $kuota = $this->kuotaService->statusBulan($penghuni, $rentang['bulan']);

        $catatan = Laundry::query()
            ->where('properti_id', $properti->id)
            ->where('jenis', 'penghuni')
            ->where('penghuni_id', $penghuni->id)
            ->whereBetween('tanggal', [$rentang['awal'], $rentang['akhir']])
            ->with(['item', 'kamar'])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $kumulatifCenti = 0;
        $baris = [];
        $jumlahItemTerpisah = 0;

        foreach ($catatan as $laundry) {
            $beratCenti = $this->hargaService->keCentiKg((string) $laundry->berat_kg);
            $kumulatifCenti += $beratCenti;
            $merah = $kuota['mode_kuota'] !== 'tanpa_kuota'
                && $kuota['kuota_centi'] > 0
                && $kumulatifCenti > $kuota['kuota_centi'];

            $tanggalLabel = CarbonImmutable::parse($laundry->tanggal, 'Asia/Jakarta')
                ->locale('id')
                ->translatedFormat('j M Y');

            if ($beratCenti > 0 || $laundry->biaya_utama !== null) {
                $baris[] = [
                    'tipe' => 'utama',
                    'tanggal_label' => $tanggalLabel,
                    'berat_label' => $this->formatKg($beratCenti),
                    'biaya' => $laundry->biaya_utama,
                    'merah' => $merah,
                ];
            }

            if ($laundry->item->isNotEmpty()) {
                foreach ($laundry->item as $item) {
                    $jumlahItemTerpisah++;
                    $baris[] = [
                        'tipe' => 'item',
                        'tanggal_label' => $tanggalLabel,
                        'nama_item' => $item->nama,
                        'biaya' => (int) $item->biaya,
                        'merah' => false,
                    ];
                }
            } elseif ($laundry->item_nama !== null && $laundry->item_nama !== '') {
                $jumlahItemTerpisah++;
                $baris[] = [
                    'tipe' => 'item',
                    'tanggal_label' => $tanggalLabel,
                    'nama_item' => $laundry->item_nama,
                    'biaya' => (int) ($laundry->item_biaya ?? 0),
                    'merah' => false,
                ];
            }
        }

        $kamarTerkini = $penghuni->penempatanAktif?->kamar
            ?? $penghuni->penempatan()->with('kamar')->orderByDesc('mulai')->orderByDesc('id')->first()?->kamar;

        return $this->pesanTagihan(
            properti: $properti,
            penghuni: $penghuni,
            dataKartu: [
                'label_kamar' => $kamarTerkini?->nomor ?? '-',
                'baris' => $baris,
                'kuota' => $kuota,
                'bayar_terpisah' => $kuota['bayar_terpisah'],
                'jumlah_item_terpisah' => $jumlahItemTerpisah,
            ],
            labelPeriode: $rentang['label'],
        );
    }

    /**
     * Bangun pesan & tautan peringatan kuota WhatsApp untuk penghuni (mendekati / lewat).
     *
     * @param  array<string, mixed>|null  $kuota
     * @return array{pesan: string, nomor: ?string, url: ?string}
     */
    public function pesanPeringatanKuota(
        Properti $properti,
        Penghuni $penghuni,
        ?array $kuota = null,
        ?string $bulan = null
    ): array {
        $kuotaData = $kuota ?? $this->kuotaService->statusBulan($penghuni, $bulan);
        $template = (string) ($this->pengaturanService->nilai($properti, 'template_wa_peringatan') ?? '');
        $gedung = (string) ($this->pengaturanService->nilai($properti, 'nama_properti') ?? $properti->nama_properti_laporan);

        $kamarTerkini = $penghuni->penempatanAktif?->kamar
            ?? $penghuni->penempatan()->with('kamar')->orderByDesc('mulai')->orderByDesc('id')->first()?->kamar;

        $terpakaiCenti = (int) ($kuotaData['terpakai_centi'] ?? 0);
        $kuotaCenti = (int) ($kuotaData['kuota_centi'] ?? 2000);
        $sisaCenti = (int) ($kuotaData['sisa_centi'] ?? 0);
        $kelebihanCenti = (int) ($kuotaData['kelebihan_centi'] ?? 0);
        $tagihanLewat = (int) ($kuotaData['tagihan_lewat_kuota'] ?? 0);
        $bayarTerpisah = (int) ($kuotaData['bayar_terpisah'] ?? 0);

        $pesan = $this->renderTemplate($template, [
            'nama' => $penghuni->nama,
            'kamar' => $kamarTerkini?->nomor ?? '-',
            'gedung' => $gedung,
            'periode' => (string) ($kuotaData['label_bulan'] ?? ''),
            'rincian' => '',
            'kuota_terpakai' => $this->formatKg($terpakaiCenti),
            'kuota' => $this->formatKg($kuotaCenti),
            'sisa' => $this->formatKg($sisaCenti),
            'lewat_kg' => $this->formatKg($kelebihanCenti),
            'tagihan_lewat' => $tagihanLewat > 0 ? $this->formatRupiah($tagihanLewat) : 'Rp0',
            'terpisah' => $this->formatRupiah($bayarTerpisah),
            'total' => $this->formatRupiah($tagihanLewat + $bayarTerpisah),
        ]);

        if (($kuotaData['status'] ?? 'normal') === 'lewat' && ! str_contains($template, '{lewat_kg}')) {
            $barisTambahan = 'Pemakaian sudah melewati kuota sebesar *'.$this->formatKg($kelebihanCenti).' kg*.';

            if ($tagihanLewat > 0) {
                $barisTambahan .= ' Estimasi tagihan lewat kuota: *'.$this->formatRupiah($tagihanLewat).'*.';
            }

            $pesan .= "\n".$barisTambahan;
        }

        $nomor = $this->normalisasiNomor($penghuni->no_wa);

        return [
            'pesan' => $pesan,
            'nomor' => $nomor,
            'url' => $this->tautan($nomor, $pesan),
        ];
    }

    /**
     * Bangun teks rekap seluruh gedung untuk disalin, dibagikan, atau dibuka via WhatsApp.
     *
     * @param  array<string, mixed>  $dataLaporan
     * @return array{pesan: string, url: ?string, terlalu_panjang: bool, jumlah_karakter: int}
     */
    public function pesanRekapGedung(Properti $properti, array $dataLaporan): array
    {
        $template = (string) ($this->pengaturanService->nilai($properti, 'template_wa_rekap') ?? "*REKAP LAUNDRY {gedung} — {periode}*\n\n{rincian}");
        $gedung = (string) ($dataLaporan['nama_properti'] ?? $properti->nama_properti_laporan);
        $periode = (string) ($dataLaporan['rentang']['label'] ?? '');

        $bagian = [];

        foreach ($dataLaporan['kartu_penghuni'] ?? [] as $kartu) {
            $statusKeluar = ($kartu['penghuni']->status ?? null) === 'keluar' ? ' [Keluar]' : '';
            $header = '*Kamar '.$kartu['label_kamar'].' - '.$kartu['penghuni']->nama.$statusKeluar.'* ('.$kartu['total_kg_label'].' kg)';
            $barisKartu = [$header];

            foreach ($kartu['baris'] as $baris) {
                if (($baris['tipe'] ?? 'utama') === 'item') {
                    $barisKartu[] = '  • '.$baris['tanggal_label'].' - '.$baris['nama_item'].': 1 item - '.$this->formatRupiah((int) $baris['biaya']);
                } else {
                    $biayaTeks = $baris['biaya'] !== null ? $this->formatRupiah((int) $baris['biaya']) : 'Belum diisi';
                    $tandaMerah = ! empty($baris['merah']) ? ' ⚠️' : '';
                    $barisKartu[] = '  • '.$baris['tanggal_label'].': '.$baris['berat_label'].' kg - '.$biayaTeks.$tandaMerah;
                }
            }

            $ringkas = '  Biaya utama: '.$this->formatRupiah((int) $kartu['biaya_utama']);

            if ((int) $kartu['tagihan_lewat_kuota'] > 0) {
                $ringkas .= ' | Lewat kuota: '.$this->formatRupiah((int) $kartu['tagihan_lewat_kuota']);
            }

            if ((int) $kartu['bayar_terpisah'] > 0) {
                $ringkas .= ' | Terpisah: '.$this->formatRupiah((int) $kartu['bayar_terpisah']);
            }

            $barisKartu[] = $ringkas;
            $bagian[] = implode("\n", $barisKartu);
        }

        foreach ($dataLaporan['kartu_lainnya'] ?? [] as $kartuLain) {
            $barisKartu = ['*'.$kartuLain['judul'].'* ('.$kartuLain['total_kg_label'].' kg)'];

            foreach ($kartuLain['baris'] as $baris) {
                if (($baris['tipe'] ?? 'utama') === 'item') {
                    $barisKartu[] = '  • '.$baris['tanggal_label'].' - '.$baris['nama_item'].': 1 item - '.$this->formatRupiah((int) $baris['biaya']);
                } else {
                    $biayaTeks = $baris['biaya'] !== null ? $this->formatRupiah((int) $baris['biaya']) : 'Belum diisi';
                    $barisKartu[] = '  • '.$baris['tanggal_label'].': '.$baris['berat_label'].' kg - '.$biayaTeks;
                }
            }

            $bagian[] = implode("\n", $barisKartu);
        }

        $ringkasan = $dataLaporan['ringkasan'] ?? [];
        $penutup = implode("\n", [
            '────────────────────',
            '*TOTAL KESELURUHAN*',
            'Total berat: '.($ringkasan['total_kg_label'] ?? '0,0').' kg ('.($ringkasan['jumlah_penghuni'] ?? 0).' penghuni)',
            'Biaya utama: '.$this->formatRupiah((int) ($ringkasan['total_biaya_utama'] ?? 0)),
            'Tagihan lewat kuota: '.$this->formatRupiah((int) ($ringkasan['total_tagihan_lewat'] ?? 0)),
            'Bayar terpisah: '.$this->formatRupiah((int) ($ringkasan['total_bayar_terpisah'] ?? 0)),
        ]);

        $rincianLengkap = $bagian !== []
            ? implode("\n\n", $bagian)."\n\n".$penutup
            : 'Belum ada catatan laundry pada periode ini.';

        $pesan = $this->renderTemplate($template, [
            'gedung' => $gedung,
            'periode' => $periode,
            'rincian' => $rincianLengkap,
            'total' => $this->formatRupiah((int) ($ringkasan['total_tagihan_lewat'] ?? 0) + (int) ($ringkasan['total_bayar_terpisah'] ?? 0)),
        ]);

        $url = $this->tautanTanpaNomor($pesan);

        return [
            'pesan' => $pesan,
            'url' => $url,
            'terlalu_panjang' => $url === null,
            'jumlah_karakter' => mb_strlen($pesan),
        ];
    }
}
