# Keputusan Desain v2 — Adendum Resmi

Dokumen ini adalah adendum resmi terhadap [RANCANGAN-LAUNDRY-WEB.md](./RANCANGAN-LAUNDRY-WEB.md). Apabila terdapat perbedaan atau pertentangan antara rancangan awal dan dokumen ini, **keputusan di dokumen ini yang berlaku**.

---

## 1. Keputusan Domain, Data, dan Tampilan

| Kode | Topik | Keputusan Final |
|---|---|---|
| **K1** | Anomali Riko & Devin di Kamar 307 | Devin tetap di kamar 307 s.d. `2026-11-10`, pindah ke kamar 310 per `2026-11-11`. Riko masuk kamar 307 dikoreksi otomatis menjadi `2026-11-11` saat impor seed (laundry pertamanya tercatat 13 Nov, sehingga tidak ada yang ditolak). Impor mencatat koreksi tersebut di laporan anomali. |
| **K2** | Biaya Laundry & Kasus Syafira | Form selalu mengisi otomatis `max(berat, min_kg) * tarif` saat berat diisi, namun tetap bisa diubah manual oleh operator. Bila nilai berbeda dari hitungan otomatis, sistem menandai `biaya_manual = true` dan tidak menimpa saat kalkulasi ulang. Data Syafira 10 Nov (`2,4 kg = Rp20.000`) diimpor apa adanya dengan `biaya_manual = true`. |
| **K3** | Tailwind CSS v4 | Proyek tetap menggunakan **Tailwind CSS v4** (`@tailwindcss/vite`). Seluruh token warna Bagian 8.1 ditempatkan di blok `@theme` pada `resources/css/app.css` dengan nama token yang sama. File `tailwind.config.js` dan `postcss.config.js` (gaya v3) tidak dipakai. |
| **K4** | Catatan Merah di Bawah Toleransi | Catatan yang membuat kumulatif bulanan melewati kuota **selalu ditandai merah** sebagai peringatan pada laporan, meskipun kelebihannya berada di bawah `toleransi_kg` dan tagihan default-nya Rp0 (tampil lencana `"Lewat · di bawah toleransi"`). |
| **K5** | Toleransi Relatif | Tagihan lewat kuota dikenakan apabila `kelebihan >= toleransi_kg` (default `1,0` kg; nilai tepat 1,0 kg ditagih Rp8.000). Parameter batas ini dapat diatur di menu Pengaturan admin. Di bawah batas toleransi, default tagihan Rp0 dan tetap dapat diisi/disesuaikan manual melalui `penyesuaian_lewat_kuota` sebelum cetak laporan. |
| **K6** | Saklar Izin Kontrak Penjaga | Tersedia satu saklar per properti (`penjaga_kelola_kontrak`, default `false` / nonaktif). Jika diaktifkan oleh admin/owner, **seluruh penjaga** di properti tersebut berhak mengelola kontrak kamar: Masuk (kontrak baru), Pindah Kamar, Keluar, dan Batalkan Keluar. Semua aksi tetap tercatat di `audit_log` atas nama penjaga. |

---

## 2. Arsitektur Multi-Properti & 4 Peran (K7)

Menggantikan bagian Ruang Lingkup (§1 yang membatasi satu properti) dan Pengguna & Peran (§3.7):

1. **Istilah & Kepemilikan:**
   - Entitas utama bangunan kos disebut **Properti** (tabel `properti`).
   - Aplikasi melayani **satu Owner** (bukan SaaS multi-tenant), namun Owner dapat memiliki **banyak properti**.
   - Properti pertama untuk data awal bernama **"Dbata Residence"**.
2. **Hierarki 4 Peran (`App\Enums\Peran`):**
   - `dev_admin`: Administrator teknis tingkat sistem (kelola akun owner, konfigurasi nilai bawaan aturan & template WA untuk properti baru, melihat `audit_log` lintas properti, info sistem, impor/ekspor, serta dukungan teknis masuk sebagai pengguna lain yang tercatat di audit). Tidak terlibat operasional harian.
   - `owner`: Pemilik kos. Dapat CRUD properti, menjalankan wizard setup properti, menunjuk/mengelola `admin` dan `penjaga` pada propertinya, melihat dashboard & laporan lintas properti, serta mengubah aturan laundry per properti.
   - `admin`: Admin properti. Ditetapkan oleh Owner ke **tepat satu properti** (`users.properti_id`). Maksimal **1 admin aktif per properti** (dijaga oleh kolom turunan `storedAs` dan indeks unik di MySQL). Mengelola seluruh operasional laundry/kontrak/penghuni/laporan/WA di propertinya, mengelola akun `penjaga` di propertinya (buat, nonaktifkan, ganti akun/reset password), mengatur saklar **K6**, mengubah aturan laundry propertinya, dan melakukan ekspor/backup data properti tersebut.
   - `penjaga`: Petugas operasional harian. Terikat ke **tepat satu properti** (`users.properti_id`). Mengelola catatan laundry (tambah, ubah/hapus milik sendiri dalam batas jam), melihat daftar penghuni & tombol WhatsApp, melihat laporan. Aksi kontrak kamar (Masuk, Pindah, Keluar, Batalkan Keluar) hanya bisa dilakukan bila saklar **K6** aktif.
3. **Keterikatan Karyawan & Login:**
   - Setiap karyawan (`admin` / `penjaga`) terikat ke tepat satu properti melalui `users.properti_id` (tanpa tabel pivot `gedung_user`). Tidak ada pemilihan properti setelah login bagi karyawan—saat login langsung masuk ke dashboard properti penempatannya.
   - Login menggunakan **`username`** (unik); kolom `email` bersifat opsional (`nullable`). Akun dengan `aktif = false` ditolak saat login dan sesi aktifnya dicabut.
   - Registrasi publik **dimatikan**.
4. **Pindah Penghuni Antar-Properti:**
   - Diperlakukan sebagai Keluar di properti lama dan Masuk sebagai penghuni baru di properti tujuan. Kuota bulan berjalan **otomatis dimulai dari nol**.
5. **Integritas Referensial & Pembagian Fase:**
   - Data operasional (`kamar`, `penghuni`, `laundry`, `pengaturan`, `audit_log`) wajib terikat `properti_id`.
   - Fase 1 dipecah menjadi:
     - **Fase 1a (Fondasi Auth, Peran, Properti, Layout):** Login username, 4 peran, tabel `properti`, manajemen pengguna, layout mobile-first (`@theme` Tailwind v4 + font Roboto lokal), komponen Blade dasar, dan seeder awal.
     - **Fase 1b (Tabel Inti, Seeder Operasional, Impor Seed, & Wizard):** Tabel `kamar`, `penghuni`, `penempatan`, `laundry`, `penyesuaian_lewat_kuota`, `pengaturan`, `audit_log`, seeder kamar/pengaturan, artisan `laundry:impor-seed`, serta wizard setup properti untuk Owner.
