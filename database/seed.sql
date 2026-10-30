-- Central SaaS Seed Data
-- ⚠️ PERINGATAN: file ini MENGOSONGKAN (TRUNCATE) seluruh tabel central_saas sebelum mengisi data demo.
-- Jangan jalankan pada database yang sudah berisi data nyata. Untuk memperbarui struktur, pakai: php database/migrate.php
-- Instalasi baru: schema.sql → seed.sql (opsional, data demo) → php database/migrate.php
--   (migrate.php juga mendaftarkan aplikasi bawaan seperti MINIPOS, lihat docs/STANDAR_INTEGRASI.md)
USE `central_saas`;

-- Disable Foreign Key Checks during truncate & seed
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `license_logs`;
TRUNCATE TABLE `license_activations`;
TRUNCATE TABLE `application_instances`;
TRUNCATE TABLE `license_modules`;
TRUNCATE TABLE `licenses`;
TRUNCATE TABLE `subscription_modules`;
TRUNCATE TABLE `subscriptions`;
TRUNCATE TABLE `customers`;
TRUNCATE TABLE `package_modules`;
TRUNCATE TABLE `packages`;
TRUNCATE TABLE `modules`;
TRUNCATE TABLE `applications`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Applications
INSERT INTO `applications` (`id`, `code`, `name`, `slug`, `description`, `status`) VALUES
(1, 'APOTEK', 'Apotek Management System', 'apotek-management-system', 'Sistem pengelolaan apotek, stok obat, kasir, dan laporan keuangan.', 'active'),
(2, 'DISTRIBUTOR', 'Distributor Management System', 'distributor-management-system', 'Sistem distribusi barang, multi-gudang, dan armada pengiriman.', 'active'),
(3, 'TMS', 'Training Management System', 'training-management-system', 'Sistem manajemen pelatihan, peserta, dan sertifikat.', 'active');

-- 2. Modules
-- Apotek Modules
INSERT INTO `modules` (`id`, `application_id`, `code`, `name`, `slug`, `module_type`, `description`, `sort_order`) VALUES
(1, 1, 'DASHBOARD', 'Dashboard & Analytics', 'apotek-dashboard', 'standard', 'Ringkasan penjualan, stok obat kritis, dan pendapatan.', 1),
(2, 1, 'MASTER_OBAT', 'Master Data Obat', 'apotek-master-obat', 'standard', 'Pengelolaan data obat, kategori, satuan, dan harga.', 2),
(3, 1, 'INVENTORY', 'Manajemen Stok & Inventory', 'apotek-inventory', 'standard', 'Stok masuk/keluar, batch number, dan tanggal kadaluarsa.', 3),
(4, 1, 'POS_KASIR', 'Kasir & Penjualan (POS)', 'apotek-pos-kasir', 'standard', 'Point of Sales penjualan resep dan non-resep.', 4),
(5, 1, 'PURCHASING', 'Pembelian (PO & AP)', 'apotek-purchasing', 'advanced', 'Pemesanan obat ke PBF dan hutang dagang.', 5),
(6, 1, 'ACCOUNTING', 'Akuntansi & Laporan Keuangan', 'apotek-accounting', 'advanced', 'Jurnal otomatis, neraca, dan laba rugi.', 6),
(7, 1, 'BPJS_INTEGRATION', 'Integrasi BPJS / SatuSehat', 'apotek-bpjs-integration', 'advanced', 'Bridging sistem kesehatan nasional.', 7);

-- Distributor Modules
INSERT INTO `modules` (`id`, `application_id`, `code`, `name`, `slug`, `module_type`, `description`, `sort_order`) VALUES
(8, 2, 'DASHBOARD', 'Dashboard Distribusi', 'dist-dashboard', 'standard', 'Ringkasan pengiriman dan outstanding invoice.', 1),
(9, 2, 'MULTI_WAREHOUSE', 'Multi Gudang & Stock Transfer', 'dist-multi-warehouse', 'advanced', 'Manajemen stok lintas cabang gudang.', 2),
(10, 2, 'SALES_CANVASSING', 'Sales & Canvassing App', 'dist-sales-canvassing', 'advanced', 'Aplikasi mobile sales lapangan.', 3);

