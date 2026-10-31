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
| T-02 | lmd | `validateLicense` membuat instance baru jika `instance_key` tidak dikenal dan menimpa `domain` (`COALESCE`). Tidak ada respons `domain_mismatch` seperti klaim di `PANDUAN_PENGGUNAAN.md` §5. Satu license key bisa dipakai dari mana saja. Bukti nyata di data lokal: lisensi Budi Santoso tercatat punya 2 instance, salah satunya ber-domain IP `192.168.0.116`. | Blocker desain | Selesai — lihat Riwayat "T-02" (batasan pemalsuan dilanjutkan di T-22) |
| T-03 | sample-mini-pos | `max_users` diterima dari LMD tetapi belum ditegakkan (belum ada batas sesi/akun). | Fungsional | Selesai — lihat Riwayat "Login Mini POS" (metode named users) |
| T-04 | lmd | Tidak ada autentikasi API key/App-Secret untuk client maupun auth untuk endpoint admin (`customers`, `subscriptions`, `licenses/{id}`). Klaim "7 layer security" di docs belum terimplementasi. | Hardening (pra-production) | Selesai — lihat Riwayat "T-04" & "Paket keamanan" |
| T-05 | lmd | Tidak ada rate limiting / anti brute-force. | Hardening (pra-production) | Selesai — lihat Riwayat "Paket keamanan" |
| T-06 | sample-mini-pos | Lisensi dikirim lewat query string (`?tenant=&lic=`) dan disimpan di `localStorage`; validasi JS mudah dilewati. `index.php` membaca DB `central_saas` langsung (coupling ke DB LMD). | Arsitektur | Selesai — lihat Riwayat "Login Mini POS" |
| T-07 | software-showcase | Password admin hardcode di `admin/admin.js` (`nexus2024`) dan dicek di browser. | Hardening (pra-production) | Selesai — lihat Riwayat "Paket keamanan" |
| T-08 | lmd | `SecurityHelper::generateActivationKey` menghasilkan prefix ganda `ACT-ACT-…`. | Minor | Selesai — lihat Riwayat "Perapian T-08, T-11, T-14" |
| T-09 | semua | Docs memuat path lokal Windows (`c:\xampp\…`, `file:///c:/…`), IP LAN `192.168.0.116`, base URL tidak konsisten (`/api/...` vs `/api/index.php/...`), dan dua bagian bernomor 12 di `PANDUAN_SHOWCASE.md`. | Dokumentasi | Ditunda — tahap pengembangan |
| T-10 | lmd | Docs menyebut backend "100% siap" untuk n8n (`approve_order`), tidak sesuai kode. Perlu penanda status (✅/🟡/📋) pada klaim di docs. | Dokumentasi | Selesai — `approve_order` kini ada; klaim docs sesuai |
| T-11 | lmd | Token CSS `--border` dan `--muted` dipakai di `index.html` (switcher tab analitik, header Buku Panduan, dll.) tetapi tidak didefinisikan di `style.css`; garis/warna jatuh ke default browser. | Minor (UI) | Selesai — lihat Riwayat "Perapian T-08, T-11, T-14" |
| T-12 | lmd, software-showcase | Paket & harga di wizard Showcase hardcode (Starter 3 user Rp150rb, Pro 15 user Rp350rb, Enterprise 50 user Rp850rb) dan berbeda dengan data LMD `seed.sql` (APO_BASIC 3 user Rp300rb, APO_PRO 10 user Rp750rb, APO_ENT 50 user Rp1,5jt annual). | Fungsional / konsistensi data | Selesai — lihat Riwayat "T-01 + T-12" |
| T-13 | lmd | `future_module_access` ada di tabel `packages` (ERD level 9 🟢 FIX), tetapi belum ada logika yang menambahkan modul baru ke `subscription_modules`/`license_modules` paket terkait. | Fungsional | Selesai — lihat Riwayat "T-13" |
| T-14 | lmd, software-showcase | Istilah docs tidak sesuai kode: `validation_logs` (seharusnya `license_logs`), "Hashing SHA-256" (kode memakai `random_bytes` hex), "Level 8" di `PANDUAN_SHOWCASE.md` §10 bentrok dengan ERD Level 8. | Dokumentasi | Selesai — lihat Riwayat "Perapian T-08, T-11, T-14" |
| T-15 | lmd, sample-mini-pos, docs | Repo LMD tidak memiliki `.htaccess`, tetapi Mini POS (`app.js`) dan docs memanggil `/api/licenses/...` tanpa `index.php`. Hanya berfungsi jika ada rewrite rule di server lokal. Saat ini panggilan validate Mini POS gagal diam-diam (hanya `console.warn`). Showcase sudah dipindah ke `/api/index.php/...`. | Fungsional / konfigurasi | Selesai — `api/.htaccess` masuk repo, docs `PANDUAN_PENGGUNAAN.md` dilengkapi (lihat Riwayat "Rekonsiliasi WIP lokal") |
| T-16 | software-showcase | `style.css` publik tidak punya aturan `.hidden`, sehingga keempat langkah wizard dan layar sukses tampil bersamaan. | Bug UI | Selesai — lihat Riwayat "T-01 + T-12" |
| T-17 | software-showcase, sample-mini-pos | Wizard meminta "password akun admin" dan menampilkan "kredensial login", tetapi Mini POS tidak punya login dan LMD sengaja tidak menyimpan password. Perlu keputusan: hapus field, atau buat akun admin di aplikasi client. | Desain / UX | Selesai — lihat Riwayat "Login Mini POS" |
| T-18 | software-showcase | Semua produk Showcase (termasuk Mini POS Express, DineFlow Resto) berlangganan memakai paket `APOTEK`, karena `products.js` belum punya pemetaan ke kode aplikasi LMD. Kode sudah membaca `product.lmdAppCode` (default `APOTEK`). | Fungsional / data | Selesai — lihat Riwayat "Standar Integrasi v1" |
| T-19 | sample-mini-pos | Judul toko di Mini POS memakai nama customer (`Budi Santoso — Mini POS Express`), bukan nama toko yang diisi di wizard (tersimpan di `application_instances.instance_name`). | Minor | Selesai — lihat Riwayat "Login Mini POS" |
| T-20 | sample-mini-pos | `app.js` tersimpan dengan encoding ganda (UTF-8 dibaca sebagai Windows-1252), sehingga emoji produk tampil rusak (`ðŸ¥`, `â˜•`). | Bug UI | Selesai — lihat Riwayat "Login Mini POS" |
| T-21 | sample-mini-pos | Belum ada pemulihan jika **admin** lupa password (kasir bisa direset oleh admin). Opsi: reset via email pemesan yang tercatat di LMD, atau tombol reset dari dashboard LMD. | Fungsional | Selesai — lihat Riwayat "T-21" |
| T-22 | lmd | `instance_key` dan `domain` pada `validate` dikirim oleh client sendiri, sehingga pihak yang memegang license key dapat meniru instalasi asli dan lolos penguncian T-02. Perlu request bertanda tangan (secret per instance yang diterbitkan saat aktivasi + HMAC), terkait T-04. | Keamanan (pra-production) | Selesai — lihat Riwayat "Paket keamanan" (model trust on first use) |
| T-23 | lmd | `database/seed.sql` men-`TRUNCATE` semua tabel `central_saas` tanpa peringatan; berbahaya jika dijalankan pada data nyata. Peringatan ditambahkan di kepala file, dan pembaruan struktur kini lewat `database/migrate.php`. | Operasional | Selesai — lihat Riwayat "T-02" |
| T-24 | lmd, sample-mini-pos, software-showcase | Mini POS belum terdaftar sebagai aplikasi sendiri di LMD (seed hanya APOTEK, DISTRIBUTOR, TMS); ia memakai paket `APOTEK`. Selain itu `LicenseGate` Mini POS tidak memeriksa `app_code` di respons `validate`, sehingga license key aplikasi lain (misal TMS) juga diterima. Perlu: aplikasi `MINIPOS` + modul + paket di LMD, `lmdAppCode` per produk di Showcase (T-18), dan pengecekan `app_code` di client. | Fungsional / keamanan | Selesai — lihat Riwayat "Standar Integrasi v1" |
| T-25 | lmd | Modul yang dihapus (soft delete) tetap tercatat di `package_modules`: jumlah modul paket salah dan order baru ikut menyalin modul terhapus ke subscription/lisensi. Terlihat saat verifikasi lokal T-13 (modul `TEST_FUTURE`). | Data | Selesai — lihat Riwayat "T-25" |
| T-26 | lmd | `SecurityHelper::getClientIp` memercayai `X-Forwarded-For` / `Client-IP` dari siapa pun: rate limit bisa ditembus dan IP di audit log bisa dipalsukan. | Keamanan | Selesai — lihat Riwayat "Paket keamanan" |
| T-27 | software-showcase | Admin Showcase: status login di `sessionStorage` (bisa dipalsukan dari DevTools) dan `admin/index.html` bisa dibuka langsung tanpa PHP. | Keamanan | Selesai — lihat Riwayat "Paket keamanan" |
| T-28 | sample-mini-pos, lmd | Mini POS menyimpan daftar `modules` dari LMD tetapi **tidak memakainya**: semua fitur terbuka untuk semua paket (Starter = Bisnis). `STANDAR_INTEGRASI.md` 4.2 no. 5 sempat mengklaim sudah diterapkan; klaim dikoreksi menjadi 🟡. | Fungsional | Selesai — lihat Riwayat "T-28" |
| T-29 | lmd | `client_demo/apotek.html` memanggil `validate` & `register` langsung dari browser, tidak sesuai Standar Integrasi (no. 1: wajib dari server). Aman selama APOTEK `require_signature = 0`, tetapi demo ini akan berhenti bekerja bila APOTEK diwajibkan tanda tangan. | Konsistensi / demo | Selesai — lihat Riwayat "T-29" |
| T-31 | software-showcase | Admin Showcase menyimpan katalog hanya di `localStorage` browser admin; halaman publik membaca `data/products.js`. Mengubah `lmdAppCode` di admin tidak berpengaruh ke toko sampai file diekspor & diganti manual. | Fungsional | Selesai — lihat Riwayat "Pembayaran otomatis + simulasi dari nol" |
| T-32 | lmd | Persiapan simulasi dari nol: (a) `migrate.php` membuat ulang aplikasi MINIPOS setiap dijalankan bila datanya dihapus; (b) aplikasi tidak bisa diubah setelah dibuat (launch URL salah = buntu); (c) `require_signature` tidak bisa diatur dari dashboard. | Operasional | Selesai — lihat Riwayat "Pembayaran otomatis + simulasi dari nol" |
| T-30 | sample-mini-pos | Belum ada halaman kelola produk: setiap toko hanya punya 6 produk contoh, harga tidak bisa diubah, produk tidak bisa ditambah (tercatat 📋 di entri T-28). | Fungsional | Selesai — lihat Riwayat "T-30" |

