<?php
/**
 * Mengosongkan DATA Central SaaS LMD untuk simulasi integrasi dari nol.
 *
 *   php database/reset_data.php
 *
 * Dihapus  : semua aplikasi, modul, paket, customer, subscription, lisensi, instalasi, pembayaran, log.
 * Disimpan : akun admin (system_users), token API (api_tokens), catatan migrasi (schema_migrations),
 *            dan struktur tabel. Karena catatan migrasi disimpan, migrate.php TIDAK membuat ulang
 *            aplikasi MINIPOS — daftarkan sendiri lewat dashboard (lihat docs/SIMULASI_INTEGRASI.md).
 *
 * Tidak bisa dibatalkan. Backup dulu, misalnya (XAMPP):
 *   C:\xampp\mysql\bin\mysqldump.exe -u root central_saas > C:\xampp\htdocs\_backup_central_saas.sql
 * Konfirmasi diketik manual; untuk skrip otomatis: LMD_RESET_CONFIRM=KOSONGKAN.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Jalankan dari command line.\n");
}

require_once __DIR__ . '/../api/config/database.php';

const KEEP_TABLES = ['system_users', 'api_tokens', 'schema_migrations'];

$db = Database::getInstance()->getConnection();
$tables = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")
    ->fetchAll(PDO::FETCH_COLUMN);
$targets = array_values(array_diff($tables, KEEP_TABLES));

echo "Database: " . $db->query("SELECT DATABASE()")->fetchColumn() . "\n\nAkan DIKOSONGKAN:\n";
foreach ($targets as $table) {
    $count = $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    echo '  ' . str_pad($table, 26) . $count . " baris\n";
}
echo "\nDisimpan: " . implode(', ', array_intersect(KEEP_TABLES, $tables)) . "\n";
echo "\nTindakan ini tidak bisa dibatalkan. Pastikan sudah backup.\nKetik KOSONGKAN untuk melanjutkan: ";

$answer = getenv('LMD_RESET_CONFIRM') !== false ? getenv('LMD_RESET_CONFIRM') : trim((string) fgets(STDIN));
if ($answer !== 'KOSONGKAN') {
    exit("\nDibatalkan. Tidak ada data yang diubah.\n");
}

$db->exec("SET FOREIGN_KEY_CHECKS = 0");
try {
    foreach ($targets as $table) {
        $db->exec("TRUNCATE TABLE `{$table}`");
    }
} finally {
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
}
echo "\nSelesai: " . count($targets) . " tabel dikosongkan. Akun admin & token API tetap ada.\n";
echo "Catatan: database aplikasi client (mis. mini_pos) tidak ikut dihapus; lihat docs/SIMULASI_INTEGRASI.md.\n";
