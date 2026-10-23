# Hasil Review Dokumentasi Ekosistem Central SaaS

> **Tanggal review:** 2026-10-23
> **Cakupan:** seluruh docs di repo `lmd`, `software-showcase`, `sample-mini-pos`, lalu dicocokkan dengan kode di branch pengembangan.
> **Konteks:** proyek masih tahap perancangan & pengembangan. Password, credential, dan URL lokal sengaja hardcode.
> **Dokumen terkait:** [`EKOSISTEM_CARA_KERJA.md`](EKOSISTEM_CARA_KERJA.md) (cara kerja sistem) · [`LOG_EVALUASI.md`](LOG_EVALUASI.md) (daftar temuan `T-xx` & riwayat perubahan)

Dokumen ini adalah **pemahaman reviewer** atas isi docs: apa yang dikatakan setiap dokumen, gambaran utuh yang terbentuk, penilaian, dan bagian mana yang sesuai/tidak sesuai dengan kode. Status penyelesaian temuan dilacak di `LOG_EVALUASI.md`, bukan di sini.

---

## 1. Dokumen yang Direview

| Repo | Dokumen | Isi pokok |
|---|---|---|
| lmd | `docs/PANDUAN_PENGGUNAAN.md` | Buku panduan Central SaaS: prinsip arsitektur, 2 metode pendaftaran, API register/validate, simulasi alur DMS, 3 model deployment, 8 grafik analitik, 7 lapis keamanan, FAQ. |
| lmd | `docs/REKOMENDASI_DECISION_SUPPORT.md` | Matriks keputusan per peran (CEO/CFO, Product, Sales/CS, DevOps), fungsi 8 grafik, roadmap (churn scoring, export laporan, filter tanggal). |
| lmd | `docs/ERD/*` (level 0–9) | Desain database bertahap dari konsep sampai skema fisik final + aturan khusus (future module, users/roles di luar LMD). |
| software-showcase | `README.md` | Akses cepat (public & admin) dan rujukan ke panduan. |
| software-showcase | `docs/PANDUAN_SHOWCASE.md` | Visi closed-loop ecosystem, peran tiap entitas, tombol Install, validasi harian, struktur folder, admin katalog, deployment lokal vs production, multi-device & kuota user, wizard berlangganan 4 langkah, zero-footprint client, roadmap n8n. |
| sample-mini-pos | `README.txt` | Cara mengakses Mini POS dari perangkat lain di jaringan Wi-Fi yang sama. |

---

## 2. Ringkasan per Dokumen

### 2.1 LMD — `PANDUAN_PENGGUNAAN.md`
- **Tujuan sistem:** platform terpusat untuk katalog produk (aplikasi, modul, paket), customer, subscription, serta penerbitan & validasi lisensi untuk banyak aplikasi SaaS (Apotek, Distributor/DMS, TMS, ERP).
- **Pemisahan tanggung jawab:** LMD mengatur katalog, auto-register, hak modul, `max_users`, dan status lisensi. Aplikasi client mengatur user, role, permission, dan menegakkan kuota.
- **Dua cara pendaftaran:** (1) **Auto-register** oleh installer client — rekomendasi utama, tanpa input admin; (2) **Manual** oleh superadmin untuk kontrak B2B.
- **Dua API utama:** `POST /api/licenses/register` (instalasi pertama) dan `POST /api/licenses/validate` (validasi & heartbeat).
- **Simulasi DMS:** PT Distribution Logistics Jaya → register → license key + 15 user + modul MULTI_WAREHOUSE → validate → dashboard bertambah.
- **3 model deployment:** desktop (.exe, key di registry), cloud multi-tenant (portal memanggil register), on-premise (web installer, key di `.env`). API dinyatakan agnostik terhadap jenis aplikasi.
- **8 grafik analitik** dalam 2 fase + **Radar Aksi** (lisensi ≤30 hari/expired, tombol WhatsApp & Perpanjang).
- **7 lapis keamanan:** API key/secret, binding domain + instance, key SHA-256, PDO, anomaly detection, TLS, rate limiting.

### 2.2 LMD — `REKOMENDASI_DECISION_SUPPORT.md`
- Menilai kematangan dashboard sebagai Decision Support System **>90%** untuk skala early-stage s.d. mid-enterprise.
- Memetakan grafik ke peran: eksekutif (MRR, revenue per aplikasi, billing cycle), product (adopsi modul, tier user), sales/CS (radar jatuh tempo), DevOps (API health, heartbeat).
- Roadmap: automated churn risk scoring, export laporan PDF/Excel, filter rentang tanggal.