---

## Riwayat

### 2026-10-31 — Pembayaran otomatis (simulator) + persiapan simulasi integrasi dari nol
**Jenis:** Perubahan kode + docs
**Repo:** lmd, software-showcase (commit `6664620`)

Pembayaran otomatis (rencana di entri "Analisis: pembayaran otomatis", tahap 1):
- **LMD:** tabel `payments` (migrasi `2026_10_31_payments`); `api/config/payment.php` (provider `simulator`, secret dev hardcode, override `payment.local.php`); adapter `api/helpers/payment_providers.php` (antarmuka `PaymentProvider`, `SimulatorPaymentProvider`); `PaymentModel` (buat tagihan saat `order`, webhook idempoten, cek nominal, retry, batal saat keputusan manual); endpoint publik `POST /payments/webhook/{provider}` (rate limit 120/menit); halaman `pay/simulator.php` (bayar / gagal / tagihan baru, CSRF, nonaktif bila `simulator.enabled = false`). `order` & `order_status` kini mengembalikan `payment {status, payment_url}`; tabel Persetujuan Pembayaran menampilkan status tagihan.
- **Showcase:** wizard langkah 3 "Buat Tagihan & Bayar" membuka halaman bayar di tab baru; langkah 4 menampilkan status tagihan (belum dibayar / lunas / gagal / kedaluwarsa) dan otomatis menampilkan link aplikasi setelah lunas. Gambar QRIS dari layanan eksternal (`api.qrserver.com`) dihapus.
- n8n tidak dipakai untuk pembayaran (webhook langsung ke LMD); n8n tetap 📋 untuk notifikasi/pengingat.

Persiapan simulasi dari nol (permintaan pemilik: kosongkan LMD, putus produk Showcase, lalu latihan integrasi):
- **T-31 (Showcase):** `admin/save.php` (wajib login + CSRF header, validasi id/nama/`lmdAppCode`, tulis atomik) menyimpan katalog ke `data/products.local.json` (di-ignore git agar `git pull` tidak bentrok); `data/catalog.php` menyajikan katalog admin atau `products.js` bawaan; halaman publik & admin memuat `data/catalog.php`. Tombol Reset ke Default menghapus katalog admin. Ekspor `products.js` tetap ada sebagai cadangan.
- **T-32 (LMD):** `migrate.php` mencatat migrasi yang sudah jalan di `schema_migrations` (migrasi pengisi data tidak mengulang); tombol **Ubah** aplikasi (`PUT /applications/{id}`: nama, deskripsi, launch URL, tanda tangan; kode terkunci); checkbox *Wajib request bertanda tangan* (default aktif) saat membuat aplikasi. Metode `ApplicationModel::update` lama yang tidak terpakai (bisa mengganti kode) dihapus.
- **`database/reset_data.php`:** mengosongkan semua tabel data LMD kecuali `system_users`, `api_tokens`, `schema_migrations`; menampilkan jumlah baris, wajib ketik `KOSONGKAN`.
- **`docs/SIMULASI_INTEGRASI.md`:** panduan langkah demi langkah + tabel uji & masalah umum; dirujuk dari `STANDAR_INTEGRASI.md` §6.

Verifikasi (lingkungan cloud, DB uji di-backup dulu):

| Skenario | Hasil |
|---|---|
| Webhook tanpa tanda tangan / nominal salah / tagihan tak dikenal / provider tak aktif | 401 / 409 / 404 / 404 |
| Bayar di simulator | Tagihan `paid`, pesanan `active`, `license_key` + `app_url` di `order_status`; log `payment_paid` → `approve_order` |
| Webhook yang sama dikirim ulang | 200 `duplicate: true`, tidak diproses dua kali |
| Gagal → tagihan baru → bayar | Tagihan lama `failed`, baru `paid`; retry kedua ditolak "Masih ada tagihan aktif" |
| CSRF salah di halaman bayar | Ditolak |
| Admin menolak pesanan lalu customer membuka halaman bayar | Tagihan "dibatalkan", tombol bayar tidak ada |
| Wizard Showcase (Playwright) | Tab bayar terbuka → status "BELUM DIBAYAR" → bayar → layar sukses + URL aplikasi |
| Admin Showcase: hapus semua `lmdAppCode` lalu hubungkan lagi | Halaman publik: 0 tombol Berlangganan → 1; id tidak valid ditolak; tanpa login 401 |
| **Simulasi dari nol:** `reset_data.php` → `migrate.php` | 15 tabel dikosongkan, admin tetap; MINIPOS **tidak** dibuat ulang |
| Daftar MINIPOS lewat form dashboard | Checkbox tanda tangan default aktif → `require_signature = 1`; Ubah nama berhasil, kode terkunci |
| 4 modul + 2 paket → Showcase memuat paket Starter & Pro | ✅ |
| Pesan "Toko Simulasi" → bayar → buka aplikasi (DB `mini_pos` dihapus dulu) | Setup admin → kasir; menu Kasir, Laporan, Produk, Kelola User terbuka, Stok & Multi Outlet 🔒; transaksi Rp 19.980; instalasi aktif dengan secret |

Catatan: halaman bayar memanggil webhook LMD lewat host yang sama dengan yang dibuka browser; bila LMD tidak bisa memanggil dirinya sendiri (host/port berbeda), simulator menampilkan "Webhook ditolak LMD".

### 2026-10-31 — Analisis: pembayaran otomatis (rencana, belum ada kode)
**Jenis:** Analisis / usulan
**Repo:** lmd, software-showcase

