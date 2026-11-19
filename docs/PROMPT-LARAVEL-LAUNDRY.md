# Prompt Agent — Aplikasi Web Catatan Laundry (Laravel)

> [!IMPORTANT]
> Beberapa isi prompt di bawah sudah diubah oleh keputusan di `docs/KEPUTUSAN.md` (multi-properti, 4 peran, Tailwind v4, PHPUnit + MySQL, Fase 1 dipecah 1a/1b). Bila bertentangan, **`docs/KEPUTUSAN.md` yang berlaku**.

Letakkan file berikut di folder `docs/` repo (jangan ubah namanya):
`RANCANGAN-LAUNDRY-WEB.md` (acuan utama).
Dua file data berisi **data pribadi nyata** (nama, nomor WhatsApp): simpan di `storage/app/seed/` dan **masukkan ke `.gitignore`**; jangan di-commit, terutama jika repo di GitHub.
`seed_penghuni_aktif.csv` · `seed_laundry_nov_uji.csv`

> **Hemat konteks:** jangan minta agent membaca seluruh rancangan sekaligus. Tiap prompt di bawah menyebut bagian yang perlu dibaca.
> Daftar bagian: `grep -n '^## \|^### ' docs/RANCANGAN-LAUNDRY-WEB.md`.

Kerjakan **satu fase per sesi**. Setiap sesi dimulai dengan rencana dan menunggu persetujuanmu.

---

## Prompt dasar (awali setiap sesi dengan ini)

```
Kamu pengembang senior Laravel yang membantu membangun "Aplikasi Web Catatan Laundry" untuk kos (internal).
Acuan utama: docs/RANCANGAN-LAUNDRY-WEB.md. Baca hanya bagian yang kusebut di prompt fase.

Stack tetap: Laravel (versi stabil terbaru yang didukung PHP 8.3+), MySQL 8, Blade + Tailwind CSS + Alpine.js
(Laravel Breeze stack Blade). Tanpa Livewire, Inertia, atau paket tambahan tanpa izinku.
Lingkungan dev: Windows 11 + Laragon + VS Code. Deploy nanti ke VPS Linux.

Aturan:
- Ikuti nama tabel, kolom, dan istilah di rancangan (bahasa Indonesia). Jangan mengganti skema tanpa bertanya.
- Controller tipis; logika di app/Services; validasi di Form Request; hak akses di Policy.
- Uang integer rupiah; tanggal laundry/sewa bertipe DATE; zona waktu Asia/Jakarta.
- Kuota SELALU dihitung per penghuni per bulan kalender, tidak pernah difilter per kamar.
- Tulis tes (Pest atau PHPUnit) dengan database MySQL sungguhan. Jalankan tes sebelum menyatakan selesai.
- Jangan menyimpan rahasia di repo; sediakan .env.example. Data seed pribadi tidak boleh di-commit.
- Jangan mengerjakan fitur di luar fase. Jika ada celah atau pertentangan di rancangan, catat dan tanya.
- Saya cukup mengerti Laravel; beri alasan singkat untuk keputusan non-trivial.

Cara kerja tiap sesi:
1) RENCANA dulu tanpa kode: ringkasan pemahaman (maks 10 baris), asumsi dan pertanyaan, daftar file yang akan dibuat/diubah,
   daftar tes. Berhenti dan tunggu persetujuanku.
2) IMPLEMENTASI dalam commit kecil. Di akhir: cara menjalankan, hasil tes, dan hal yang perlu kuverifikasi manual.
```

---

## Fase 1 — Fondasi

```
Fase 1 (Fondasi). Baca bagian 1, 6, 7 (struktur), 8 (tema), dan 3.7 (peran) dari docs/RANCANGAN-LAUNDRY-WEB.md.

Kerjakan HANYA:
1. Proyek Laravel + Breeze (Blade), registrasi publik DIMATIKAN; login oleh akun yang dibuat admin.
2. Tema Tailwind: token warna bagian 8.1 di tailwind.config.js, font Roboto (paket npm lokal), komponen dasar
   <x-kartu>, <x-lencana>, <x-batang>, <x-ringkasan-tile>, <x-modal> (Alpine), <x-toast>, layout dengan bottom bar di HP
   dan sidebar di desktop (menu: Beranda, Laundry, Penghuni, Laporan, Lainnya). Halaman-halamannya boleh masih kerangka.
3. Migrasi semua tabel inti di bagian 6: users (role admin/penjaga, aktif), kamar, penghuni, penempatan (dengan dua kolom
   turunan storedAs + indeks unik), laundry, penyesuaian_lewat_kuota, pengaturan, audit_log.
4. Seeder: admin awal (dari .env), kamar 101-109, 201-210, 301-310, 401-402, dan nilai awal pengaturan (bagian 3.6 dan 4).
5. Artisan laundry:impor-seed untuk seed_penghuni_aktif.csv (penghuni + penempatan, termasuk penempatan sebelumnya
   "kamar|mulai|selesai"), idempoten dan mendukung --dry-run.
6. Policy peran: admin dan penjaga (aturan bagian 3.7).
Tes: login dan peran, indeks unik penempatan (dua penempatan terbuka pada kamar yang sama ditolak),
impor seed idempoten.
```