### 2.3 LMD — `docs/ERD` level 0–9
- **Level 0–2:** dua domain besar (Product Catalog vs Subscription System) dan relasi dasar (Application 1:N Module/Package, Package N:M Module, Customer 1:N Subscription, Subscription 1:N License, License 1:N Instance → Activation).
- **Level 3–5:** atribut tiap tabel dan kardinalitas lengkap.
- **Level 6–8:** alur data nyata: buat aplikasi → modul → paket → isi paket → (baru kemudian) customer → subscription → subscription_modules → license → license_modules → instance → activation → License API → aplikasi customer.
- **Level 9 (physical final):** 12 tabel inti 🟢; `users` dan `roles/permissions` ❌ sengaja di luar LMD; billing/payment dan API auth/webhook 🟡 FUTURE; aturan **future module access** 🟢 (paket dengan `future_module_access = true` otomatis mendapat modul baru tanpa upgrade).
- **Prinsip yang dipegang:** Catalog (Application → Module → Package) · Commercial (Customer → Subscription) · Provisioning (Subscription → License → Instance) · Runtime (Instance → License API → akses modul).

### 2.4 Showcase — `PANDUAN_SHOWCASE.md`
- Showcase adalah **front-store/marketplace** yang menutup siklus: LMD (produk & paket) → Showcase (pajang, demo, distribusi) → Client (install, register) → LMD (validasi & telemetri).
- Tombol **Install**: modal berisi paket ZIP / perintah CLI, langkah setup, contoh `.env`; installer client lalu memanggil register.
- **Validasi harian:** client memanggil validate; suspended/expired → layar dikunci.
- **Admin katalog** (`/admin`, password default): CRUD produk disimpan di `localStorage`, lalu **Export products.js** untuk memperbarui file publik.
- **Deployment:** tabel lokal (XAMPP) vs production (cPanel/VPS), prosedur cPanel & VPS, konektivitas on-premise ke LMD cloud.
- **Multi-device & kuota:** aplikasi client-server cukup di-install di satu host; perangkat lain lewat browser. Kuota `max_users` ditegakkan dengan **concurrent sessions** (rekomendasi) atau **named users**.
- **Wizard berlangganan 4 langkah** (disebut "Level 8"): pilih paket → data diri & subdomain → bayar QRIS/transfer + PPN 11% → menunggu approve admin (polling `order_status` tiap 2,5 detik) → buka aplikasi.
- **Zero-footprint client:** tidak ada file biner di perangkat customer; token lisensi berupa JSON kecil di `localStorage` browser.
- **Roadmap:** n8n + payment gateway (Midtrans/Xendit/Tripay) atau mutasi bank (Moota) untuk approve otomatis via `approve_order`.

### 2.5 Mini POS — `README.txt`
- Mini POS Express adalah aplikasi kasir web contoh yang terintegrasi lisensi LMD; diakses dari perangkat lain di Wi-Fi yang sama, lalu mengikuti wizard aktivasi.

---

## 3. Pemahaman Utuh (Sintesis)

1. **Satu otak lisensi untuk banyak produk.** LMD tidak menjalankan bisnis customer; ia hanya menjawab "aplikasi X milik customer Y boleh membuka modul apa, untuk berapa user, sampai kapan". Setiap produk baru cukup didaftarkan kodenya di LMD tanpa membangun sistem subscription sendiri.
2. **Siklus hidup produk berurutan dan tegas.** Katalog dibangun lebih dulu tanpa customer. Customer masuk → subscription → lisensi → instance → aktivasi → runtime. Setiap tahap adalah tabel tersendiri.
3. **Snapshot hak akses.** Modul disalin turun (package → subscription → license) agar perubahan paket tidak merusak kontrak yang sudah berjalan. Pengecualian yang disengaja: `future_module_access` untuk paket tertentu.
4. **Dua pintu masuk customer.** Installer client (auto-register, langsung aktif) dan Showcase (pesanan pending → approve pembayaran → aktif). Keduanya berakhir di tabel yang sama.
5. **Batas tanggung jawab jelas.** User/role/permission dan penegakan kuota ada di aplikasi client; LMD hanya memberi angka dan daftar modul.
6. **Telemetri sebagai dasar keputusan.** Setiap validate dicatat; dari log, `last_seen_at`, dan subscription lahir 8 grafik untuk retensi, revenue, produk, dan keamanan.
7. **Tahap proyek:** desain dan dokumentasi sudah jauh di depan; implementasi mengejar. Beberapa bagian docs menggambarkan kondisi target, bukan kondisi saat ini.

---

## 4. Penilaian

### 4.1 Yang solid
- ERD matang dan konsisten dengan `database/schema.sql` (12 tabel).
- Pemisahan Catalog / Commercial / Provisioning / Runtime tepat untuk multi-produk.
- Pola snapshot modul benar secara bisnis.
- Auto-register dibuat dalam satu transaksi atomik.
- Pembuatan key memakai `random_bytes`; query memakai PDO prepared statements.
- Docs sudah memikirkan skenario lapangan nyata (multi-device, on-premise vs cloud, kuota concurrent vs named).

### 4.2 Yang perlu diperhatikan
- Sebagian docs menulis fitur target seolah sudah berjalan (keamanan, endpoint order, "backend 100% siap n8n").
- Beberapa istilah dan angka tidak seragam antar-dokumen dan antar-repo (lihat §6).

---

## 5. Docs vs Kode