- Kondisi: payment gateway belum dipilih, n8n belum terpasang. Persetujuan pesanan masih manual (`approve_order` oleh admin; token API `lmdtok_…` sudah tersedia).
- Usulan: n8n **tidak wajib** untuk pembayaran. Webhook gateway sebaiknya langsung ke LMD (`POST /payments/webhook/{provider}`) dengan verifikasi tanda tangan, lalu memanggil logika `approve_order` yang sama. Lapisan *adapter* per provider membuat pilihan vendor bisa ditunda.
- Tahap 1 (📋): provider **simulator** (halaman bayar tiruan + webhook bertanda tangan) agar alur Showcase → bayar → lisensi aktif otomatis bisa diuji end-to-end tanpa akun vendor.
- Tahap 2 (📋): adapter Midtrans (Snap) atau Xendit (Invoice); keduanya punya sandbox gratis, QRIS/VA/e-wallet, dan butuh verifikasi usaha (KYC) untuk production.
- n8n (📋): untuk otomasi non-kritis — notifikasi email/WhatsApp saat lisensi aktif, pengingat sebelum kedaluwarsa, laporan berkala.

### 2026-10-31 — Verifikasi lokal T-29 (PR LMD #11)
**Jenis:** Verifikasi (tanpa perubahan kode)
**Repo:** lmd — dilakukan AI lokal di XAMPP

- (a) License key demo bawaan `LIC-APO-CRIT…` ada di DB lokal tetapi berstatus **expired** → LMD menjawab "License status is expired.", badge "TIDAK VALID" (perilaku benar).
- (b) Auto-Register → `LIC-APOTEK-8D908CD4DAFFBC0D31BEBFF4`, valid, modul DASHBOARD/MASTER_OBAT/INVENTORY/POS_KASIR; RESEP_DOKTER & BPJS_INTEGRATION terkunci (sesuai APO_BASIC).
- (c) Heartbeat lewat `client_demo/api.php?action=validate`, tidak ada request browser langsung ke `api/index.php/licenses/...`.
- (d) `error.log` Apache: tidak ada error PHP baru.
- Catatan cross-check: (1) AI lokal melaporkan otomasi browsernya gagal (`ECONNREFUSED 127.0.0.1:52924`) dan pengujian dilakukan "langsung ke endpoint server", sehingga pengamatan tampilan badge dan tab Network (a–c) kemungkinan **disimpulkan dari respons HTTP, bukan dilihat di browser**. Bagian browser sudah diuji di lingkungan cloud (Playwright: 0 request langsung ke LMD), jadi risiko kecil; pemilik proyek disarankan membuka demo sekali secara manual. (2) Keluaran `git log -1 --oneline` yang diminta kembali tidak disertakan (seharusnya LMD `dbcf919`, mini-pos `1abf8a1`).

### 2026-10-31 — T-29: demo client Apotek memanggil LMD dari server
**Jenis:** Perubahan kode + docs
**Repo:** lmd

- `client_demo/apotek.html` tidak lagi memanggil `licenses/validate` & `licenses/register` langsung dari browser (dan tidak lagi memakai URL `http://localhost:8080` hardcode). Browser memanggil `client_demo/api.php` di host yang sama (`action=state|validate|register`, POST wajib `application/json`).
- `client_demo/api.php` meneruskan request ke LMD dari server, memeriksa `app_code === APOTEK`, dan **menandatangani request** (HMAC, T-22) bila LMD menerbitkan `instance_secret`. Secret disimpan di server (sesi PHP demo) dan disamarkan di JSON yang ditampilkan ke browser.
- `client_demo/lmd-client.php` ditambahkan sesuai Standar Integrasi (no. 1–3): `app_code`, URL API (otomatis host yang sama), prefix instance key.
- Vue di demo dipatok ke versi `3.4.38` (build produksi), bukan `vue@3` mengambang.
- Batasan demo: status instalasi disimpan di sesi browser, jadi membuka demo di browser lain = instalasi baru tanpa secret. Aplikasi Apotek sungguhan wajib menyimpannya di database/.env server seperti Mini POS. `STANDAR_INTEGRASI.md` §7 diperbarui: client Apotek 🟡 (hanya demo).

Verifikasi (lokal): `state` → identitas default; POST form biasa → 405; `register` → lisensi APO_BASIC baru, `valid: true`, modul DASHBOARD/MASTER_OBAT/INVENTORY/POS_KASIR; APOTEK diubah sementara ke `require_signature = 1` → `register` + validasi pertama `signature: issued` (secret tidak sampai ke browser), validasi berikutnya `signature: verified`; flag dikembalikan ke 0. Browser (Playwright): halaman demo terisi dari server, **0** request langsung dari browser ke API LMD. License key default demo (`LIC-APO-CRIT…`) hanya ada bila DB berisi `seed.sql`; tanpa itu badge menunjukkan "TIDAK VALID" sampai tombol Auto-Register dipakai.

### 2026-10-31 — Verifikasi lokal T-30 (PR Mini POS #7, LMD #10)
**Jenis:** Verifikasi (tanpa perubahan kode)
**Repo:** sample-mini-pos — dilakukan AI lokal di XAMPP, toko `tokobaruminipos.pos.local` (MPOS_STARTER)

- (a) Menu **Produk** tampil untuk admin.
- (b) "Roti Bakar Keju" Rp 15.000 (kategori Camilan) ditambahkan dan langsung tampil di Kasir.
- (c) Dijual 1: `TRX-20261031-000002`, Rp 15.000 + PPN Rp 1.650 = Rp 16.650. Harga diubah ke Rp 17.000; Laporan tetap mencatat Rp 15.000 / Rp 16.650.
- (d) "roti bakar keju" (huruf kecil) ditolak: `Produk "roti bakar keju" sudah ada.`
- (e) Nonaktif → hilang dari Kasir; aktif kembali → tampil lagi.
- (f) `error.log` Apache: tidak ada error PHP baru.
- Catatan: laporan **tidak menyertakan** keluaran `git log -1 --oneline` yang diminta (seharusnya mini-pos `1abf8a1`, LMD `006cd00`). Hasil fungsional a–e hanya mungkin dengan kode T-30, jadi versi terpasang dianggap benar; hash tetap perlu dikonfirmasi di sinkron berikutnya.

### 2026-10-31 — T-30: Kelola Produk di Mini POS
**Jenis:** Perubahan kode + docs
**Repo:** sample-mini-pos (commit `8da3bac`), lmd (docs)

- Halaman baru `produk.php` (menu **Produk**, khusus admin, bagian dari modul KASIR): tambah produk, ubah nama/kategori/ikon emoji/harga, nonaktifkan/aktifkan. Produk tidak dihapus permanen karena dirujuk transaksi; produk nonaktif hilang dari kasir dan checkout-nya ditolak.
- Validasi di `ProductRepository`: nama 2–150 karakter dan **unik per toko** (tanpa beda huruf besar/kecil; laporan produk terlaris dikelompokkan per nama), harga 1 – 100.000.000, ikon maks 8 code point.
- Stok awal hanya diisi bila toko punya modul STOK; tanpa modul itu produk baru berstok 0 dan diatur di halaman Stok setelah upgrade.
- Mengubah nama/harga tidak mengubah transaksi lama (`sale_items` menyimpan salinan nama & harga).

Verifikasi (lokal): Starter tambah "Roti Bakar Keju" Rp 15.000 → tampil di kasir, checkout 2 × = Rp 33.300; ubah nama & harga → `sale_items` lama tetap "Roti Bakar Keju" Rp 15.000; nama duplikat (beda huruf besar/kecil), nama 1 karakter, harga 0 → ditolak dan isian form dipertahankan; ganti nama ke produk yang sudah ada → ditolak; nonaktif → hilang dari kasir, checkout 422; ubah produk milik toko lain → "Produk tidak ditemukan"; Pro tambah produk dengan stok awal 7 → tersimpan 7; role kasir membuka `produk.php` → dialihkan ke Kasir, menu Produk tidak tampil.

### 2026-10-31 — Verifikasi lokal T-28 (PR Mini POS #6, LMD #9)
**Jenis:** Verifikasi (tanpa perubahan kode)
**Repo:** sample-mini-pos, lmd — dilakukan AI lokal di XAMPP

