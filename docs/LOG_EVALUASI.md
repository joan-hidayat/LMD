# Log Evaluasi & Perubahan Ekosistem Central SaaS

Catatan kronologis setiap **perubahan kode** dan **temuan/analisis** pada tiga repo ekosistem
(`lmd`, `software-showcase`, `sample-mini-pos`), sebagai bahan evaluasi dan cross-check.

**Aturan pengisian**
- Entri terbaru di **paling atas**.
- Satu entri = satu sesi kerja / satu perubahan berarti.
- Sebut repo, file, dan commit (jika ada). Temuan diberi ID (`T-xx`) agar bisa dirujuk dan ditutup.
- Saat temuan diselesaikan, ubah statusnya di tabel **Daftar Temuan** dan rujuk entri log yang menyelesaikannya.

Dokumen terkait: [`HASIL_REVIEW_DOCS.md`](HASIL_REVIEW_DOCS.md) · [`EKOSISTEM_CARA_KERJA.md`](EKOSISTEM_CARA_KERJA.md) · [`PANDUAN_PENGGUNAAN.md`](PANDUAN_PENGGUNAAN.md) · [`REKOMENDASI_DECISION_SUPPORT.md`](REKOMENDASI_DECISION_SUPPORT.md)

---

## Daftar Temuan

| ID | Repo | Temuan | Kategori | Status |
|---|---|---|---|---|
| T-01 | lmd, software-showcase | Endpoint `licenses/order`, `licenses/order_status`, `licenses/approve_order` dipanggil Showcase (`assets/js/app.js`) tetapi tidak ada di router LMD (`api/index.php`). Wizard berlangganan berhenti di langkah bayar. | Blocker fungsional | Selesai — lihat Riwayat "T-01 + T-12" |
| T-02 | lmd | `validateLicense` membuat instance baru jika `instance_key` tidak dikenal dan menimpa `domain` (`COALESCE`). Tidak ada respons `domain_mismatch` seperti klaim di `PANDUAN_PENGGUNAAN.md` §5. Satu license key bisa dipakai dari mana saja. | Blocker desain | Terbuka |
| T-03 | sample-mini-pos | `max_users` diterima dari LMD tetapi belum ditegakkan (belum ada batas sesi/akun). | Fungsional | Terbuka |
| T-04 | lmd | Tidak ada autentikasi API key/App-Secret untuk client maupun auth untuk endpoint admin (`customers`, `subscriptions`, `licenses/{id}`). Klaim "7 layer security" di docs belum terimplementasi. | Hardening (pra-production) | Ditunda — tahap pengembangan |
| T-05 | lmd | Tidak ada rate limiting / anti brute-force. | Hardening (pra-production) | Ditunda — tahap pengembangan |
| T-06 | sample-mini-pos | Lisensi dikirim lewat query string (`?tenant=&lic=`) dan disimpan di `localStorage`; validasi JS mudah dilewati. `index.php` membaca DB `central_saas` langsung (coupling ke DB LMD). | Arsitektur | Terbuka |
| T-07 | software-showcase | Password admin hardcode di `admin/admin.js` (`nexus2024`) dan dicek di browser. | Hardening (pra-production) | Ditunda — sengaja selama pengembangan |
| T-08 | lmd | `SecurityHelper::generateActivationKey` menghasilkan prefix ganda `ACT-ACT-…`. | Minor | Terbuka |
| T-09 | semua | Docs memuat path lokal Windows (`c:\xampp\…`, `file:///c:/…`), IP LAN `192.168.0.116`, base URL tidak konsisten (`/api/...` vs `/api/index.php/...`), dan dua bagian bernomor 12 di `PANDUAN_SHOWCASE.md`. | Dokumentasi | Ditunda — tahap pengembangan |
| T-10 | lmd | Docs menyebut backend "100% siap" untuk n8n (`approve_order`), tidak sesuai kode. Perlu penanda status (✅/🟡/📋) pada klaim di docs. | Dokumentasi | Selesai — `approve_order` kini ada; klaim docs sesuai |
| T-11 | lmd | Token CSS `--border` dan `--muted` dipakai di `index.html` (switcher tab analitik, header Buku Panduan, dll.) tetapi tidak didefinisikan di `style.css`; garis/warna jatuh ke default browser. | Minor (UI) | Terbuka |
| T-12 | lmd, software-showcase | Paket & harga di wizard Showcase hardcode (Starter 3 user Rp150rb, Pro 15 user Rp350rb, Enterprise 50 user Rp850rb) dan berbeda dengan data LMD `seed.sql` (APO_BASIC 3 user Rp300rb, APO_PRO 10 user Rp750rb, APO_ENT 50 user Rp1,5jt annual). | Fungsional / konsistensi data | Selesai — lihat Riwayat "T-01 + T-12" |
| T-13 | lmd | `future_module_access` ada di tabel `packages` (ERD level 9 🟢 FIX), tetapi belum ada logika yang menambahkan modul baru ke `subscription_modules`/`license_modules` paket terkait. | Fungsional | Terbuka |
| T-14 | lmd, software-showcase | Istilah docs tidak sesuai kode: `validation_logs` (seharusnya `license_logs`), "Hashing SHA-256" (kode memakai `random_bytes` hex), "Level 8" di `PANDUAN_SHOWCASE.md` §10 bentrok dengan ERD Level 8. | Dokumentasi | Terbuka |
| T-15 | lmd, sample-mini-pos, docs | Repo LMD tidak memiliki `.htaccess`, tetapi Mini POS (`app.js`) dan docs memanggil `/api/licenses/...` tanpa `index.php`. Hanya berfungsi jika ada rewrite rule di server lokal. Saat ini panggilan validate Mini POS gagal diam-diam (hanya `console.warn`). Showcase sudah dipindah ke `/api/index.php/...`. | Fungsional / konfigurasi | Terbuka |
| T-16 | software-showcase | `style.css` publik tidak punya aturan `.hidden`, sehingga keempat langkah wizard dan layar sukses tampil bersamaan. | Bug UI | Selesai — lihat Riwayat "T-01 + T-12" |
| T-17 | software-showcase, sample-mini-pos | Wizard meminta "password akun admin" dan menampilkan "kredensial login", tetapi Mini POS tidak punya login dan LMD sengaja tidak menyimpan password. Perlu keputusan: hapus field, atau buat akun admin di aplikasi client. | Desain / UX | Terbuka |
| T-18 | software-showcase | Semua produk Showcase (termasuk Mini POS Express, DineFlow Resto) berlangganan memakai paket `APOTEK`, karena `products.js` belum punya pemetaan ke kode aplikasi LMD. Kode sudah membaca `product.lmdAppCode` (default `APOTEK`). | Fungsional / data | Terbuka |
| T-19 | sample-mini-pos | Judul toko di Mini POS memakai nama customer (`Budi Santoso — Mini POS Express`), bukan nama toko yang diisi di wizard (tersimpan di `application_instances.instance_name`). | Minor | Terbuka |