| Klaim di docs | Kondisi di kode | Temuan |
|---|---|---|
| Auto-register membuat customer, subscription, lisensi, instance dalam 1 transaksi | Sesuai (`LicenseModel::registerAutoLicense`) | — |
| Validate mengecek aktif / terblokir / kedaluwarsa | Sesuai (`LicenseModel::validateLicense`) | — |
| 8 grafik + Radar Aksi | Ada di dashboard | — |
| Wizard Showcase memanggil `order`, `order_status`, `approve_order` | Endpoint tidak ada di router LMD | T-01 |
| Lisensi terikat domain + instance; respons `domain_mismatch` | Instance baru dibuat otomatis, domain ditimpa, tidak pernah ditolak | T-02 |
| Kuota `max_users` ditegakkan (concurrent/named) | Belum ada di Mini POS | T-03 |
| API key / App-Secret, `401 Unauthorized` | Tidak ada autentikasi di API | T-04 |
| Rate limiting anti brute-force | Tidak ada | T-05 |
| Token lisensi aman di client | Dikirim lewat URL, disimpan di `localStorage`; `index.php` Mini POS membaca DB LMD langsung | T-06 |
| Admin Showcase dengan password | Hardcode & dicek di browser (sengaja, tahap dev) | T-07 |
| Format activation key `ACT-…` | Menghasilkan `ACT-ACT-…` | T-08 |
| Backend "100% siap" untuk n8n | `approve_order` belum ada | T-10 |
| `future_module_access` memberi modul baru otomatis | Kolom ada di `packages`, tetapi tidak ada logika yang menyalin modul baru ke subscription/lisensi | T-13 |

---

## 6. Inkonsistensi Antar-Dokumen / Antar-Repo

| Hal | Detail | Temuan |
|---|---|---|
| Path & URL lokal | `c:\xampp\…`, `file:///c:/…`, IP `192.168.0.116`; base URL `/api/...` vs `/api/index.php/...`; dua bagian bernomor 12 di `PANDUAN_SHOWCASE.md` | T-09 |
| Paket & harga | Showcase menampilkan Starter 3 user Rp150.000, Professional 15 user Rp350.000, Enterprise 50 user Rp850.000 (hardcode di `assets/js/app.js`). LMD (`seed.sql`): APO_BASIC 3 user Rp300.000, APO_PRO 10 user Rp750.000, APO_ENT 50 user Rp1.500.000 (annual). Customer melihat angka berbeda dari yang dicatat LMD. | T-12 |
| Nama tabel log | Docs menyebut `validation_logs`; tabel sebenarnya `license_logs`. | T-14 |
| Istilah key | Docs menyebut "Hashing SHA-256"; kode memakai `random_bytes` → hex (tetap tidak dapat ditebak, tetapi bukan SHA-256). | T-14 |
| Penamaan "Level 8" | `PANDUAN_SHOWCASE.md` §10 menyebut wizard berlangganan sebagai "Level 8", sedangkan di ERD Level 8 adalah tahap lisensi. Berpotensi membingungkan. | T-14 |

---

## 7. Daftar Temuan yang Dihasilkan Review Ini

Ringkasan saja; deskripsi lengkap dan status ada di `LOG_EVALUASI.md` → **Daftar Temuan**.

| ID | Ringkasan | Kategori |
|---|---|---|
| T-01 | Endpoint order/order_status/approve_order belum ada | Blocker fungsional |
| T-02 | Lisensi tidak terkunci ke instance/domain | Blocker desain |
| T-03 | `max_users` belum ditegakkan di client | Fungsional |
| T-04 | Tidak ada autentikasi API/admin | Hardening (ditunda) |
| T-05 | Tidak ada rate limiting | Hardening (ditunda) |
| T-06 | Lisensi lewat URL + `localStorage`; Mini POS baca DB LMD langsung | Arsitektur |
| T-07 | Password admin Showcase hardcode | Hardening (ditunda, sengaja) |
| T-08 | Prefix `ACT-ACT-` | Minor |
| T-09 | Path/URL lokal & penomoran di docs | Dokumentasi (ditunda) |
| T-10 | Klaim "100% siap n8n" tidak sesuai kode | Dokumentasi |
| T-11 | Token CSS `--border`/`--muted` tidak terdefinisi | Minor (UI) — ditemukan saat implementasi tab Ekosistem |
| T-12 | Paket & harga Showcase berbeda dengan LMD | Fungsional / konsistensi data |
| T-13 | Mekanisme `future_module_access` belum diimplementasikan | Fungsional |
| T-14 | Istilah docs tidak sesuai kode (`validation_logs`, SHA-256, "Level 8") | Dokumentasi |

---

## 8. Rekomendasi Urutan Penanganan

1. **T-01** — endpoint order/approve, agar alur berlangganan Showcase bisa diuji end-to-end.
2. **T-12** — Showcase mengambil paket & harga dari LMD (bukan hardcode), sekalian saat mengerjakan T-01.
3. **T-02** — aturan penguncian instance/domain di validate.
4. **T-03, T-13** — penegakan kuota di client dan mekanisme future module.
5. **T-08, T-11, T-14, T-09, T-10** — perbaikan kecil kode & docs.
6. **T-04, T-05, T-06, T-07** — hardening sebelum production.
