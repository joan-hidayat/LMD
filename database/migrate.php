<?php
/**
 * Migrasi database central_saas yang sudah terpasang ke struktur terbaru.
 *
 * Jalankan dari command line (aman dijalankan berulang kali):
 *   C:\xampp\php\php.exe database\migrate.php
 *
 * Setiap langkah dicek dulu ke INFORMATION_SCHEMA, jadi kolom yang sudah ada tidak diubah
 * dan data yang sudah diatur admin tidak ditimpa.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Jalankan dari command line: php database/migrate.php\n");
}

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/models/ModuleModel.php';

$db = Database::getInstance()->getConnection();

function columnExists(PDO $db, $table, $column) {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column
    ");
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

$migrations = [
    // T-02: kuota instalasi per paket, disalin ke subscription seperti max_users
    '2026_10_24_add_max_instances' => function (PDO $db) {
        $changed = false;
        if (!columnExists($db, 'packages', 'max_instances')) {
            $db->exec("ALTER TABLE `packages` ADD COLUMN `max_instances` INT UNSIGNED NOT NULL DEFAULT 1
                       COMMENT 'Kuota instalasi/lokasi aplikasi client per lisensi' AFTER `max_users`");
            // Nilai awal untuk paket bawaan seed; paket lain tetap 1 dan bisa diubah admin
            $db->exec("UPDATE `packages` SET `max_instances` = CASE `code` WHEN 'APO_PRO' THEN 2 WHEN 'APO_ENT' THEN 5 ELSE 1 END");
            $changed = true;
        }
        if (!columnExists($db, 'subscriptions', 'max_instances')) {
            $db->exec("ALTER TABLE `subscriptions` ADD COLUMN `max_instances` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `max_users`");
            $db->exec("UPDATE `subscriptions` s JOIN `packages` p ON s.`package_id` = p.`id` SET s.`max_instances` = p.`max_instances`");
            $changed = true;
        }
        return $changed;
    },

    // Standar Integrasi v1: template URL aplikasi hosted per aplikasi
    '2026_10_30_application_launch_url' => function (PDO $db) {
        if (columnExists($db, 'applications', 'launch_url')) {
            return false;
        }
        $db->exec("ALTER TABLE `applications` ADD COLUMN `launch_url` VARCHAR(255) NULL
                   COMMENT 'Template URL aplikasi hosted; NULL = aplikasi on-premise' AFTER `description`");
        return true;
    },

    // Standar Integrasi v1: Mini POS terdaftar sebagai aplikasi sendiri (sebelumnya menumpang APOTEK)
    '2026_10_30_register_minipos' => function (PDO $db) {
        return registerApplication($db, [
            'code' => 'MINIPOS',
            'name' => 'Mini POS Express',
            'slug' => 'mini-pos-express',
            'description' => 'Aplikasi kasir web multi-user untuk toko, kafe, dan UMKM.',
            'launch_url' => '/mini-pos/?tenant={tenant}&lic={license_key}',
            'modules' => [
                ['KASIR', 'Kasir & Transaksi', 'standard', 'Penjualan, keranjang, dan struk.'],
                ['LAPORAN', 'Laporan Penjualan', 'standard', 'Rekap harian, produk terlaris, omzet.'],
                ['STOK', 'Manajemen Stok', 'standard', 'Stok produk, stok minimum, dan penyesuaian.'],
                ['MULTI_OUTLET', 'Multi Outlet', 'advanced', 'Kelola beberapa outlet dalam satu akun.'],
            ],
            'packages' => [
                ['MPOS_STARTER', 'Mini POS Starter', 'Untuk satu toko kecil.', 150000, 'monthly', 2, 1, 0, ['KASIR', 'LAPORAN']],
                ['MPOS_PRO', 'Mini POS Pro', 'Toko dengan stok dan dua lokasi.', 350000, 'monthly', 5, 2, 0, ['KASIR', 'LAPORAN', 'STOK']],
                ['MPOS_BISNIS', 'Mini POS Bisnis', 'Jaringan outlet, termasuk fitur baru otomatis.', 3500000, 'annual', 15, 5, 1, ['KASIR', 'LAPORAN', 'STOK', 'MULTI_OUTLET']],
            ],
        ]);
    },

    // T-13: modul yang dibuat setelah paket/subscription future_module_access diberikan otomatis.
    // Idempoten; dijalankan setiap migrate untuk menyusulkan data yang terlewat.
    '2026_10_30_sync_future_modules' => function (PDO $db) {
        $granted = ModuleModel::syncFutureModules($db);
        return array_sum($granted) > 0;
    },

    // T-25: modul yang sudah dihapus tidak boleh tersisa di isi paket
    '2026_10_31_cleanup_deleted_package_modules' => function (PDO $db) {
        return $db->exec("DELETE pm FROM package_modules pm JOIN modules m ON m.id = pm.module_id WHERE m.deleted_at IS NOT NULL") > 0;
    },

    // T-21: token sekali pakai untuk reset password admin aplikasi client
    '2026_10_31_admin_reset_tokens' => function (PDO $db) {
        $exists = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_reset_tokens'")->fetchColumn();
        if ($exists) {
            return false;
        }
        $db->exec("
            CREATE TABLE `admin_reset_tokens` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `license_id` BIGINT UNSIGNED NOT NULL,
                `token_hash` CHAR(64) NOT NULL UNIQUE,
                `expires_at` DATETIME NOT NULL,
                `used_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_reset_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        return true;
    },

    // T-04: akun admin dashboard LMD
    '2026_10_31_system_users' => function (PDO $db) {
        $exists = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_users'")->fetchColumn();
        if ($exists) {
            return false;
        }
        $db->exec("
            CREATE TABLE `system_users` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(150) NOT NULL,
                `email` VARCHAR(150) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `locked_until` DATETIME NULL,
                `last_login_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        return true;
    },
];

/**
 * Mendaftarkan aplikasi beserta modul & paketnya bila belum ada (dicari berdasarkan kode).
 * Data yang sudah ada tidak diubah, jadi penyesuaian admin di dashboard tetap aman.
 */
