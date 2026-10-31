# Simulasi Integrasi dari Nol — LMD ↔ Showcase ↔ Aplikasi Client

Latihan mendaftarkan aplikasi client (contoh: **Mini POS**) ke ekosistem mulai dari database LMD kosong,
sampai customer membayar di Showcase dan memakai aplikasinya. Mengikuti [`STANDAR_INTEGRASI.md`](STANDAR_INTEGRASI.md) v1.1.

Sudah diuji ujung-ke-ujung di lingkungan pengembangan pada 2026-10-31 (lihat `LOG_EVALUASI.md`).
Versi tampilan ada di dashboard LMD: **Buku Panduan → 🧪 Simulasi Integrasi** (perbarui keduanya bila langkah berubah).

```
[0] Backup & kosongkan  →  [1] LMD: aplikasi, modul, paket  →  [2] Showcase: hubungkan produk
→  [3] Client: cek lmd-client.php  →  [4] Customer: pesan & bayar  →  [5] Customer: buka aplikasi
→  [6] Periksa di LMD  →  [7] Uji penguncian
```

---

## 0. Persiapan

Jalankan di folder XAMPP (`C:\xampp\htdocs\…`). Semua repo sudah `git pull` ke `main`.

```bat
cd C:\xampp\htdocs\License-Management-Dashboard
C:\xampp\php\php.exe database\migrate.php
```

**Backup dulu** (pengosongan tidak bisa dibatalkan):

```bat
C:\xampp\mysql\bin\mysqldump.exe -u root central_saas > C:\xampp\htdocs\_backup_central_saas.sql
C:\xampp\mysql\bin\mysqldump.exe -u root --databases mini_pos > C:\xampp\htdocs\_backup_mini_pos.sql
```

**Kosongkan data:**

| Bagian | Cara | Yang tersisa |
|---|---|---|
| LMD | `C:\xampp\php\php.exe database\reset_data.php` → ketik `KOSONGKAN` | Akun admin LMD, token API, catatan migrasi |
| Mini POS | phpMyAdmin → hapus database `mini_pos` (dibuat ulang otomatis saat Mini POS dibuka) | — |
| Showcase | Admin Showcase → edit produk → kosongkan **Kode Aplikasi di LMD** (tersimpan otomatis) | Produk tetap ada, tombol jadi "Segera hadir" |

`migrate.php` **tidak** membuat ulang aplikasi MINIPOS setelah pengosongan (migrasi yang sudah jalan dicatat di `schema_migrations`).

---

## 1. LMD — daftarkan aplikasi

Login dashboard LMD → **Applications & Modules**.

**1a. + Tambah Application**

| Field | Isi | Catatan |
|---|---|---|
| Application Code | `MINIPOS` | Harus sama persis dengan `app_code` di `mini-pos/lmd-client.php`. Tidak bisa diubah setelah disimpan. |
| Application Name | `Mini POS Express` | Bebas |
| Launch URL | `/mini-pos/?tenant={tenant}&lic={license_key}` | Aplikasi hosted. Kosongkan untuk aplikasi on-premise. |
| Wajib request bertanda tangan | ✅ (default) | Mini POS sudah menandatangani request (§4.3) |

Salah isi? Tombol **Ubah** pada kartu aplikasi (nama, deskripsi, launch URL, tanda tangan).

**1b. + Tambah Module** (pilih aplikasi MINIPOS). Kode **harus sama** dengan yang dikenali aplikasi client
(Mini POS: `APP_MODULES` di `lib/bootstrap.php`); kode lain tidak membuka fitur apa pun.

| Code | Name | Fitur di Mini POS |
|---|---|---|
| `KASIR` | Kasir | Halaman kasir, menu Produk |
| `LAPORAN` | Laporan | Laporan penjualan |
| `STOK` | Stok | Halaman stok, stok dikurangi & dibatasi saat transaksi |
| `MULTI_OUTLET` | Multi Outlet | Menu tampil "segera hadir" |

**1c. Sales Packages → + Tambah Paket** (contoh):

| Code | Nama | Harga | Siklus | Max user | Max instalasi | Modul | Publik |
|---|---|---|---|---|---|---|---|
| `MPOS_STARTER` | Starter | 150.000 | monthly | 2 | 1 | KASIR, LAPORAN | ✅ |
| `MPOS_PRO` | Pro | 350.000 | monthly | 5 | 1 | KASIR, LAPORAN, STOK | ✅ |

Paket harus **publik** dan berstatus aktif agar muncul di Showcase.

---

## 2. Showcase — hubungkan produk

Admin Showcase (`/software-showcase/admin/`) → **Produk Katalog** → edit *Mini POS Express* →
**Kode Aplikasi di LMD** = `MINIPOS` → Simpan. Perubahan langsung tersimpan di server (`data/products.local.json`)
dan tombol produk di halaman publik berubah dari "Segera hadir" menjadi **Berlangganan**.