- Sinkron ke `main`: LMD `b9fcf8d`, Mini POS `d572300`. Tabel `products`, `sales`, `sale_items` terbentuk otomatis saat Mini POS dibuka (tanpa migrasi manual).
- Toko `tokobaruminipos.pos.local` (MPOS_STARTER, modul `KASIR, LAPORAN`): menu Kasir, Laporan, Kelola User terbuka; Stok dan Multi Outlet bergembok 🔒.
- Transaksi Kopi Espresso Blend ×1 = Rp 18.000 + PPN Rp 1.980 = **Rp 19.980**, nomor `TRX-20261031-000001`; muncul di Laporan (omzet, produk terlaris, transaksi terakhir). `SELECT COUNT(*) FROM mini_pos.sales` = 1.
- `stok.php` dibuka langsung: 403 "Modul Stok tidak termasuk paket Anda" + tombol ke Showcase.
- Skenario stok habis (paket Pro) tidak diuji lokal karena toko lokal berpaket Starter; sudah diuji di lingkungan cloud (entri T-28).
- Catatan cross-check: hash commit yang dilaporkan cocok, tetapi **judul commit** di laporan AI lokal (`docs(lmd): arsitektur & ekosistem level 10 …`, `feat(minipos): transaksi kasir …`) tidak sesuai GitHub; judul sebenarnya `Merge pull request #9 …` dan `Merge pull request #6 …`. Kode yang terpasang benar (hash cocok); untuk laporan berikutnya AI lokal diminta menyalin keluaran `git log -1 --oneline` apa adanya.

### 2026-10-30 — T-28: Mini POS menegakkan `modules` dari lisensi LMD
**Jenis:** Perubahan kode + docs
**Repo:** sample-mini-pos (commit `8346386`), lmd (docs)

Masalah: Mini POS menyimpan `modules` dari LMD tetapi tidak memakainya, dan fitur di baliknya belum ada (kasir menghitung di browser, produk hardcode di `app.js`, transaksi tidak tersimpan). Starter dan Bisnis mendapat aplikasi yang sama.

Perubahan (sample-mini-pos):
- **Data di server:** tabel baru `products`, `sales`, `sale_items` (per tenant, `database/schema.sql`; dibuat otomatis oleh `db()` juga untuk database lama). Katalog contoh 6 produk dibuat otomatis untuk toko yang belum punya produk (`lib/ProductRepository.php`).
- **Checkout di server (modul KASIR):** `api.php?action=checkout` (sesi login + header `X-CSRF-Token`). Browser hanya mengirim `id` + `qty`; harga, PPN 11%, total dihitung ulang dari DB dan disimpan (`lib/SaleRepository.php`). Produk tenant lain / qty tidak valid → 422. Struk memakai nomor `TRX-YYYYMMDD-xxxxxx` dari server. License key tidak lagi dikirim ke JavaScript.
- **Penguncian modul:** `APP_MODULES`, `has_module()`, `require_module()` di `lib/bootstrap.php`. Menu dibangun dari `APP_MODULES`; modul di luar paket tampil 🔒 dan halamannya 403 "tidak termasuk paket Anda" (`views/locked.php`) dengan tautan ke Showcase untuk admin. Kasir (role) tidak melihat menu admin.
- **Laporan (modul LAPORAN, admin):** `laporan.php` — omzet, transaksi, rata-rata, PPN, produk terlaris, per kasir, per hari, transaksi terakhir; preset Hari ini/7/30 hari + rentang tanggal (maks 1 tahun).
- **Stok (modul STOK, admin):** `stok.php` — atur stok & stok minimum. Dengan modul STOK, checkout mengunci baris produk (`FOR UPDATE`), menolak penjualan melebihi stok, dan mengurangi stok; kartu produk menampilkan sisa stok dan "Stok habis". Tanpa modul STOK, stok diabaikan.
- **Multi Outlet:** `outlet.php` sudah dikunci per paket; isinya "segera hadir" (📋, tidak diklaim berjalan).

Docs (lmd): `STANDAR_INTEGRASI.md` 4.2 no. 5 kembali ✅; `EKOSISTEM_CARA_KERJA.md` (komponen Mini POS, tabel paket → menu, status); tab Buku Panduan Ekosistem di `index.html`.

Verifikasi (lokal, MariaDB + PHP built-in server, request lisensi bertanda tangan):

| Skenario | Hasil |
|---|---|
| Starter (KASIR, LAPORAN): Kasir / Laporan / Kelola User | 200 |
| Starter: Stok, Multi Outlet | 403 "tidak termasuk paket" |
| Checkout dengan `price: 1` disisipkan | Diabaikan; total dihitung dari DB (58.000 + PPN 6.380) |
| CSRF salah / tanpa login / GET / qty 0, 1000 / keranjang kosong | 419 / 401 / 404 / 422 / 422 |
| Starter: qty 60 > stok 50 | Diterima, stok tidak berubah (stok tidak dilacak) |
| Pro (+STOK): stok di-set 3 → jual 2 → jual 2 lagi (dua baris produk sama) | 200 (sisa 1) → 422 "Stok tidak cukup: … (sisa 1)" |
| Stok negatif lewat form | Ditolak "Stok harus angka 0 – 1.000.000" |
| Role kasir di paket Pro: Laporan / Stok / Outlet / Kelola User | Redirect ke Kasir; checkout kasir tercatat di laporan "Per Kasir" |
| Modul di LMD diubah (STOK dicabut, MULTI_OUTLET ditambah) lalu heartbeat | Stok 403, `trackStock: false`, Multi Outlet "segera hadir" 200; dikembalikan lagi setelah tes |
| Browser (Playwright): klik kartu "Stok habis" | Tidak masuk keranjang |

Catatan: tenant uji lama berisi modul APOTEK (license key `LIC-APOTEK-…`) sudah ditolak `app_mismatch` sejak Standar Integrasi v1, jadi tidak terdampak penguncian baru. Kelola produk (tambah/ubah harga) dicatat sebagai 📋 rencana.

### 2026-10-31 — Inventaris pekerjaan tersisa
**Jenis:** Analisis (tanpa perubahan kode)
**Repo:** lmd, sample-mini-pos

- Atas pertanyaan "apakah masih ada pekerjaan yang belum dikerjakan", kode diperiksa ulang terhadap `STANDAR_INTEGRASI.md` dan roadmap.
- Ditemukan **T-28** (modules tidak mengunci fitur di Mini POS; klaim di standar dikoreksi) dan **T-29** (demo client Apotek memanggil API dari browser).
- Roadmap yang belum dikerjakan dicatat sebagai 📋 di jawaban kepada pemilik proyek: churn risk scoring, ekspor laporan, filter tanggal (`REKOMENDASI_DECISION_SUPPORT.md`); integrasi n8n + payment gateway (token sudah siap); kuota *concurrent sessions*; HTTPS di production.

### 2026-10-31 — Verifikasi lokal paket keamanan (PR LMD #8, Mini POS #5, Showcase #5)
**Jenis:** Verifikasi (tanpa perubahan kode)
**Repo:** lmd, sample-mini-pos, software-showcase

- XAMPP lokal di `269d5c4` / `ddc4de5` / `03a3bfb`; backup `_backup_20261031_0006_central_saas.sql` (67.690 byte) & `_backup_20261031_0006_mini_pos.sql` (4.820 byte).
- `migrate.php` run 1: `system_users`, `rate_limits`, `api_tokens`, `instance_signatures` **DITERAPKAN** + pesan "Belum ada akun admin LMD"; run 2 semua "sudah terpasang".
- Tanpa login: dashboard menampilkan layar login + petunjuk CLI; `GET /api/licenses` → 401; `GET /api/packages?app_code=MINIPOS` → 200; Admin Showcase menampilkan "Password admin belum dibuat"; `admin/app.php` → 302 ke login.
- Mini POS `tokobaruminipos.pos.local` tetap terbuka normal dan otomatis menerima instance secret (*trust on first use* untuk toko lama berjalan). `apotek-sehat.pos.local` tanpa secret karena memakai lisensi APOTEK (ditolak `app_mismatch` sejak Standar Integrasi v1, sesuai desain).
- Tidak ada error PHP baru.
- Pemilik proyek membuat akun admin LMD sendiri lewat CLI dan berhasil login ke dashboard (tampilan nama admin + tombol Keluar). Password tidak dicatat.

### 2026-10-31 — Paket keamanan sebelum production (T-05, T-04 token, T-22, T-07)
**Jenis:** Perubahan kode + migrasi database
**Repo:** lmd, sample-mini-pos, software-showcase

**Keputusan pemilik proyek:** T-22 memakai secret **per instalasi** (bukan per aplikasi), diwajibkan per aplikasi; T-07 memakai login PHP sendiri di Showcase. Sinkronisasi lokal dilakukan sekaligus setelah semua selesai.

