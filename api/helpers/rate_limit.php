<?php
/**
 * Pembatasan jumlah request per IP per endpoint (T-05), jendela waktu tetap 60 detik.
 * Disimpan di tabel rate_limits agar berlaku di semua proses PHP / worker.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

const RATE_LIMIT_WINDOW = 60;
// License key tidak dikenal per IP per menit (penangkal tebak-tebakan license key)
const RATE_LIMIT_VALIDATE_FAILURES = 20;

/**
 * Batas per menit untuk endpoint publik & login. Endpoint lain tidak dibatasi di sini
 * (sudah wajib login admin).
 */
function rate_limit_rule($resource, $id, $method) {
    $rules = [
        // Aplikasi hosted memvalidasi semua tokonya dari satu IP server, jadi batas umum dibuat longgar;
        // penebakan license key dibatasi terpisah lewat RATE_LIMIT_VALIDATE_FAILURES.
        'licenses/validate' => 600,
        'licenses/register' => 10,
        'licenses/order' => 10,
        // Showcase polling tiap 2,5 detik = 24/menit per pesanan
        'licenses/order_status' => 120,
        'licenses/reset_token' => 20,
        'auth/login' => 10,
        'packages' => 120,
        'payments/webhook' => 120,
    ];
    $key = $resource === 'packages' ? 'packages' : "{$resource}/{$id}";
    return isset($rules[$key]) ? [$key, $rules[$key]] : null;
}

/**
 * Menghentikan request dengan 429 bila batas terlampaui.
 */
function enforce_rate_limit($resource, $id, $method) {
    $rule = rate_limit_rule($resource, $id, $method);
    if (!$rule) {
        return;
    }
    [$key, $limit] = $rule;
    if (rate_limit_hit($key) > $limit) {
        reject_rate_limited();
    }
    if ($key === 'licenses/validate' && rate_limit_hit('licenses/validate_failed', false) >= RATE_LIMIT_VALIDATE_FAILURES) {
        reject_rate_limited();
    }
}

/**
 * Menambah (atau hanya membaca bila $increment = false) penghitung bucket untuk IP ini; mengembalikan jumlah hit.
 */
function rate_limit_hit($key, $increment = true) {
    $db = Database::getInstance()->getConnection();
    $window = intdiv(time(), RATE_LIMIT_WINDOW) * RATE_LIMIT_WINDOW;
    $bucket = $key . '|' . SecurityHelper::getClientIp();

    if ($increment) {
        // hits dihitung ulang dari 1 saat jendela berganti; urutan assignment MySQL kiri-ke-kanan
        $db->prepare("
            INSERT INTO rate_limits (bucket, window_start, hits) VALUES (:bucket, :window, 1)
            ON DUPLICATE KEY UPDATE hits = IF(window_start = VALUES(window_start), hits + 1, 1), window_start = VALUES(window_start)
        ")->execute(['bucket' => $bucket, 'window' => $window]);
        if (mt_rand(1, 200) === 1) {
            $db->prepare("DELETE FROM rate_limits WHERE window_start < :old")->execute(['old' => $window - 3600]);
        }
    }
    $stmt = $db->prepare("SELECT hits FROM rate_limits WHERE bucket = :bucket AND window_start = :window");
    $stmt->execute(['bucket' => $bucket, 'window' => $window]);
    return (int) $stmt->fetchColumn();
}

function reject_rate_limited() {
    $retryAfter = max(1, intdiv(time(), RATE_LIMIT_WINDOW) * RATE_LIMIT_WINDOW + RATE_LIMIT_WINDOW - time());
    header('Retry-After: ' . $retryAfter);
    sendJsonResponse(['retry_after' => $retryAfter], 429, "Terlalu banyak permintaan. Coba lagi dalam {$retryAfter} detik.");
}