Tombol **Reset ke Default** mengembalikan katalog ke `data/products.js` bawaan repo.

---

## 3. Aplikasi client — cek identitas

Buka `C:\xampp\htdocs\mini-pos\lmd-client.php`:

```php
'app_code' => 'MINIPOS',          // = Application Code di LMD = lmdAppCode di Showcase
'lmd_api_url' => null,            // null = LMD di host yang sama
'instance_key_prefix' => 'INST-STORE-',
```

Tiga tempat, satu kode: **LMD registry = Showcase `lmdAppCode` = client `app_code`**.

---

## 4. Customer — pesan & bayar

1. Buka Showcase → *Mini POS Express* → **Berlangganan**. Paket yang muncul berasal dari LMD (langkah 1c).
2. Isi data diri, nama toko, subdomain (mis. `tokosimulasi`) → **Lanjut ke Pembayaran**.
3. Pilih metode → **Buat Tagihan & Bayar**. Halaman bayar **SIMULATOR** terbuka di tab baru.
4. Klik **Bayar Rp …**. Simulator mengirim webhook bertanda tangan ke LMD → tagihan lunas → pesanan disetujui otomatis.
   - Coba juga **Simulasikan pembayaran gagal** → **Buat tagihan baru & coba lagi**.
5. Kembali ke tab Showcase: dalam beberapa detik tampil **Pembayaran Disetujui & Lisensi Aktif** + tombol buka aplikasi.

Tidak memakai simulator? Admin tetap bisa **Approve** manual di *Subscriptions → Persetujuan Pembayaran*.

---

## 5. Customer — buka aplikasi

1. Klik **Buka & Masuk ke Aplikasi**. Mini POS memvalidasi lisensi ke LMD (server ke server), menerima secret instalasi, lalu menyimpan tenant.
2. Halaman **Buat Akun Admin**: pakai email yang sama dengan saat berlangganan.
3. Kasir terbuka. Menu sesuai paket: Starter → Kasir, Laporan terbuka; Stok & Multi Outlet 🔒.
4. Lakukan satu transaksi → muncul di **Laporan**.

---

## 6. Periksa di LMD

| Menu | Yang terlihat |
|---|---|
| Customers | Customer baru dari Showcase |
| Subscriptions | Status `active` (pesanan tidak lagi muncul di Persetujuan Pembayaran) |
| Licenses → Detail | Instalasi `tokosimulasi.pos.local` aktif, **🔐 Secret terpasang**, terakhir terlihat |
| License Audit Logs | `order` → `payment_paid` → `approve_order` → `validate` |

---

## 7. Uji penguncian (opsional)

| Uji | Cara | Hasil yang benar |
|---|---|---|
| Modul di luar paket | Buka `mini-pos/stok.php?tenant=…` pada paket Starter | 403 "tidak termasuk paket Anda" |
| Domain lain | Pesan paket `max_instances = 1`, buka lisensi yang sama dari tenant lain | "Instalasi Tidak Diizinkan" (`domain_mismatch`) |
| Aplikasi lain | Daftarkan aplikasi kedua, pakai license key-nya di Mini POS | Ditolak `app_mismatch` |
| Tanda tangan | Kosongkan `instance_secret` tenant di DB `mini_pos` | Ditolak `signature_required`; pulihkan dengan **Reset secret** di Licenses → Detail |
| Webhook palsu | `POST /api/index.php/payments/webhook/simulator` tanpa header tanda tangan | `401 Invalid simulator signature` |

---

## Masalah umum

| Gejala | Penyebab | Solusi |
|---|---|---|
| Lupa password dashboard LMD / akun terkunci | — | Command Prompt di folder LMD: `C:\xampp\php\php.exe database\admin.php reset-password <email>` (email: `... admin.php list`). Tidak menghapus data. |
| Showcase: "Belum ada paket publik" | Paket belum publik/aktif, atau `lmdAppCode` ≠ Application Code | Periksa langkah 1c & 2 |
| Tombol produk tetap "Segera hadir" | `lmdAppCode` kosong | Langkah 2; muat ulang halaman Showcase |
| Halaman bayar tidak terbuka | Popup diblokir browser | Klik **Buka Halaman Pembayaran** di Showcase |
| "Webhook ditolak LMD" di simulator | LMD tidak bisa memanggil dirinya sendiri (host/port berbeda) | Buka Showcase & LMD lewat host yang sama, mis. `http://localhost/…` |
| Mini POS: "Instalasi Tidak Diizinkan" | Lihat pesan di layar (domain, kuota, tanda tangan) | Ikuti petunjuk di pesan; untuk tanda tangan: **Reset secret** |
| Mini POS: semua menu 🔒 | Kode modul di LMD tidak sama dengan `APP_MODULES` | Samakan kode modul (langkah 1b) |