**T-05 — rate limit (LMD)**
- Tabel `rate_limits` + migrasi `2026_10_31_rate_limits`; `api/helpers/rate_limit.php` (jendela tetap 60 detik, 429 + `Retry-After`).
- Batas per IP: `validate` 600 (aplikasi hosted memvalidasi banyak toko dari satu IP), **license key tak dikenal 20**, `register` 10, `order` 10, `order_status` 120, `reset_token` 20, `auth/login` 10, `packages` 120.
- **T-26:** `getClientIp` tidak lagi memercayai `X-Forwarded-For` kecuali dari `TRUSTED_PROXIES` (default kosong).
- Desain awal (validate 60/menit) diubah setelah analisis: akan memblokir aplikasi hosted dengan banyak toko.

**T-04 sisa — token API otomasi (LMD)**
- Tabel `api_tokens` (hash SHA-256, scope, `last_used_at`, `revoked_at`) + migrasi `2026_10_31_api_tokens`; `ApiTokenModel`.
- `require_admin()` menerima `Authorization: Bearer lmdtok_…` hanya untuk endpoint dalam scope `orders` (`pending_orders`, `approve_order`, `reject_order`, `order_status`).
- CLI `admin.php token-create "<nama>" | token-list | token-revoke <id>`; token ditampilkan sekali.
- `api/.htaccess` + fallback `getallheaders()` agar header `Authorization` sampai ke PHP di Apache.

**T-22 — request bertanda tangan (LMD + Mini POS)**
- `applications.require_signature` (MINIPOS = 1), `application_instances.secret` + migrasi `2026_10_31_instance_signatures` (instalasi baru via `registerApplication`).
- `validate`: instalasi tanpa secret menerima `instance_secret` sekali (*trust on first use*); berikutnya wajib `X-LMD-Timestamp` + `X-LMD-Signature` = HMAC-SHA256(secret, ts + "\n" + body), toleransi 5 menit. Reason `signature_required` / `signature_invalid` / `signature_expired`. Secret tidak pernah dicatat di log dan tidak dikirim ke API admin (`has_secret` saja).
- `POST /instances/{id}/reset_secret` (admin) + tombol **Reset secret** di Licenses → Detail.
- Mini POS: kolom `tenants.instance_secret` (ditambah otomatis di DB lama), `LicenseGate` menyimpan & menandatangani; pesan untuk `signature_*`. Tombol heartbeat dipindah ke server (`heartbeat.php`) karena browser tidak boleh memegang secret.
- `STANDAR_INTEGRASI.md` naik ke **v1.1** (bagian 4.3, reason baru, endpoint publik/admin, checklist). Contoh `lmd-client.php` tidak lagi menyebut App ID/secret per aplikasi.

**T-07 — login Admin Showcase**
- Password `nexus2024` dihapus dari `admin.js`; **T-27:** status login tidak lagi di `sessionStorage`; `admin/index.html` statis dihapus.
- `admin/index.php` (login + logout ber-CSRF), `admin/app.php` (halaman admin hanya untuk sesi login), `admin/auth.php` (sesi HttpOnly + SameSite=Strict, idle 2 jam, kunci 15 menit per IP setelah 5 gagal), `admin/set-password.php` (CLI, hash di `credentials.local.php` yang di-ignore git), `admin/.htaccess`.

**Verifikasi** (MariaDB + PHP server, browser)
- Rate limit: 100 validasi sah dari satu IP lolos; 20 tebakan license key → 429; `X-Forwarded-For` palsu tidak menembus; login 10/menit.
- Token: approve order nyata via token; endpoint di luar scope, token palsu, token dicabut → 403.
- Tanda tangan (15 skenario API): issued → unsigned ditolak → signed verified → salah/kedaluwarsa/tebakan ditolak → secret tidak bocor (API admin & log) → reset secret (wajib login) → secret lama mati, baru berlaku → APOTEK `not_required`.
- Browser: aktivasi Mini POS menyimpan secret; heartbeat via server valid; perangkat lain `verified`; **toko lama tanpa secret otomatis mendapat secret** (tidak terkunci); secret hilang → halaman penjelasan → admin Reset secret → masuk lagi.
- Admin Showcase: tanpa password bawaan; `nexus2024` & trik `sessionStorage` gagal; login/logout; kunci setelah 5 gagal.
- Migrasi: DB lama & instalasi baru (`schema` → `seed` → `migrate`) memberi `require_signature = 1` untuk MINIPOS.

**Temuan baru:** T-26, T-27 (keduanya selesai).

### 2026-10-31 — T-04 (bagian 1): login admin dashboard LMD
**Jenis:** Perubahan kode + tabel baru
**Repo:** lmd

**Keputusan pemilik proyek:** sementara satu akun admin.

**Perubahan**
- Tabel `system_users` (ERD level 1-4 "System Users") di `schema.sql` + migrasi `2026_10_31_system_users`.
- `database/admin.php` (CLI): `create <email> "<nama>"` (menolak akun kedua), `reset-password <email>` (sekaligus membuka kunci), `list`. Password ditanya interaktif atau via `LMD_ADMIN_PASSWORD`; minimal 10 karakter. Akun sengaja **tidak** bisa dibuat lewat web agar tidak ada yang "mendaftar duluan" di server yang terbuka.
- `api/helpers/auth.php`: sesi `LMDSESSID` (`HttpOnly`, `SameSite=Strict`, path folder LMD), idle 2 jam, `session_regenerate_id` saat login/logout. `SystemUserModel`: bcrypt, kunci 15 menit setelah 5 kali gagal, waktu respons sama untuk email tak terdaftar.
- `AuthController`: `GET /auth/me`, `POST /auth/login`, `POST /auth/logout`; setiap percobaan login dicatat `admin_login`.
- `api/index.php`: daftar putih endpoint publik (`validate`, `register`, `order`, `order_status`, `reset_token`, `packages?app_code`, `auth/*`); selain itu `require_admin()` → 401. Preflight `OPTIONS` dijawab sebelum pemeriksaan.
- Dashboard: layar login (petunjuk CLI bila belum ada akun / lupa password), nama admin + tombol **Keluar** di topbar, respons 401 di tengah sesi otomatis kembali ke layar login.
- Docs: `PANDUAN_PENGGUNAAN.md` (lapis keamanan 1, catatan n8n), `STANDAR_INTEGRASI.md` (endpoint publik), `EKOSISTEM_CARA_KERJA.md`, tab Ekosistem.

**Belum (tetap di T-04):** token API untuk otomasi (n8n memanggil `approve_order`). App ID + secret aplikasi client ditangani di T-22.

**Verifikasi**
- CLI: password pendek ditolak; akun dibuat; akun kedua ditolak; `list`; `reset-password` membuka kunci.
- API tanpa sesi: 9 endpoint admin → 401; endpoint publik tetap 200/403/404 sesuai isi. Login salah → 401; login benar → cookie `LMDSESSID; HttpOnly; SameSite=Strict`; dengan sesi → 200; setelah logout → 401. 5 kali salah → terkunci 15 menit. Audit `admin_login` tercatat.
- Browser: layar login sebelum masuk; password salah ditolak; login → dashboard dengan 8 grafik; reload tetap login; cookie dihapus → kembali ke login "Sesi berakhir"; tombol Keluar.
- Regresi tanpa login admin: Showcase memuat paket & mengirim order, polling melihat approve, Mini POS memvalidasi lisensi. Tidak ada error JS.

### 2026-10-31 — Verifikasi lokal T-21 (PR LMD #7, Mini POS #4)
**Jenis:** Verifikasi (tanpa perubahan kode)
**Repo:** lmd, sample-mini-pos

- XAMPP lokal di `e611320` / `00f05e5`; backup `_backup_20261030_2314_central_saas.sql` (63.877 byte) dan `_backup_20261030_2314_mini_pos.sql` (4.569 byte).
- `migrate.php`: run 1 `2026_10_31_admin_reset_tokens` **DITERAPKAN**, run 2 semua "sudah terpasang".
- Tenant uji `tokobaruminipos.pos.local`: (a) link reset terbit dari Licenses → Detail; (b) redirect ke `reset.php`, token hilang dari address bar; (c) konfirmasi berbeda ditolak; (d) password admin diganti, login dengan password baru berhasil; (e) link yang sama ditolak "sudah dipakai" (HTTP 403); (f) teks bantuan login benar; (g) tidak ada error PHP baru.
- Password baru & token uji sengaja **tidak** dicatat di repo (repo publik).

### 2026-10-31 — T-21: reset password admin aplikasi client dari dashboard LMD
**Jenis:** Perubahan kode + tabel baru
**Repo:** lmd, sample-mini-pos