---

## Riwayat

### 2026-10-23 — T-01 + T-12: alur berlangganan Showcase berjalan end-to-end
**Jenis:** Perubahan kode
**Repo:** lmd, software-showcase

**LMD — API**
- Baru: `api/models/OrderModel.php` (create, status, approve, reject; transaksi atomik, `SELECT … FOR UPDATE` saat approve/reject).
- `LicenseController`: `order()`, `orderStatus()`, `approveOrder()`, `rejectOrder()`; error 400/404/409.
- Router `api/index.php`: `POST licenses/order`, `GET licenses/order_status`, `POST licenses/approve_order`, `POST licenses/reject_order`, `GET packages?app_code=`.
- `PackageModel::getPublicByAppCode()` + `PackageController::publicCatalog()`: paket publik + modul (sumber tunggal harga untuk Showcase).
- `SubscriptionModel::getAll()`: tambah `customer_phone`, `instance_domain`.

**LMD — Dashboard**
- Banner "N pesanan menunggu persetujuan pembayaran" di Dashboard.
- Panel **Persetujuan Pembayaran & Aktivasi Domain Klien** di Subscriptions dengan tombol Approve / Tolak (konfirmasi).
- Kolom status subscription kini berwarna sesuai status (sebelumnya selalu hijau).

**Showcase**
- Kartu paket dimuat dari LMD (`GET packages?app_code`), tidak lagi hardcode → T-12.
- Payload order memakai `app_code` produk (`product.lmdAppCode`, default `APOTEK`) dan metode bayar yang benar-benar dipilih (sebelumnya selalu `QRIS`).
- Semua panggilan memakai `/api/index.php/...` (lihat T-15).
- Menangani pesanan ditolak; fallback URL aplikasi kini menyertakan `lic`.
- Tambah aturan CSS `.hidden` → T-16.

**Keputusan desain**
- Lisensi `suspended` selama pending (enum `licenses.status` tidak punya `pending`); **tanpa perubahan schema**, DB lokal langsung kompatibel.
- Masa aktif dihitung sejak tanggal approve sesuai `billing_cycle` paket.
- Subdomain divalidasi (`[a-z0-9-]`, 3–40 karakter), unik di antara lisensi yang belum `revoked`.
- `admin_password` tidak disimpan dan dihapus dari log (lihat T-17).

**Verifikasi** (MariaDB sementara dari `schema.sql` + `seed.sql`, PHP built-in server dengan struktur folder seperti XAMPP)
- API: paket publik; order sukses (PPN 11% benar); subdomain duplikat → 409; email/subdomain invalid → 400; paket tidak ada → 404; validate saat pending → ditolak; Mini POS saat pending → redirect `license_not_active`; approve → lisensi aktif, `expires_at` +1 bulan, activation dibuat, 6 modul; approve ulang → 409; reject → `cancelled`/`revoked` dan subdomain bisa dipakai lagi; `admin_password` tidak muncul di `license_logs`; revenue dashboard tidak menghitung pesanan pending.
- Browser (Playwright): Showcase menampilkan 3 paket dari LMD → isi data → bayar → menunggu; Dashboard LMD menampilkan banner + panel → Approve; Showcase otomatis menampilkan layar sukses → Mini POS terbuka dengan lisensi baru. Tidak ada error JS.

