<?php
/**
 * Konfigurasi pembayaran otomatis.
 *
 * provider : 'simulator' (pengembangan) — nanti 'midtrans' / 'xendit' setelah adapter dibuat.
 * Simulator sengaja punya secret hardcode selama pengembangan (lihat CLAUDE.md). Untuk server lain,
 * buat api/config/payment.local.php (di-ignore git) yang me-return key yang ingin diganti, misalnya:
 *   <?php return ['provider' => 'midtrans', 'simulator' => ['enabled' => false]];
 */
$config = [
    'provider' => 'simulator',
    // Masa berlaku tagihan sejak dibuat
    'expires_hours' => 24,
    // Ke mana customer diarahkan setelah membayar. Berawalan "/" = host yang sama dengan LMD.
    // {subscription_no} & {token} diganti otomatis; Showcase membuka "Cek Pesanan" untuk pesanan itu.
    'return_url' => '/software-showcase/?order={subscription_no}&t={token}',
    'simulator' => [
        'enabled' => true,
        'webhook_secret' => 'sim-dev-secret-ganti-di-production',
    ],
];

if (file_exists(__DIR__ . '/payment.local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/payment.local.php');
}
return $config;