function registerApplication(PDO $db, array $app) {
    $find = $db->prepare("SELECT id FROM applications WHERE code = :code");
    $find->execute(['code' => $app['code']]);
    if ($find->fetchColumn()) {
        return false;
    }

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO applications (code, name, slug, description, launch_url, status) VALUES (:code, :name, :slug, :description, :launch_url, 'active')")
            ->execute(['code' => $app['code'], 'name' => $app['name'], 'slug' => $app['slug'], 'description' => $app['description'], 'launch_url' => $app['launch_url']]);
        $appId = $db->lastInsertId();

        $moduleIds = [];
        $insModule = $db->prepare("INSERT INTO modules (application_id, code, name, slug, module_type, description, sort_order) VALUES (:app, :code, :name, :slug, :type, :description, :sort)");
        foreach ($app['modules'] as $i => [$code, $name, $type, $description]) {
            $insModule->execute(['app' => $appId, 'code' => $code, 'name' => $name, 'slug' => strtolower($app['code'] . '-' . str_replace('_', '-', $code)),
                'type' => $type, 'description' => $description, 'sort' => $i + 1]);
            $moduleIds[$code] = $db->lastInsertId();
        }

        $insPackage = $db->prepare("
            INSERT INTO packages (application_id, code, name, slug, description, base_price, billing_cycle, max_users, max_instances, future_module_access, is_public, sort_order)
            VALUES (:app, :code, :name, :slug, :description, :price, :cycle, :users, :instances, :future, 1, :sort)
        ");
        $insPackageModule = $db->prepare("INSERT INTO package_modules (package_id, module_id, additional_price) VALUES (:pkg, :mod, 0.00)");
        foreach ($app['packages'] as $i => [$code, $name, $description, $price, $cycle, $users, $instances, $future, $modules]) {
            $insPackage->execute(['app' => $appId, 'code' => $code, 'name' => $name, 'slug' => strtolower(str_replace('_', '-', $code)),
                'description' => $description, 'price' => $price, 'cycle' => $cycle, 'users' => $users, 'instances' => $instances,
                'future' => $future, 'sort' => $i + 1]);
            $packageId = $db->lastInsertId();
            foreach ($modules as $moduleCode) {
                $insPackageModule->execute(['pkg' => $packageId, 'mod' => $moduleIds[$moduleCode]]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return true;
}

foreach ($migrations as $name => $migrate) {
    try {
        echo str_pad($name, 46) . ($migrate($db) ? "DITERAPKAN\n" : "sudah terpasang\n");
    } catch (Throwable $e) {
        echo str_pad($name, 46) . "GAGAL: " . $e->getMessage() . "\n";
        exit(1);
    }
}
echo "Selesai.\n";

if ((int) $db->query("SELECT COUNT(*) FROM system_users")->fetchColumn() === 0) {
    echo "\nBelum ada akun admin LMD. Buat dengan:\n  php database/admin.php create <email> \"<nama>\"\n";
}