**Keputusan pemilik proyek:** pemulihan lewat dashboard LMD (tanpa server email).

**LMD**
- Tabel `admin_reset_tokens` (hash SHA-256 token, `expires_at`, `used_at`) di `schema.sql` + migrasi `2026_10_31_admin_reset_tokens`.
- `AdminResetModel`: `issue` (token 64 hex, berlaku 24 jam, menghapus token lama yang belum dipakai, hanya untuk lisensi `active`; `reset_url` dari path `launch_url`, null untuk on-premise) dan `check` (periksa / hanguskan, `FOR UPDATE`).
- API `POST /licenses/{id}/admin_reset`, `POST /licenses/reset_token`. Audit `admin_reset_issued`, `admin_reset_used`, `admin_reset_checked` (gagal).
- Modal **Licenses → Detail**: kotak *Reset password admin aplikasi* dengan tombol **Buat link reset** dan **Salin**; link hanya ditampilkan sekali.

**Mini POS**
- `index.php` meneruskan `?reset_token=` ke `reset.php`; token disimpan di sesi dan dihapus dari URL.
- `reset.php` + `views/reset.php`: pilih akun admin aktif, password baru; validasi lokal dulu, baru token dihanguskan di LMD, lalu password disimpan (kunci login ikut dibuka).
- `LicenseGate::checkResetToken` (helper `postLmd` dipakai bersama `validate`); `UserRepository::listActiveAdmins`, `checkPassword`.
- Petunjuk "Lupa password" di halaman login dibedakan untuk kasir dan admin.

**Docs:** `STANDAR_INTEGRASI.md` (kewajiban client no. 9 + 2 endpoint), `EKOSISTEM_CARA_KERJA.md`, tab Ekosistem.

**Verifikasi** (MariaDB + PHP server, browser)
1. Dashboard menerbitkan link `…/mini-pos/?tenant=tokosari.pos.local&reset_token=…`.
2. Link lama tidak berlaku setelah link baru dibuat.
3. Link valid → `reset.php`, token hilang dari URL, form tampil.
4. Konfirmasi password salah → ditolak, token **tidak** hangus.
5. Simpan → login dengan flash sukses; password lama ditolak, password baru berhasil.
6. Link dipakai ulang → "sudah dipakai"; link kedaluwarsa → "kedaluwarsa"; token lisensi lain di toko ini → "tidak dikenal".
7. Audit log lengkap; tidak ada error JS. Migrasi run 1 "DITERAPKAN", run 2 "sudah terpasang".

### 2026-10-31 — Verifikasi lokal T-25 (PR LMD #6)
**Jenis:** Verifikasi (tanpa perubahan kode)
**Repo:** lmd

- XAMPP lokal di `929c9fe`; backup `_backup_20261030_2259_central_saas.sql` (63.936 byte).
- `migrate.php` run 1: `2026_10_31_cleanup_deleted_package_modules` **DITERAPKAN** (sisa `TEST_FUTURE` dibersihkan); run 2: semua "sudah terpasang".
- SQL: paket `MPOS_BISNIS` → `isi_paket = 4`, `modul_terhapus = 0`. T-25 terkonfirmasi selesai di lingkungan lokal.

### 2026-10-31 — T-25: modul terhapus keluar dari isi paket
**Jenis:** Perubahan kode + pembersihan data
**Repo:** lmd

- `ModuleModel::delete` kini (dalam transaksi) men-soft-delete modul **dan** menghapusnya dari `package_modules`. Riwayat di `subscription_modules` / `license_modules` dibiarkan; `validate` sudah menyaring modul terhapus (T-13).
- `migrate.php`: `2026_10_31_cleanup_deleted_package_modules` membersihkan sisa lama (di PC lokal: `TEST_FUTURE` di paket MPOS_BISNIS). Kolom nama migrasi dilebarkan agar output rapi.
- Verifikasi: paket MPOS_BISNIS berisi 6 baris (2 modul terhapus) → migrate run 1 "DITERAPKAN" → 4 baris; run 2 "sudah terpasang". Modul baru dibuat lalu dihapus via API → 0 baris di `package_modules`, riwayat subscription tetap.

### 2026-10-30 — Verifikasi lokal T-13 + perapian (PR LMD #5, Showcase #4)
**Jenis:** Verifikasi (tanpa perubahan kode)
**Repo:** lmd, software-showcase

- XAMPP lokal di `b042761` / `91237e7`; backup `_backup_20261030_2211_central_saas.sql` (63.723 byte).
- `migrate.php` 2x: semua "sudah terpasang" (tidak ada modul lama yang perlu disusulkan).
- (a) label "YA (modul baru otomatis)" / "TIDAK" benar; (b) garis tab analitik tampil (T-11); (c) modul `TEST_FUTURE` untuk MINIPOS → API `future_module_granted {packages: 1, subscriptions: 0, licenses: 0}` karena belum ada lisensi MPOS_BISNIS; hanya paket MPOS_BISNIS yang menerima; modul lalu dihapus → memunculkan **T-25**; (d) kartu keamanan no. 3 & 5 sesuai T-14; (e) tidak ada error PHP baru.

### 2026-10-30 — T-13: modul baru otomatis (future_module_access)
**Jenis:** Perubahan kode
**Repo:** lmd

**Aturan** (ERD level 9, bagian 17): modul yang **dibuat setelah** sebuah paket/subscription bertanda `future_module_access = 1` otomatis diberikan tanpa customer upgrade. Modul yang sudah ada saat paket dibuat tidak ditambahkan, agar isi paket yang dipilih admin tidak berubah diam-diam.

**Perubahan**
- `ModuleModel::syncFutureModules(PDO, ?moduleId)`: `INSERT IGNORE` ke `package_modules`, `subscription_modules` (`source = 'future_module'`, subscription `pending/active/suspended`), dan `license_modules` (lisensi selain `revoked`); setiap lisensi yang menerima modul dicatat `future_module_granted` di `license_logs`.
- `ModuleModel::create` membuat modul + sinkronisasi dalam satu transaksi; API `POST /applications/modules` mengembalikan `future_module_granted {packages, subscriptions, licenses}` dan dashboard menampilkannya.
- `migrate.php`: `2026_10_30_sync_future_modules` (dijalankan setiap migrate, menyusulkan modul yang dibuat di luar dashboard).
- Ditemukan & diperbaiki: `validate` masih mengirim modul yang sudah dihapus (`deleted_at`); kini disaring.
- Label paket "YES (Auto Add)" → "YA (modul baru otomatis)".

**Verifikasi** (MariaDB + PHP server)
- Lisensi MPOS_BISNIS (fitur baru otomatis) & MPOS_PRO dibuat → modul `LOYALTY` dibuat → hanya Bisnis yang menerima (API: 1 paket, 1 subscription, 1 lisensi); Pro tidak berubah.
- Order Bisnis baru setelahnya otomatis berisi `LOYALTY` (via `package_modules`).
- Modul dibuat langsung lewat SQL → `migrate.php` run 1 "DITERAPKAN" (2 subscription Bisnis menerima), run 2 "sudah terpasang".
- Modul dihapus → tidak muncul lagi di `validate`. Audit log `future_module_granted` tercatat.

### 2026-10-30 — Perapian T-08, T-11, T-14
**Jenis:** Perubahan kode kecil + koreksi docs
**Repo:** lmd, software-showcase

- **T-08:** `SecurityHelper::generateActivationKey` tidak lagi menghasilkan `ACT-ACT-…` (sekarang `ACT-` + 16 hex). Sekalian diperbaiki bug prefix huruf kecil yang terhapus (`"pos"` → `ACT-POS-…`). Activation key lama tidak diubah.
- **T-11:** alias token `--border` → `--border-subtle` dan `--muted` → `--text-muted` di `:root`. Garis tab analitik dan teks keterangan kini tampil sesuai tema (terverifikasi di browser).
- **T-14:** istilah disamakan dengan kode di `PANDUAN_PENGGUNAAN.md` dan Buku Panduan dashboard: "Hashing SHA-256" → license key acak `random_bytes()` (96-bit / activation 64-bit); `validation_logs` → `license_logs`; kartu keamanan "ID hardware" → domain + kuota instalasi (T-02); typo "Anomaly Anomaly". `PANDUAN_SHOWCASE.md` §10 tidak lagi memakai istilah "Level 8" (bentrok dengan ERD), merujuk Alur B & `STANDAR_INTEGRASI.md`.

