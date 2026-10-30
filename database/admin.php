<?php
/**
 * Kelola akun admin dashboard LMD dari command line (T-04). Saat ini dibatasi satu akun.
 *
 *   php database/admin.php create <email> "<nama>"     buat akun admin (password ditanyakan)
 *   php database/admin.php reset-password <email>      ganti password & buka kunci login
 *   php database/admin.php list                        tampilkan akun
 *   php database/admin.php token-create "<nama>"       token API otomasi (scope orders: pending/approve/reject order)
 *   php database/admin.php token-list                  daftar token
 *   php database/admin.php token-revoke <id>           cabut token
 *
 * Password ditanyakan secara interaktif agar tidak tersimpan di riwayat command line.
 * Untuk skrip otomatis, password bisa diberikan lewat environment LMD_ADMIN_PASSWORD.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Jalankan dari command line.\n");
}

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/models/SystemUserModel.php';
require_once __DIR__ . '/../api/models/ApiTokenModel.php';

$db = Database::getInstance()->getConnection();
$exists = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_users'")->fetchColumn();
if (!$exists) {
    exit("Tabel system_users belum ada. Jalankan dulu: php database/migrate.php\n");
}
$users = new SystemUserModel($db);

function askPassword() {
    $fromEnv = getenv('LMD_ADMIN_PASSWORD');
    if ($fromEnv !== false && $fromEnv !== '') {
        return $fromEnv;
    }
    $first = askLine('Password baru (min. ' . SystemUserModel::MIN_PASSWORD . ' karakter): ');
    $second = askLine('Ulangi password: ');
    if ($first !== $second) {
        exit("Password tidak sama. Tidak ada yang diubah.\n");
    }
    return $first;
}

function askLine($prompt) {
    echo $prompt;
    return rtrim((string) fgets(STDIN), "\r\n");
}

[$script, $command, $email, $name] = array_pad($argv, 4, null);

try {
    switch ($command) {
        case 'create':
            if (!$email) {
                exit("Pemakaian: php database/admin.php create <email> \"<nama>\"\n");
            }
            if ($users->count() > 0) {
                exit("Akun admin LMD sudah ada (saat ini dibatasi satu akun). Gunakan: php database/admin.php reset-password <email>\n");
            }
            $admin = $users->create($name ?: 'Admin LMD', $email, askPassword());
            echo "Akun admin dibuat: {$admin['email']}. Silakan login di dashboard LMD.\n";
            break;

        case 'reset-password':
            if (!$email) {
                exit("Pemakaian: php database/admin.php reset-password <email>\n");
            }
            $users->resetPassword($email, askPassword());
            echo "Password {$email} diganti dan kunci login dibuka.\n";
            break;

        case 'list':
            foreach ($db->query("SELECT email, name, last_login_at, locked_until FROM system_users") as $row) {
                echo "{$row['email']}  ({$row['name']})  login terakhir: " . ($row['last_login_at'] ?: '-')
                    . ($row['locked_until'] && strtotime($row['locked_until']) > time() ? "  [TERKUNCI s/d {$row['locked_until']}]" : '') . "\n";
            }
            break;

        case 'token-create':
            $token = (new ApiTokenModel($db))->create($email ?: 'n8n', 'orders');
            echo "Token dibuat (simpan sekarang, tidak bisa ditampilkan lagi):\n\n  {$token}\n\n"
                . "Pakai di header: Authorization: Bearer <token>\n"
                . "Hanya untuk: " . implode(', ', ApiTokenModel::SCOPES['orders']) . "\n";
            break;

        case 'token-list':
            foreach ((new ApiTokenModel($db))->all() as $row) {
                echo "#{$row['id']}  {$row['name']}  scope={$row['scope']}  dipakai: " . ($row['last_used_at'] ?: '-')
                    . ($row['revoked_at'] ? "  [DICABUT {$row['revoked_at']}]" : '') . "\n";
            }
            break;

        case 'token-revoke':
            echo (new ApiTokenModel($db))->revoke((int) $email) ? "Token #{$email} dicabut.\n" : "Token #{$email} tidak ditemukan / sudah dicabut.\n";
            break;

        default:
            exit("Perintah: create <email> \"<nama>\" | reset-password <email> | list | token-create \"<nama>\" | token-list | token-revoke <id>\n");
    }
} catch (InvalidArgumentException | DomainException $e) {
    exit('Gagal: ' . $e->getMessage() . "\n");
}