**Temuan baru:** T-15, T-16 (selesai), T-17, T-18, T-19.

### 2026-10-23 — Dokumentasi hasil review docs
**Jenis:** Analisis + dokumentasi (tanpa perubahan kode aplikasi)
**Repo:** lmd, software-showcase, sample-mini-pos

**Yang dilakukan**
- Menuliskan pemahaman lengkap atas seluruh docs ke `docs/HASIL_REVIEW_DOCS.md`: ringkasan per dokumen, sintesis, penilaian, tabel docs vs kode, inkonsistensi antar-dokumen, dan urutan penanganan.
- Pemeriksaan tambahan pada ERD level 9 (aturan future module), `database/seed.sql`, dan wizard Showcase menghasilkan 3 temuan baru: **T-12**, **T-13**, **T-14**.

**Keputusan**
- Temuan dikerjakan **setelah** seluruh hasil review terdokumentasi (permintaan pemilik proyek).

### 2026-10-23 — Tab "Ekosistem Central SaaS" di menu Buku Panduan
**Jenis:** Perubahan kode (UI dashboard LMD)
**Repo:** lmd

**Yang diubah**
- `index.html` — halaman Buku Panduan kini punya 2 tab: **📘 Panduan & Integrasi API** (isi lama, tidak diubah) dan **🧭 Ekosistem Central SaaS** (baru). Isi tab baru = `docs/EKOSISTEM_CARA_KERJA.md`: peta sistem (SVG), model data 4 lapis, Alur A/B/C, tabel status implementasi dengan rujukan ID temuan.
- `assets/js/app.js` — state `guideTab: 'manual'`.
- `assets/css/style.css` — kelas `.eco-*` memakai token tema gelap yang sudah ada.
- Cache-busting aset dinaikkan ke `?v=20261023_v1`.

**Verifikasi**
- Dirender di Chromium (Playwright): perpindahan tab berfungsi, tidak ada error JS, tampilan dicek per bagian.

**Catatan**
- Tab switcher baru memakai `var(--border-subtle)`. Switcher analitik lama memakai `var(--border)` yang tidak didefinisikan di `style.css` → dicatat sebagai T-11.
- Tab Ekosistem harus ikut diperbarui setiap kali `docs/EKOSISTEM_CARA_KERJA.md` berubah.

### 2026-10-23 — Review awal docs vs kode ketiga repo
**Jenis:** Analisis (tanpa perubahan kode)
**Repo:** lmd, software-showcase, sample-mini-pos

**Yang dilakukan**
- Membaca seluruh docs: `LMD/docs/PANDUAN_PENGGUNAAN.md`, `REKOMENDASI_DECISION_SUPPORT.md`, `docs/ERD/*` (level 0–9), `software-showcase/README.md`, `docs/PANDUAN_SHOWCASE.md`, `sample-mini-pos/README.txt`.
- Mencocokkan klaim docs dengan kode: `api/index.php`, `api/helpers/security.php`, `api/models/LicenseModel.php`, `api/controllers/LicenseController.php`, `database/schema.sql`, `software-showcase/assets/js/app.js`, `software-showcase/admin/admin.js`, `sample-mini-pos/index.php`, `sample-mini-pos/app.js`.

**Kesimpulan**
- Arsitektur & ERD matang: pemisahan Katalog → Komersial → Provisioning → Runtime, snapshot modul (`package_modules` → `subscription_modules` → `license_modules`), 12 tabel `schema.sql` sesuai ERD.
- Alur auto-register dan validasi berjalan; alur berlangganan via Showcase belum bisa end-to-end.
- Temuan dicatat sebagai T-01 s.d. T-10.

**Konteks dari pemilik proyek**
- Proyek masih tahap perancangan & pengembangan; password dan URL lokal **sengaja hardcode**. Item terkait diberi status "Ditunda — tahap pengembangan", bukan bug.

**Prioritas yang disepakati sebagai usulan**
1. T-01 endpoint order/approve.
2. T-02 aturan penguncian instance/domain.
3. T-03 penegakan `max_users`.
4. T-04, T-05, T-07 hardening sebelum production.

**Dokumen yang dihasilkan**
- `docs/EKOSISTEM_CARA_KERJA.md` — penjelasan cara kerja sistem (peta, model data, 3 alur, status).
- Versi visual: https://claude.ai/artifact/1MFMqHEvSLC6c3UHLEkZHi (privat).
- `docs/LOG_EVALUASI.md` — dokumen ini.
- `CLAUDE.md` di tiga repo — aturan agar setiap sesi berikutnya memperbarui log ini.