### 2026-10-30 — Verifikasi lokal Standar Integrasi v1 (PR LMD #4, Showcase #3, Mini POS #3)
**Jenis:** Verifikasi (tanpa perubahan kode)
**Repo:** lmd, software-showcase, sample-mini-pos

- XAMPP lokal ditarik ke `fee9457` / `d8a6c48` / `69726d1`. Backup: `_backup_20261030_2107_central_saas.sql` (56.760 byte) dan `_backup_20261030_2107_mini_pos.sql` (4.296 byte).
- `migrate.php` dua kali: semua "sudah terpasang". **Catatan:** run pertama yang dilaporkan seharusnya "DITERAPKAN" untuk dua migrasi baru; kemungkinan migrasi sudah dijalankan sekali sebelum laporan dibuat. Struktur & data akhir sesuai (verifikasi a), jadi tidak ada tindakan perbaikan.
- Hasil: (a) `launch_url` hanya MINIPOS, 3 paket MPOS sesuai; (b) label Hosted/On-premise benar; (c) hanya Mini POS & Apotek yang bisa dipesan; (d) order MINIPOS `SUB-2026-5AF5A9` → `LIC-MINIPOS-…` → "Buat Akun Admin — Toko Mini POS Baru"; (e) order APOTEK `SUB-2026-305C5E` → `app_url` null, layar on-premise tanpa tombol buka; (f) tenant lama `apotek-sehat.pos.local` → "Instalasi Tidak Diizinkan" (`app_mismatch`), sesuai desain; (g) tidak ada error PHP baru.

### 2026-10-30 — Standar Integrasi v1 (LMD ↔ Showcase ↔ aplikasi client)
**Jenis:** Perubahan kode + migrasi database + dokumen standar
**Repo:** lmd, software-showcase, sample-mini-pos

**Keputusan pemilik proyek**
- Menerapkan standar integrasi untuk ketiga sisi.
- Paket Mini POS memakai usulan: `MPOS_STARTER` Rp150.000/bln (2 user, 1 lokasi, KASIR+LAPORAN), `MPOS_PRO` Rp350.000/bln (5 user, 2 lokasi, +STOK), `MPOS_BISNIS` Rp3.500.000/thn (15 user, 5 lokasi, +MULTI_OUTLET, fitur baru otomatis).
- Produk Showcase yang dipetakan: Mini POS → `MINIPOS`, Apotek → `APOTEK`. Produk lain "Segera hadir".

**Temuan saat analisis:** URL "Buka Aplikasi" setelah approve di-hardcode ke `/mini-pos/` (`OrderModel::buildAppUrl`), sehingga produk lain akan membuka Mini POS. Diselesaikan dengan `launch_url` per aplikasi.

**LMD**
- Kolom `applications.launch_url` (template `{tenant}`, `{license_key}`; NULL = on-premise). Form & daftar aplikasi menampilkan/menyimpan field ini.
- `migrate.php`: `2026_10_30_application_launch_url`, `2026_10_30_register_minipos` (fungsi `registerApplication`, idempoten berdasarkan kode; tidak mengubah data yang sudah ada).
- `order_status` / `approve_order` mengembalikan `app_code`, `app_name`, dan `app_url` dari `launch_url` (null untuk on-premise).
- Dokumen baru `docs/STANDAR_INTEGRASI.md` (kontrak v1, tabel `reason`, checklist aplikasi baru). `CLAUDE.md` ketiga repo merujuk standar ini.

**Showcase**
- `products.js`: `lmdAppCode` untuk `mini-pos-express` (MINIPOS) dan `apotek-smart-pos` (APOTEK). Tanpa default `APOTEK` lagi → T-18.
- Produk tanpa `lmdAppCode`: tombol "Segera hadir" nonaktif; modal pemesanan menolak.
- Layar sukses tanpa `app_url`: menampilkan license key + petunjuk instalasi on-premise.
- Admin Showcase: field *Kode Aplikasi di LMD* ikut tersimpan & terekspor.

**Mini POS**
- `lmd-client.php` (app_code `MINIPOS`, `lmd_api_url`, `instance_key_prefix`; override `lmd-client.local.php`). `lmd_api_url` dipindah dari `config.php`.
- `LicenseGate` menolak lisensi aktif dengan `app_code` lain (`app_mismatch`) dan menampilkan penjelasan → T-24.

**⚠️ Dampak pada data lokal:** tenant Mini POS yang dulu diaktifkan dengan lisensi APOTEK (mis. Apotek Sehat Sentosa) akan ditolak (`app_mismatch`). Untuk menguji ulang, pesan paket Mini POS baru dari Showcase.

### 2026-10-29 — Analisis: cara mendaftarkan aplikasi client ke LMD
**Jenis:** Analisis (tanpa perubahan kode)
**Repo:** lmd, sample-mini-pos, software-showcase

- Menjawab pertanyaan pemilik proyek tentang langkah mendaftarkan aplikasi eksternal (contoh Mini POS) ke LMD: (1) daftar aplikasi & modul, (2) buat paket, (3) integrasi `validate` di client, (4) pilih jalur penerbitan lisensi (Showcase order / auto-register / manual), (5) tampilkan di Showcase.
- Temuan: Mini POS belum punya aplikasi sendiri di LMD dan client tidak memverifikasi `app_code` → **T-24**.
- Pertanyaan lanjutan "apakah aplikasi client butuh file khusus": tidak ada file wajib saat ini; LMD mengenali aplikasi dari license key. Diusulkan standar `lmd-client.php` (📋 rencana), yang akan membawa App ID + secret saat T-04/T-22.
- Diagram alur (4 tahap, setup, 3 jalur lisensi, pohon keputusan validasi) ditambahkan ke `EKOSISTEM_CARA_KERJA.md` bagian 5b; versi visual: https://claude.ai/artifact/EAKYuDsC3ZJrsev4NSFqsk.

### 2026-10-24 — T-02: kuota instalasi per paket & penguncian domain
**Jenis:** Perubahan kode + migrasi database
**Repo:** lmd, software-showcase, sample-mini-pos

**Keputusan pemilik proyek**
- Kuota instalasi per paket (bukan 1 lisensi = 1 instalasi).
- Instance lama yang melebihi kuota diputuskan admin secara manual (tidak diblokir otomatis).

**Database** (perlu migrasi pada DB yang sudah ada)
- Kolom baru `packages.max_instances` dan `subscriptions.max_instances` (default 1; seed: APO_BASIC 1, APO_PRO 2, APO_ENT 5). Subscription menyalin nilai paket saat dibuat, seperti `max_users`.
- `database/migrate.php`: migrasi idempoten (cek `INFORMATION_SCHEMA`), dijalankan via CLI. `schema.sql` & `seed.sql` diperbarui untuk instalasi baru.

**LMD**
- `validate` (`LicenseModel::validateLicense`): `instance_key_conflict`, `instance_blocked`, `domain_mismatch` (domain diisi sekali, tidak lagi ditimpa), `instance_limit_reached` (instance `blocked` tidak dihitung). Respons gagal kini membawa `reason`; respons sukses membawa `max_instances`.
- `POST /instances/{id}/status` (`active` / `blocked`), mengaktifkan kembali tunduk pada kuota (409); tercatat di `license_logs`.
- Ketiga jalur pembuatan subscription (order Showcase, auto-register, subscription manual) menyalin `max_instances`. Form & tabel paket punya **Max Instalasi**.
- Dashboard: kolom **Instalasi** `aktif / kuota` (kuning jika melebihi), modal detail dengan **Blokir instalasi / Aktifkan kembali**.
- Docs: `EKOSISTEM_CARA_KERJA.md`, `PANDUAN_PENGGUNAAN.md` (lapis keamanan 2, FAQ 4, bagian 9), tab Ekosistem.

**Showcase:** kartu paket menampilkan kuota lokasi ("1 lokasi toko", "Hingga 5 lokasi / cabang").

**Mini POS:** penolakan instalasi (`domain_mismatch`, `instance_limit_reached`, `instance_blocked`, `instance_key_conflict`) menampilkan halaman "Instalasi Tidak Diizinkan" dengan penjelasan, bukan dilempar ke Showcase.

