<?php
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance()->getConnection();

echo "Enriching SaaS demo data for rich dashboard analytics...\n";

// 1. Modules for TMS
$db->exec("
    INSERT IGNORE INTO `modules` (`id`, `application_id`, `code`, `name`, `slug`, `module_type`, `description`, `sort_order`) VALUES
    (11, 3, 'TMS_DASHBOARD', 'Dashboard Pelatihan', 'tms-dashboard', 'standard', 'Ringkasan jadwal kelas dan kelulusan.', 1),
    (12, 3, 'TMS_CLASSES', 'Manajemen Kelas & Jadwal', 'tms-classes', 'standard', 'Pengelolaan kurikulum, trainer, dan peserta.', 2),
    (13, 3, 'TMS_CERTIFICATE', 'E-Sertifikat & Ujian', 'tms-certificate', 'advanced', 'Penerbitan sertifikat digital terverifikasi QR.', 3);
");

// 2. Packages for Distributor & TMS
$db->exec("
    INSERT IGNORE INTO `packages` (`id`, `application_id`, `code`, `name`, `slug`, `description`, `base_price`, `billing_cycle`, `max_users`, `future_module_access`, `is_public`, `sort_order`) VALUES
    (4, 2, 'DIST_PRO', 'Distributor Pro', 'dist-pro', 'Paket multi-gudang dan armada.', 1200000.00, 'monthly', 15, 0, 1, 1),
    (5, 2, 'DIST_ENT', 'Distributor Enterprise', 'dist-ent', 'Paket enterprise distribusi nasional.', 14000000.00, 'annual', 50, 1, 1, 2),
    (6, 3, 'TMS_PRO', 'TMS Academy Pro', 'tms-pro', 'Paket lengkap pelatihan profesional.', 7500000.00, 'annual', 20, 0, 1, 1);
");

// 3. Package Modules
$db->exec("
    INSERT IGNORE INTO `package_modules` (`package_id`, `module_id`, `additional_price`) VALUES
    (4, 8, 0.00), (4, 9, 0.00),
    (5, 8, 0.00), (5, 9, 0.00), (5, 10, 0.00),
    (6, 11, 0.00), (6, 12, 0.00), (6, 13, 0.00);
");

// 4. Customers
$db->exec("
    INSERT IGNORE INTO `customers` (`id`, `code`, `name`, `email`, `phone`, `status`) VALUES
    (4, 'CUST-004', 'PT Logistik Prima Express', 'ops@logistikprima.co.id', '081122334455', 'active'),
    (5, 'CUST-005', 'Lembaga Edukasi Bina Mandiri', 'admin@binamandiri.ac.id', '082233445566', 'active'),
    (6, 'CUST-006', 'Apotek Sentra Medika', 'owner@sentramedika.com', '081344552211', 'active'),
    (7, 'CUST-007', 'Apotek Kencana Jaya', 'info@kencanajaya.id', '081566778899', 'inactive');
");

// 5. Subscriptions
$db->exec("
    INSERT IGNORE INTO `subscriptions` (`id`, `subscription_no`, `customer_id`, `application_id`, `package_id`, `start_date`, `end_date`, `billing_cycle`, `max_users`, `base_price`, `addon_price`, `discount_amount`, `tax_amount`, `total_price`, `status`) VALUES
    (4, 'SUB-2026-00004', 4, 2, 5, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_ADD(CURDATE(), INTERVAL 8 MONTH), 'annual', 50, 14000000.00, 0.00, 1000000.00, 1430000.00, 14430000.00, 'active'),
    (5, 'SUB-2026-00005', 5, 3, 6, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 'annual', 20, 7500000.00, 0.00, 500000.00, 770000.00, 7770000.00, 'active'),
    (6, 'SUB-2026-00006', 6, 1, 1, DATE_SUB(CURDATE(), INTERVAL 1 YEAR), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'monthly', 3, 300000.00, 0.00, 0.00, 33000.00, 333000.00, 'active'),
    (7, 'SUB-2026-00007', 7, 1, 2, DATE_SUB(CURDATE(), INTERVAL 1 YEAR), DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'annual', 10, 7500000.00, 0.00, 500000.00, 770000.00, 7770000.00, 'expired');
");

// 6. Licenses
$db->exec("
    INSERT IGNORE INTO `licenses` (`id`, `license_key`, `subscription_id`, `application_id`, `issued_at`, `expires_at`, `status`, `last_checked_at`) VALUES
    (4, 'LIC-DIST-A482E190BC33FA1277E099CC', 4, 2, DATE_SUB(NOW(), INTERVAL 4 MONTH), DATE_ADD(NOW(), INTERVAL 240 DAY), 'active', NOW()),
    (5, 'LIC-TMS-C11984FE7761002DAA5421EE', 5, 3, DATE_SUB(NOW(), INTERVAL 2 MONTH), DATE_ADD(NOW(), INTERVAL 300 DAY), 'active', NOW()),
    (6, 'LIC-APO-CRIT558811EE0042AA66B819', 6, 1, DATE_SUB(NOW(), INTERVAL 1 YEAR), DATE_ADD(NOW(), INTERVAL 5 DAY), 'active', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
    (7, 'LIC-APO-EXPD990011AA22BB33CC44DD', 7, 1, DATE_SUB(NOW(), INTERVAL 1 YEAR), DATE_SUB(NOW(), INTERVAL 12 DAY), 'expired', DATE_SUB(NOW(), INTERVAL 10 DAY));
");

// 7. License Modules
$db->exec("
    INSERT IGNORE INTO `license_modules` (`license_id`, `module_id`) VALUES
    (4, 8), (4, 9), (4, 10),
    (5, 11), (5, 12), (5, 13),
    (6, 1), (6, 2), (6, 3), (6, 4),
    (7, 1), (7, 2), (7, 3), (7, 4), (7, 5), (7, 6);
");

// 8. License Logs (Include some recent failed validations)
$db->exec("
    INSERT IGNORE INTO `license_logs` (`id`, `license_id`, `instance_id`, `action`, `request_id`, `ip_address`, `request_data`, `response_data`, `result`, `created_at`) VALUES
    (4, 6, 1, 'validate', 'REQ-20261010-004', '180.252.112.50', '{\"license_key\":\"LIC-APO-CRIT558811EE0042AA66B819\"}', '{\"valid\":true}', 'success', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
    (5, 7, NULL, 'validate', 'REQ-20261010-005', '114.122.88.90', '{\"license_key\":\"LIC-APO-EXPD990011AA22BB33CC44DD\"}', '{\"valid\":false,\"reason\":\"License has expired\"}', 'failed', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
    (6, 7, NULL, 'validate', 'REQ-20261010-006', '114.122.88.90', '{\"license_key\":\"LIC-APO-EXPD990011AA22BB33CC44DD\"}', '{\"valid\":false,\"reason\":\"License has expired\"}', 'failed', DATE_SUB(NOW(), INTERVAL 15 MINUTE));
");

echo "Enrichment completed successfully!\n";