---

## Fase 2 — Penghuni, penempatan, kuota

```
Fase 2 (Penghuni dan kuota). Baca bagian 3.3, 4, 5, 6, 7 (services), 11 dari docs/RANCANGAN-LAUNDRY-WEB.md.
Fase 1 sudah selesai; baca kodenya dulu.

Kerjakan HANYA:
1. PengaturanService (nilai berversi dengan tanggal berlaku, di-cache) dan HargaService.
2. KuotaService: statusBulan(penghuni, 'YYYY-MM') dan ringkasanSemua('YYYY-MM') dengan satu query GROUP BY
   (tanpa N+1). Status normal/mendekati/lewat, tagihan lewat kuota (kelebihan x tarif, bulatkan, toleransi),
   override dari penyesuaian_lewat_kuota. Gunakan perhitungan desimal yang aman (bukan float).
3. PenempatanService: masuk, pindahKamar, keluar, batalkanKeluar. Semua dalam transaksi dengan lockForUpdate.
   Aturan pindah kamar ada di bagian 5: kuota TIDAK berubah, kamar tujuan harus kosong pada tanggal efektif,
   penempatan lama ditutup (tanggal pindah - 1 hari), batalkan hanya bila belum ada catatan di kamar baru.
4. Halaman Penghuni: daftar (tab Aktif/Keluar/Semua, tombol wa.me, mini-bar kuota), detail (riwayat kamar, kuota per bulan),
   dan aksi Masuk, Pindah Kamar (dengan pratinjau kuota), Keluar, Batalkan Keluar, Ganti Nomor (normalisasi ke 62...,
   peringatan panjang tidak lazim). Hanya admin.
5. audit_log untuk semua aksi di atas (observer atau service).
Tes wajib (angka dari bagian 4 dan 5): 23,0 kg -> Rp24.000; 25,3 kg -> Rp42.000; 20,5 kg -> tidak ditagih; 22,6 kg -> otomatis
Rp21.000 lalu override Rp20.800; 20,0 kg -> mendekati; pindah kamar 11,0 kg + 13,0 kg = 24,0 kg tanpa reset; pindah ke kamar
terisi ditolak; keluar menolak laundry baru sesudah tanggal keluar; normalisasi nomor 08..., 62..., +62....
```

---

## Fase 3 — Laundry (CRUD)

```
Fase 3 (Laundry). Baca bagian 3.1, 3.2, 4, 6, 7, 11 dari docs/RANCANGAN-LAUNDRY-WEB.md. Fase 1-2 sudah selesai; baca kodenya dulu.

Kerjakan HANYA:
1. Daftar laundry: filter periode (hari ini, 7 hari, bulan ini, bulan lalu, semua, dari-sampai), pencarian, kartu ringkasan,
   banner peringatan kuota, baris catatan dengan ubah/hapus.
2. Form tambah/ubah (Alpine): jenis penghuni/gedung/tanpa nama, pemilihan kamar (hanya penghuni aktif pada tanggal itu),
   tanggal, berat, biaya otomatis (bisa di-override), item terpisah (chip Selimut/Sprei/Bed cover/Lainnya), catatan,
   teks bantu kuota langsung lewat GET /laundry/kuota. Kamar saat dicatat diisi otomatis dari penempatan pada tanggal itu.
3. Validasi: berat > 0 dan <= 30 (lebih besar perlu konfirmasi), tanggal dalam masa tinggal penghuni, ref idempotensi opsional.
4. Hapus lunak dengan toast Batalkan, halaman Terhapus untuk memulihkan, riwayat perubahan per catatan.
5. Tombol admin "Lengkapi harga kosong": pratinjau jumlah dan nilai, lalu simpan; hanya untuk catatan biaya kosong/0.
6. Tetapkan catatan "tanpa nama" ke penghuni.
7. Artisan laundry:impor-seed --laundry untuk seed_laundry_nov_uji.csv (kamar dan penghuni dicocokkan; baris tanpa harga dibiarkan kosong).
8. Beranda: kartu peringatan kuota dan ringkasan bulan ini.
Tes: biaya 2,0 kg dan 1,5 kg = Rp16.000; override tidak tertimpa; item terpisah tidak masuk kuota; penjaga hanya bisa mengubah
miliknya dalam batas jam; setelah impor uji, total November = 278,9 kg (94 baris) dan "Lengkapi harga kosong" mendeteksi 25 catatan.
```

---

