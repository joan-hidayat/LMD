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
];

foreach ($migrations as $name => $migrate) {
    try {
        echo str_pad($name, 40) . ($migrate($db) ? "DITERAPKAN\n" : "sudah terpasang\n");
    } catch (Throwable $e) {
        echo str_pad($name, 40) . "GAGAL: " . $e->getMessage() . "\n";
        exit(1);
    }
}
echo "Selesai.\n";
