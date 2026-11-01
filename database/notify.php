<?php
/**
 * Notifikasi terjadwal LMD (T-43). Jalankan sekali sehari (aman diulang, setiap notifikasi punya dedupe_key):
 *   C:\xampp\php\php.exe database\notify.php        (Windows Task Scheduler)
 *   php database/notify.php                         (cron Linux)
 *
 * - Pengingat masa aktif lisensi: H-7, H-1 (config notify.reminder_days) dan saat sudah berakhir.
 * - Tagihan yang kedaluwarsa tanpa dibayar: email "buat tagihan baru".
 * - Mengirim ulang notifikasi berstatus queued/failed (maks 5 percobaan).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Jalankan dari command line.\n");
}

require_once __DIR__ . '/../api/models/PaymentModel.php';
require_once __DIR__ . '/../api/helpers/notifier.php';

// URL di email (link Showcase/aplikasi) memakai host ini; ganti bila LMD diakses lewat alamat lain
$_SERVER['HTTP_HOST'] = getenv('LMD_PUBLIC_HOST') ?: ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
$_SERVER['SCRIPT_NAME'] = '/License-Management-Dashboard/api/index.php';

$db = Database::getInstance()->getConnection();
$sent = ['expiring' => 0, 'expired' => 0, 'payment_expired' => 0, 'retried' => 0];

$licenses = $db->query("
    SELECT l.license_key, l.expires_at, s.subscription_no, c.name, c.email, p.name AS package_name, a.name AS app_name,
           (SELECT ai.instance_name FROM application_instances ai WHERE ai.license_id = l.id ORDER BY ai.id LIMIT 1) AS store_name,
           DATEDIFF(DATE(l.expires_at), CURDATE()) AS days_left
    FROM licenses l
    JOIN subscriptions s ON s.id = l.subscription_id
    JOIN customers c ON c.id = s.customer_id
    JOIN packages p ON p.id = s.package_id
    JOIN applications a ON a.id = l.application_id
    WHERE l.expires_at IS NOT NULL AND l.status IN ('active', 'expired') AND s.status IN ('active', 'expired')
")->fetchAll();

$reminderDays = array_map('intval', (array) Notifier::config('reminder_days'));
foreach ($licenses as $l) {
    $days = (int) $l['days_left'];
    $vars = [
        'subscription_no' => $l['subscription_no'],
        'app_name' => $l['app_name'],
        'package_name' => $l['package_name'],
        'store_name' => $l['store_name'] ?: $l['name'],
        'end_date' => Notifier::date($l['expires_at']),
        'showcase_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/software-showcase/',
        'lookup_url' => Notifier::lookupUrl($l['subscription_no']),
    ];
    $expiry = date('Ymd', strtotime($l['expires_at']));
    if ($days < 0) {
        if (Notifier::send('license_expired', $l['email'], $l['name'], $vars, "license_expired:{$l['license_key']}:{$expiry}")) {
            $sent['expired']++;
        }
        continue;
    }
    // Kirim pengingat terdekat yang sudah jatuh tempo (mis. H-7 terlewat karena PC mati → tetap dikirim di H-5)
    foreach ($reminderDays as $threshold) {
        if ($days <= $threshold && $days > max(array_filter($reminderDays, function ($d) use ($threshold) { return $d < $threshold; }) ?: [-1])) {
            $vars['days_text'] = $days === 0 ? 'hari ini' : "dalam {$days} hari";
            if (Notifier::send('license_expiring', $l['email'], $l['name'], $vars, "license_expiring:{$l['license_key']}:{$expiry}:H{$threshold}")) {
                $sent['expiring']++;
            }
            break;
        }
    }
}

// Tagihan kedaluwarsa (status diperbarui dulu, lalu notifikasi per tagihan; pesanan harus masih pending)
$payments = new PaymentModel();
$db->exec("UPDATE payments SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()");
$expired = $db->query("
    SELECT pay.*, s.subscription_no FROM payments pay JOIN subscriptions s ON s.id = pay.subscription_id
    WHERE pay.status = 'expired' AND s.status = 'pending'
")->fetchAll();
foreach ($expired as $p) {
    $before = (int) $db->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    $payments->notifyPayment($p, $p['subscription_no'], 'expired');
    if ((int) $db->query("SELECT COUNT(*) FROM notifications")->fetchColumn() > $before) {
        $sent['payment_expired']++;
    }
}

// Kirim ulang yang tertunda / gagal (berguna saat kanal SMTP/WhatsApp sudah aktif)
foreach ($db->query("SELECT id FROM notifications WHERE status IN ('queued', 'failed') AND attempts < 5")->fetchAll(PDO::FETCH_COLUMN) as $id) {
    Notifier::deliver($id);
    $sent['retried']++;
}

echo date('Y-m-d H:i:s') . " notify: pengingat {$sent['expiring']}, berakhir {$sent['expired']}, tagihan kedaluwarsa {$sent['payment_expired']}, kirim ulang {$sent['retried']}\n";
