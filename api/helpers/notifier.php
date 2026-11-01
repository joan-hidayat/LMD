<?php
/**
 * Notifikasi ke customer (T-43). Pola outbox: setiap notifikasi dicatat di tabel notifications lalu dikirim
 * lewat kanal yang aktif. Kegagalan notifikasi TIDAK pernah menggagalkan proses bisnis (pesanan, pembayaran).
 *
 *   Notifier::send('payment_paid', 'a@b.com', 'Budi', ['subscription_no' => …], 'payment_paid:PAY-…');
 */
require_once __DIR__ . '/../config/database.php';

class Notifier {
    private static $config = null;

    public static function config($key = null) {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/notify.php';
        }
        return $key === null ? self::$config : (self::$config[$key] ?? null);
    }

    /**
     * Mengembalikan id notifikasi, atau null bila gagal dicatat / sudah pernah dikirim (dedupe_key sama).
     */
    public static function send($event, $recipient, $recipientName, array $vars, $dedupeKey = null) {
        try {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                return null;
            }
            [$subject, $body] = self::render($event, ['name' => $recipientName ?: 'Pelanggan'] + $vars);
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT IGNORE INTO notifications (event, channel, recipient, recipient_name, subject, body, ref, dedupe_key)
                VALUES (:event, :channel, :recipient, :name, :subject, :body, :ref, :dedupe)
            ");
            $stmt->execute([
                'event' => $event,
                'channel' => self::config('channel'),
                'recipient' => strtolower($recipient),
                'name' => $recipientName,
                'subject' => mb_substr($subject, 0, 200),
                'body' => $body,
                'ref' => $vars['subscription_no'] ?? ($vars['license_key'] ?? null),
                'dedupe' => $dedupeKey,
            ]);
            if ($stmt->rowCount() === 0) {
                return null;
            }
            $id = (int) $db->lastInsertId();
            self::deliver($id);
            return $id;
        } catch (Throwable $e) {
            error_log('[notifier] ' . $event . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mengirim satu notifikasi lewat kanalnya. Kotak surat lokal langsung dianggap terkirim (tersimpan).
     */
    public static function deliver($id) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM notifications WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $n = $stmt->fetch();
        if (!$n || $n['status'] === 'sent') {
            return;
        }
        $error = null;
        if ($n['channel'] !== 'mailbox') {
            // Kanal sungguhan belum dibuat: notifikasi tetap tercatat (bisa dibaca di dashboard) dan ditandai gagal
            $error = "Kanal '{$n['channel']}' belum tersedia (📋 rencana). Notifikasi tersimpan di kotak surat.";
        }
        $db->prepare("
            UPDATE notifications SET status = :status, attempts = attempts + 1, last_error = :error,
                   sent_at = IF(:sent = 1, NOW(), sent_at)
            WHERE id = :id
        ")->execute(['status' => $error ? 'failed' : 'sent', 'error' => $error, 'sent' => $error ? 0 : 1, 'id' => $id]);
    }

    private static function render($event, array $v) {
        $templates = require __DIR__ . '/notify_templates.php';
        if (!isset($templates[$event])) {
            throw new InvalidArgumentException("Unknown notification event '{$event}'.");
        }
        [$subject, $lines] = $templates[$event]($v);
        $footer = ['', '—', 'Central SaaS · email otomatis, mohon tidak membalas.', 'Cek status pesanan kapan saja: ' . ($v['lookup_url'] ?? '(Software Showcase → Cek Pesanan)')];
        return [$subject, implode("\n", array_merge(["Halo {$v['name']},", ''], $lines, $footer))];
    }

    public static function rupiah($n) {
        return 'Rp ' . number_format((float) $n, 0, ',', '.');
    }

    public static function date($d) {
        return $d ? date('d M Y', strtotime($d)) : '-';
    }

    /**
     * Link Cek Pesanan di Showcase (host yang sama dengan LMD), dipakai di footer semua notifikasi pesanan.
     */
    public static function lookupUrl($subscriptionNo) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $path = '/software-showcase/?order=' . rawurlencode($subscriptionNo);
        return $host ? "{$scheme}://{$host}{$path}" : $path;
    }
}