**Verifikasi** (MariaDB + PHP server)
- Migrasi: run 1 "DITERAPKAN", run 2 "sudah terpasang"; subscription lama terisi kuota sesuai paket. Instalasi baru dari `schema.sql` + `seed.sql` benar.
- API: instance resmi → valid; domain lain → `domain_mismatch`; instalasi baru di Basic → `instance_limit_reached`; Pro: instalasi ke-2 valid, ke-3 ditolak; blokir cabang-2 → `instance_blocked`, slot terbuka untuk cabang-3; aktifkan cabang-2 saat penuh → 409; instance key lisensi lain → `instance_key_conflict`; status tidak valid → 400; instance tidak ada → 404. Semua tercatat di `license_logs`.
- Browser: kartu Showcase menampilkan kuota lokasi; Mini POS dari domain lain → "Instalasi Tidak Diizinkan"; tabel paket & lisensi menampilkan kuota; modal blokir/aktifkan berfungsi. Tidak ada error JS.

**Temuan baru:** T-22, T-23 (selesai).

### 2026-10-24 — Sinkronisasi lokal setelah PR #2
**Jenis:** Verifikasi (tanpa perubahan kode)
**Repo:** lmd

- PR #2 di-merge ke `main` (`cde354b`). XAMPP lokal ditarik ke commit yang sama oleh AI lokal.
- `api/.htaccess` lokal (untracked) diganti versi repo; aturan `RewriteRule` identik, hanya beda komentar. File sementara dihapus; `.htaccess` kini ter-track.
- Verifikasi di PC: modal **Licenses → Detail** menampilkan instance (Apotek Sehat Sentosa, `apotek-sehat.pos.local`, `INST-STORE-21C187A5`); `GET /api/licenses/pending_orders` tanpa `index.php` → 200 JSON. Konfirmasi T-15 berjalan di Apache XAMPP.

### 2026-10-24 — Rekonsiliasi WIP lokal `License-Management-Dashboard`
**Jenis:** Analisis + perubahan kode
**Repo:** lmd

**Latar belakang**
- Saat sinkronisasi `main` ke XAMPP lokal, AI lokal menemukan 6 file LMD yang dimodifikasi tetapi belum pernah di-commit, plus `api/.htaccess` yang untracked.
- Backup penuh: `C:\xampp\htdocs\_backup_20261024_0047\` (folder ketiga aplikasi + dump `central_saas.sql`).
- Perubahan lokal diamankan ke branch `origin/local-wip-20261024` (commit `0154bca`), lalu folder lokal disinkronkan ke `main` (`0310e58`, `5fd9224`, `5d04fe8`). Verifikasi di PC: 5/5 berhasil.

**Hasil perbandingan WIP lokal vs `main`**
| Fitur di WIP lokal | Status di `main` | Tindakan |
|---|---|---|
| `licenses/order`, `order_status`, `approve_order`, panel approval di Dashboard | Sudah ada (implementasi PR #1) | Tidak diporting |
| `GET licenses/{id}/instances` + modal detail instance + kolom Kuota User | Belum ada | **Diporting**, memakai komponen modal yang sudah ada |
| `GET licenses/pending_orders` | Belum ada | **Diporting** (`OrderModel::listPending`) |
| `api/.htaccess` | Belum ada | **Masuk repo** → T-15 selesai |
| Docs bagian 6–9 `PANDUAN_PENGGUNAAN.md` | Belum ada | **Diporting** sebagai bagian 7–10, disesuaikan dengan kode `main` |

**Masalah di WIP lokal yang sengaja tidak dibawa**
- Instance dibuat dengan status `'pending'` yang tidak ada di enum `application_instances.status` (gagal di MySQL mode strict).
- `order_status` dan respons `order` membuka `license_key` sebelum pembayaran di-approve.
- Approve tanpa transaksi dan tanpa pengecekan pesanan yang sudah diproses; masa aktif tidak dihitung dari tanggal approve.
- Variabel `$ip` tidak terdefinisi saat mencatat log order.
- Paket yang tidak ditemukan diam-diam diganti paket pertama; total tagihan tanpa PPN.
- `instance_key` tidak mengikuti konvensi Mini POS, sehingga `validate` membuat instance baru.

**Perubahan**
- `api/index.php`, `LicenseController` (`instances()`, `pendingOrders()`), `OrderModel::listPending()`.
- `index.html` + `assets/js/app.js` + `assets/css/style.css`: kolom Modules/Kuota User/Instances, tombol **Detail**, modal instance.
- `api/.htaccess`.
- `docs/PANDUAN_PENGGUNAAN.md`: bagian 7–10 baru (order & approval API, backlog n8n, application instances, proteksi akses aplikasi client); FAQ no. 4 dikoreksi (klaim "Sangat Aman" → 🟡, rujuk T-02).
- `docs/EKOSISTEM_CARA_KERJA.md` & tab Ekosistem: endpoint dan status baru.

**Verifikasi**
- `GET licenses/pending_orders` → daftar pesanan pending; `GET licenses/{id}/instances` → data instance; id tidak ada → 404.
- Browser: menu Licenses → Detail membuka modal instance; tidak ada error JS.

### 2026-10-23 — Login Mini POS (T-17, T-03, T-06, T-19, T-20)
**Jenis:** Perubahan kode
**Repo:** sample-mini-pos, lmd, software-showcase

**Keputusan pemilik proyek**
- Akun admin dibuat **saat pertama membuka aplikasi** (bukan di wizard Showcase), sesuai ERD: user adalah tanggung jawab aplikasi client.
- Admin boleh menambah user lain dengan batas `max_users` dari lisensi.

**Mini POS**
- Database sendiri `mini_pos` (`database/schema.sql`: `tenants`, `users`), dibuat otomatis saat pertama dibuka.
- `lib/LicenseGate.php`: aktivasi tenant dari link Showcase; license key disimpan di DB lalu dihapus dari URL; validasi server-ke-server ke `POST licenses/validate` (cache 5 menit/sesi); mode tenggang 24 jam jika LMD tidak terjangkau. **Tidak lagi membaca database `central_saas`** → T-06.
- `lib/UserRepository.php`: bcrypt, peran Admin/Kasir, kuota user aktif ≤ `max_users` (dengan row lock), kunci 5 menit setelah 5 kali gagal, waktu respons sama untuk email terdaftar/tidak → T-03.
- Halaman baru: `setup.php` (buat admin pertama; email wajib = email pemesan di LMD), `login.php`, `logout.php` (POST + CSRF), `users.php` (tambah, nonaktifkan/aktifkan, ganti password). View di `views/`.
- `index.php` jadi controller tipis; HTML kasir dipindah ke `views/pos.php` dengan header baru (menu Kasir/Kelola User, nama user, tombol Keluar).
- `config.php` + dukungan `config.local.php` (sudah di-ignore git) untuk kredensial DB & URL LMD.
- `app.js`: hapus pemeriksaan lisensi di browser dan penyimpanan lisensi di `localStorage`; heartbeat memakai `/api/index.php/...` (T-15 untuk Mini POS); perbaikan encoding emoji → T-20.
- Nama toko diambil dari `instance_name` di LMD → T-19.

**LMD**
- `validate` kini mengembalikan `licensee` (nama & email pemesan) dan `instance_name`.

**Showcase**
- Field password admin dihapus dari wizard; diganti catatan bahwa akun admin dibuat di aplikasi. Layar sukses menampilkan email admin dan langkah berikutnya.

**Verifikasi** (MariaDB + PHP built-in server dengan 4 worker agar Mini POS bisa memanggil LMD di host yang sama)
- Link aktivasi → setup (license key hilang dari URL); email salah → ditolak; password < 8 → ditolak; setup sukses → kasir; setup tidak bisa diulang.
- Tambah 2 kasir → kuota 3/3 → form tambah disembunyikan; nonaktifkan → 2/3.
- Perangkat lain tanpa license key → halaman login; user nonaktif ditolak; kasir masuk tanpa menu Kelola User; kasir membuka `users.php` → ditolak.
- 5 kali password salah → akun dikunci; password benar saat terkunci tetap ditolak.
- Tanpa tenant → Showcase `unauthorized_direct_access`; tenant asing → `tenant_not_activated`; license palsu → `invalid_license`; POST tanpa CSRF → 419; lisensi di-suspend di LMD → perangkat baru diarahkan ke Showcase.
- Password tersimpan sebagai hash bcrypt; tidak ada password di `license_logs` LMD.
- Alur penuh Showcase → approve → Mini POS berakhir di "Buat Akun Admin — {nama toko}". Heartbeat ke LMD berhasil. Tidak ada error JS.

**Temuan baru:** T-20 (selesai), T-21.

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
