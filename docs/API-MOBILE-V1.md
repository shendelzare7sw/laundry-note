# Kontrak REST API Mobile v1 (`Laundry-Kos`)

Dokumen acuan resmi integrasi aplikasi Native Android (**Kotlin + Jetpack Compose + Retrofit + Kotlinx Serialization**) dengan backend Laravel `laundry-kos`.

---

## 1. Base URL & Koneksi Lokal vs Produksi

| Lingkungan | Base URL |
|---|---|
| **Android Emulator** (Laragon/Artisan di laptop) | `http://10.0.2.2:8000/api/v1/` |
| **HP Android Fisik** (Wi-Fi sama, jalankan `php artisan serve --host=0.0.0.0 --port=8000`) | `http://<IP-LAPTOP>:8000/api/v1/` |
| **Produksi (VPS + HTTPS)** | `https://<domain-anda>/api/v1/` |

---

## 2. Header Standar

| Header | Wajib? | Keterangan |
|---|---|---|
| `Accept` | **Ya** | `application/json` |
| `Content-Type` | Saat POST/PUT | `application/json` |
| `Authorization` | Semua kecuali `/auth/login` | `Bearer <sanctum_plain_text_token>` |
| `X-Properti-Id` | Khusus `owner` / `dev_admin` dengan >1 properti aktif | ID integer properti aktif (staf `admin`/`penjaga` otomatis terikat ke `users.properti_id`) |
| `X-Request-Id` | Opsional (otomatis dikembalikan di response) | UUID pelacakan galat |

---

## 3. Konvensi Tipe Data (Aman untuk Kotlin)

- **Uang:** `Int` rupiah (mis. `24000`, `0`, atau `null` bila belum diisi).
- **Berat:** selalu tersedia `berat_centi: Int?` (`2370` = `23,7 kg`) **dan** `berat_label: String?` (`"23,7"`) untuk menghindari galat presisi `Double`/`Float`.
- **Tanggal:** `String` format ISO `YYYY-MM-DD` (zona waktu `Asia/Jakarta`).
- **Timestamp:** `String?` format ISO-8601 (`2026-11-21T14:30:00+07:00`).
- **Daftar Panjang (Tanpa Pagination Bernomor):** gunakan query `?tampil=20`, `?tampil=40`, dst. Respons membawa objek:
  ```json
  "meta": {
    "ditampilkan": 20,
    "total": 108,
    "langkah": 20,
    "ada_lagi": true,
    "tampil_berikutnya": 40
  }
  ```

---

## 4. Format Galat Seragam

| HTTP | Kode (`code`) | Penyebab |
|---|---|---|
| `401` | `UNAUTHORIZED` | Token kosong / kedaluwarsa / sudah logout |
| `403` | `AKUN_NONAKTIF` / `PROPERTI_TIDAK_AKTIF` / `AKSES_PROPERTI_DITOLAK` | Akun/properti nonaktif atau peran tidak berwenang |
| `404` | — | Data tidak ditemukan pada properti aktif |
| `422` | `PROPERTI_BELUM_DIPILIH` | Owner/DevAdmin punya >1 properti aktif & belum kirim `X-Properti-Id` (respons memuat `properti_list`) |
| `422` | Validasi Laravel (`errors`) | Input tidak valid (mis. duplikat 1 tanggal 1 catatan per penghuni) |

---

## 5. Daftar Endpoint `/api/v1`

### Auth, Profil, & Beranda
- `POST /auth/login` — Body: `{ "username", "password", "device_name" }` → `{ token, token_type, user, properti_aktif, properti_list, permissions }`
- `POST /auth/logout` — Cabut token perangkat ini
- `GET /me` — Profil user, `properti_aktif`, `properti_list`, `permissions`
- `GET /beranda?bulan=YYYY-MM` — Ringkasan bulanan & `daftar_peringatan` (+ `wa_url`)