-- 3. Packages
INSERT INTO `packages` (`id`, `application_id`, `code`, `name`, `slug`, `description`, `base_price`, `billing_cycle`, `max_users`, `future_module_access`, `is_public`, `sort_order`) VALUES
(1, 1, 'APO_BASIC', 'Apotek Basic', 'apotek-basic', 'Paket starter untuk apotek mandiri skala kecil.', 300000.00, 'monthly', 3, 0, 1, 1),
(2, 1, 'APO_PRO', 'Apotek Professional', 'apotek-professional', 'Paket lengkap untuk apotek menengah dengan fitur kasir & purchasing.', 750000.00, 'monthly', 10, 0, 1, 2),
(3, 1, 'APO_ENT', 'Apotek Enterprise', 'apotek-enterprise', 'Paket unlimited fitur akuntansi, BPJS, dan fitur baru di masa depan.', 1500000.00, 'annual', 50, 1, 1, 3);

-- 4. Package Modules
-- Basic (Modules 1, 2, 3, 4)
INSERT INTO `package_modules` (`package_id`, `module_id`, `additional_price`) VALUES
(1, 1, 0.00), (1, 2, 0.00), (1, 3, 0.00), (1, 4, 0.00);

-- Professional (Modules 1, 2, 3, 4, 5, 6)
INSERT INTO `package_modules` (`package_id`, `module_id`, `additional_price`) VALUES
(2, 1, 0.00), (2, 2, 0.00), (2, 3, 0.00), (2, 4, 0.00), (2, 5, 0.00), (2, 6, 0.00);

-- Enterprise (Modules 1, 2, 3, 4, 5, 6, 7)
INSERT INTO `package_modules` (`package_id`, `module_id`, `additional_price`) VALUES
(3, 1, 0.00), (3, 2, 0.00), (3, 3, 0.00), (3, 4, 0.00), (3, 5, 0.00), (3, 6, 0.00), (3, 7, 0.00);

-- 5. Customers
INSERT INTO `customers` (`id`, `code`, `name`, `email`, `phone`, `status`) VALUES
(1, 'CUST-001', 'PT Sehat Sentosa Pharmacy', 'admin@sehatsentosa.co.id', '081299887766', 'active'),
(2, 'CUST-002', 'Apotek Kimia Utama', 'info@kimiautama.com', '085711223344', 'active'),
(3, 'CUST-003', 'CV Medika Nusantara', 'contact@medikanusantara.id', '081344556677', 'active');

-- 6. Subscriptions
INSERT INTO `subscriptions` (`id`, `subscription_no`, `customer_id`, `application_id`, `package_id`, `start_date`, `end_date`, `billing_cycle`, `max_users`, `base_price`, `addon_price`, `discount_amount`, `tax_amount`, `total_price`, `status`) VALUES
(1, 'SUB-2026-00001', 1, 1, 2, '2026-01-01', '2026-12-31', 'annual', 10, 7500000.00, 0.00, 500000.00, 770000.00, 7770000.00, 'active'),
(2, 'SUB-2026-00002', 2, 1, 3, '2026-03-01', '2027-02-28', 'annual', 50, 15000000.00, 0.00, 1000000.00, 1540000.00, 15540000.00, 'active');

-- 7. Subscription Modules
INSERT INTO `subscription_modules` (`subscription_id`, `module_id`, `source`, `price`) VALUES
(1, 1, 'package', 0.00), (1, 2, 'package', 0.00), (1, 3, 'package', 0.00),
(1, 4, 'package', 0.00), (1, 5, 'package', 0.00), (1, 6, 'package', 0.00),
(2, 1, 'package', 0.00), (2, 2, 'package', 0.00), (2, 3, 'package', 0.00),
(2, 4, 'package', 0.00), (2, 5, 'package', 0.00), (2, 6, 'package', 0.00), (2, 7, 'package', 0.00);

