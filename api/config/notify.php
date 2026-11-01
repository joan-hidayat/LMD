<?php
/**
 * Konfigurasi notifikasi ke customer (T-43).
 *
 * channel:
 *   'mailbox'  — kotak surat lokal: notifikasi disimpan di tabel notifications dan dibaca di dashboard LMD
 *                (menu Notifikasi). Tidak ada yang dikirim keluar. Default untuk pengembangan.
 *   'smtp'     — 📋 rencana: kirim email sungguhan (isi blok smtp di notify.local.php).
 *   'whatsapp' — 📋 rencana: lewat gateway WhatsApp / n8n.
 * Override per server: api/config/notify.local.php (di-ignore git).
 */
$config = [
    'channel' => 'mailbox',
    'from_name' => 'Central SaaS',
    'from_email' => 'no-reply@central-saas.local',
    // Hari sebelum masa aktif habis untuk pengingat (database/notify.php)
    'reminder_days' => [7, 1],
    'smtp' => [
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
    ],
];

if (file_exists(__DIR__ . '/notify.local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/notify.local.php');
}
return $config;