## Fase 4 — Laporan dan cetak

```
Fase 4 (Laporan). Baca bagian 3.4, 8.4, 4, 7 dari docs/RANCANGAN-LAUNDRY-WEB.md dan lihat laporan Android yang kulampirkan (laundry_report.pdf).
Fase 1-3 sudah selesai.

Kerjakan HANYA:
1. LaporanService.susun(dari, sampai, filter): struktur kartu per penghuni (kamar atau jalur kamar seperti 307->310, catatan,
   kg, status, subtotal, biaya utama, tagihan lewat kuota, bayar terpisah) dan PENANDA catatan merah = catatan yang membuat
   kumulatif bulan itu melewati kuota (dan setelahnya). Total keseluruhan di akhir.
2. Halaman laporan dengan tampilan SAMA seperti laporan Android: judul properti dan periode, "LAPORAN LAUNDRY" emas di kanan,
   tiga kotak ringkasan, kartu per penghuni dua kolom, batang biaya utama/tagihan lewat kuota/bayar terpisah/WhatsApp,
   batang total marun, catatan kaki. Perbaiki teks "&middot;" mentah di laporan lama.
3. Halaman cetak /laporan/cetak dengan CSS cetak (bagian 8.4): A4, dua kolom, break-inside-avoid, warna latar tercetak,
   font lokal, mode terang, tombol "Cetak / Simpan PDF" (window.print). Filter: periode, semua/kamar/penghuni, rinci.
4. Ekspor CSV.
Tes: total laporan November uji = 278,9 kg; catatan merah sesuai kasus Oktober (Dian, Eva, Glenn, Darren hanya catatan terakhir);
penghuni pindah kamar tampil 307->310; "Periode ini" hanya muncul bila periode tidak sama dengan satu bulan penuh.
Setelah selesai, tunjukkan cara memeriksa hasil cetak di Chrome (Simpan sebagai PDF) dan di HP.
```

---

## Fase 5 — WhatsApp dan pengaturan

```
Fase 5 (WhatsApp dan Pengaturan). Baca bagian 3.5, 3.6, 4, 7 dari docs/RANCANGAN-LAUNDRY-WEB.md. Fase 1-4 sudah selesai.

Kerjakan HANYA:
1. WaService: normalisasiNomor, linkTagihan(penghuni, bulan), linkPeringatan(penghuni, bulan), teksRekap(bulan).
   Tautan https://wa.me/<nomor>?text=<urlencode>. Rekap memakai format rekap November (judul tebal, poin tanggal, total,
   ringkasan keseluruhan). Variabel kosong tidak boleh menghasilkan {} mentah.
2. Tombol WhatsApp di Penghuni, Laporan, dan Beranda (peringatan kuota). Rekap: tombol Salin teks (Clipboard API) dan
   Bagikan (Web Share API) sebagai jalur utama, karena teksnya panjang; wa.me/?text= hanya bila pendek.
3. Halaman Pengaturan (admin): aturan laundry berversi dengan tanggal berlaku (tarif, minimum, kuota, ambang, toleransi,
   pembulatan, mode kuota, prorata), template WhatsApp dengan daftar variabel, pratinjau, dan "Reset ke template awal".
Tes: nomor 08.../62.../+62... menghasilkan tautan yang sama; teks sesuai template; tarif baru berlaku 1 Desember tidak mengubah
catatan November; mode kuota "tanpa_kuota" menyembunyikan peringatan dan lencana kuota.
```

---

## Fase 6 — API v1 (untuk mobile nanti)

```
Fase 6 (API). Baca bagian 9 dan 7 dari docs/RANCANGAN-LAUNDRY-WEB.md. Fase 1-5 sudah selesai.

Kerjakan HANYA: /api/v1 dengan Laravel Sanctum (token per perangkat), API Resource untuk semua respons, Form Request dan
Service yang SAMA dengan web (tanpa menduplikasi logika), kode galat seragam, dukungan "ref" idempotensi pada POST laundry,
dokumentasi OpenAPI (mis. Scribe). Endpoint sesuai tabel bagian 9.
Tes: semua aksi utama lewat API menghasilkan hasil yang identik dengan web; penjaga tidak bisa pindah kamar lewat API;
token yang dicabut ditolak.
```

---

## Catatan sebelum mulai

| Hal | Saran |
|---|---|
| Repo | Gunakan repo **privat**, atau pastikan `storage/app/seed/` ada di `.gitignore` |
| Data nyata | Untuk produksi, ekspor backup terbaru dari aplikasi Android lalu impor; CSV uji hanya untuk uji |
| Verifikasi manual tiap fase | Bandingkan layar dan hasil cetak dengan screenshot dan PDF aplikasi Android |
| Pertanyaan terbuka | Lihat bagian 13 rancangan; bila tidak diputuskan, default tertulis yang dipakai |