-- 8. Licenses
INSERT INTO `licenses` (`id`, `license_key`, `subscription_id`, `application_id`, `issued_at`, `expires_at`, `status`, `last_checked_at`) VALUES
(1, 'LIC-APO-8A3F9C2B1D7E401A99FE88AA', 1, 1, '2026-01-01 10:00:00', '2026-12-31 23:59:59', 'active', '2026-10-09 22:00:00'),
(2, 'LIC-APO-77B1D0E4F99A2C5588EF1123', 2, 1, '2026-03-01 09:00:00', '2027-02-28 23:59:59', 'active', '2026-10-09 21:30:00');

-- 9. License Modules
INSERT INTO `license_modules` (`license_id`, `module_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6),
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5), (2, 6), (2, 7);

-- 10. Application Instances
INSERT INTO `application_instances` (`id`, `license_id`, `instance_key`, `instance_name`, `domain`, `environment`, `last_seen_at`, `status`) VALUES
(1, 1, 'INST-APO-JKT-001', 'Apotek Sehat Sentosa - Cabang Jakarta', 'apotek-jkt.sehatsentosa.co.id', 'production', '2026-10-09 22:00:00', 'active'),
(2, 1, 'INST-APO-TNG-001', 'Apotek Sehat Sentosa - Cabang Tangerang', 'apotek-tng.sehatsentosa.co.id', 'production', '2026-10-09 20:15:00', 'active'),
(3, 2, 'INST-APO-KIM-001', 'Apotek Kimia Utama Pusat', 'app.kimiautama.com', 'production', '2026-10-09 21:30:00', 'active');

-- 11. License Activations
INSERT INTO `license_activations` (`id`, `license_id`, `instance_id`, `activation_key`, `activated_at`, `last_seen_at`, `ip_address`, `status`) VALUES
(1, 1, 1, 'ACT-JKT-998811AABBCC', '2026-01-02 08:30:00', '2026-10-09 22:00:00', '180.252.112.45', 'active'),
(2, 1, 2, 'ACT-TNG-776622DDEEFF', '2026-01-05 09:15:00', '2026-10-09 20:15:00', '180.252.112.48', 'active'),
(3, 2, 3, 'ACT-KIM-334455FFAABB', '2026-03-02 11:00:00', '2026-10-09 21:30:00', '36.85.201.12', 'active');

-- 12. License Logs
INSERT INTO `license_logs` (`id`, `license_id`, `instance_id`, `action`, `request_id`, `ip_address`, `request_data`, `response_data`, `result`, `created_at`) VALUES
(1, 1, 1, 'validate', 'REQ-20261009-001', '180.252.112.45', '{"instance_key":"INST-APO-JKT-001","domain":"apotek-jkt.sehatsentosa.co.id"}', '{"valid":true,"max_users":10,"modules":["DASHBOARD","MASTER_OBAT","INVENTORY","POS_KASIR","PURCHASING","ACCOUNTING"]}', 'success', '2026-10-09 22:00:00'),
(2, 2, 3, 'heartbeat', 'REQ-20261009-002', '36.85.201.12', '{"instance_key":"INST-APO-KIM-001"}', '{"status":"ok"}', 'success', '2026-10-09 21:30:00');

-- Kuota instalasi per paket (T-02) & salinannya di subscription
UPDATE `packages` SET `max_instances` = CASE `code` WHEN 'APO_PRO' THEN 2 WHEN 'APO_ENT' THEN 5 ELSE 1 END;
UPDATE `subscriptions` s JOIN `packages` p ON s.`package_id` = p.`id` SET s.`max_instances` = p.`max_instances`;