### Properti, Kamar, & Pengaturan
- `GET /properti` — Daftar properti yang bisa diakses user
- `GET /kamar?kosong=1` — Daftar kamar aktif (opsional filter hanya kamar kosong)
- `GET /pengaturan?tanggal=YYYY-MM-DD` — Aturan aktif (`aturan`, `izin_penjaga`, `template_wa`, `boleh_ubah`)
- `PUT /pengaturan/aturan` — Simpan aturan tarif & kuota berversi (`berlaku_mulai`)
- `PUT /pengaturan/izin-penjaga` — Simpan `jam_edit_penjaga` (`null` = tanpa batas), `penjaga_kelola_kontrak`, `penjaga_ubah_catatan_lain`
- `PUT /pengaturan/template-wa` — Simpan 3 template WA
- `POST /pengaturan/template-wa/reset` — Reset 1 (`{ "kunci": "template_wa_tagihan" }`) atau semua template WA

### Penghuni, Kontrak, & Kuota
- `GET /penghuni?tab=semua|booking|aktif|keluar&cari=&bulan=YYYY-MM` — Daftar penghuni + `hitungan` per tab + kuota bulanan
- `POST /penghuni` — Daftarkan penghuni masuk (`nama`, `kamar_id`, `status`, `tanggal_masuk`, `jangka_waktu_sewa`, `tanggal_keluar_rencana`, `tanggal_masuk_perkiraan`, `no_wa`, `catatan`)
- `GET /penghuni/{id}?bulan=YYYY-MM` — Detail lengkap (`penghuni`, `penempatan`, `kuota_bulan_ini`, `riwayat_kuota`, `catatan_laundry`, `wa_tagihan`, `wa_peringatan`, `permissions`)
- `PUT /penghuni/{id}` — Ubah data & kontrak penghuni
- `POST /penghuni/{id}/pindah-kamar` — `{ "kamar_tujuan_id", "tanggal_pindah" }` (kuota bulan berjalan tetap utuh)
- `POST /penghuni/{id}/batalkan-pindah` — Batalkan pindah kamar terakhir
- `POST /penghuni/{id}/keluar` — `{ "tanggal_keluar", "alasan_keluar" }`
- `POST /penghuni/{id}/batalkan-keluar` — Aktifkan kembali bila kamar masih kosong
- `POST /penghuni/{id}/ganti-nomor` — `{ "no_wa" }`
- `POST /penghuni/{id}/penyesuaian-kuota` — `{ "bulan": "YYYY-MM", "nominal": 20000 }` atau `{ "bulan": "YYYY-MM", "reset": true }`

### Pencatatan Laundry
- `GET /laundry?periode=bulan_ini|bulan_lalu|hari_ini|7_hari|semua|rentang&dari=&sampai=&cari=&jenis=&tampil=20` — Daftar catatan + `ringkasan` + `meta` + `pratinjau_lengkapi_harga` + `jumlah_terhapus`
- `GET /laundry/info-kuota?tanggal=YYYY-MM-DD&penghuni_id=&berat_kg=&kecuali_id=` — Pratinjau live combobox, biaya otomatis, kuota sebelum/sesudah, dan deteksi `duplikat` 1-tanggal-1-catatan
- `POST /laundry` — Simpan catatan baru (mendukung `ref` idempoten & `item: [{ nama, jumlah, berat_kg?, biaya }]`)
- `GET /laundry/{id}` — Detail 1 catatan laundry + `item[]`
- `PUT /laundry/{id}` — Ubah catatan laundry
- `DELETE /laundry/{id}` — Hapus lunak (soft delete)
- `GET /laundry/terhapus?tampil=15` — Daftar catatan terhapus
- `POST /laundry/{id}/pulihkan` — Pulihkan catatan terhapus
- `POST /laundry/{id}/tetapkan-pemilik` — `{ "penghuni_id" }` untuk memindahkan catatan `tanpa_nama`
- `POST /laundry/lengkapi-harga` — Lengkapi otomatis semua catatan berberat tanpa harga

### Laporan & WhatsApp
- `GET /laporan?periode=bulan_ini|bulan_lalu|rentang&dari=&sampai=&penghuni_id=&rinci=0|1` — Data lengkap laporan (header, `ringkasan`, `kartu_penghuni` + jalur kamar `307→310` + penanda merah K4 + `wa_tagihan`, `kartu_lainnya` `GDG`, dan `rekap_wa`)
- `GET /wa/tagihan/{penghuni}?bulan=YYYY-MM` — `{ pesan, nomor, url, total_tagihan }` (hanya lewat kuota > 0 + bayar terpisah > 0)
- `GET /wa/peringatan/{penghuni}?bulan=YYYY-MM` — `{ pesan, nomor, url }`
