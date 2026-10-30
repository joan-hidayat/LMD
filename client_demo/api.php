<?php
/**
 * Backend demo client Apotek (T-29). Browser (apotek.html) hanya memanggil file ini;
 * request ke LMD dikirim dari server sesuai Standar Integrasi v1 no. 1, dan ditandatangani
 * instance secret (T-22) bila LMD menerbitkannya. Secret tidak pernah dikirim ke browser.
 *
 *   GET  api.php?action=state      → identitas instalasi demo saat ini
 *   POST api.php?action=validate   → validasi lisensi ke LMD
 *   POST api.php?action=register   → daftarkan lisensi baru (paket APO_BASIC) lalu validasi
 *
 * Demo: status instalasi (license key, instance key, domain, secret) disimpan di sesi PHP.
 * Aplikasi sungguhan menyimpannya di database / .env server (lihat Mini POS: tabel tenants).
 */
header('Content-Type: application/json; charset=utf-8');
session_name('APOTEKDEMOSESSID');
session_start();

$client = require __DIR__ . '/lmd-client.php';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$lmdApi = rtrim($client['lmd_api_url'] ?: $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/License-Management-Dashboard/api/index.php', '/');

const DEMO_DEFAULT = [
    'license_key' => 'LIC-APO-CRIT558811EE0042AA66B819',
    'instance_key' => 'INST-APO-MJJ-001',
    'domain' => 'apotekmajujaya.com',
    'secret' => null,
];

function respond($status, array $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function lmd_post($url, array $body, $secret = null) {
    $payload = json_encode($body);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($secret) {
        $timestamp = (string) time();
        $headers[] = 'X-LMD-Timestamp: ' . $timestamp;
        $headers[] = 'X-LMD-Signature: ' . hash_hmac('sha256', $timestamp . "\n" . $payload, $secret);
    }
    $raw = @file_get_contents($url, false, stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers) . "\r\n",
        'content' => $payload,
        'timeout' => 5,
        'ignore_errors' => true,
    ]]));
    return $raw === false ? null : json_decode($raw, true);
}

function public_state(array $state) {
    return [
        'license_key' => $state['license_key'],
        'instance_key' => $state['instance_key'],
        'domain' => $state['domain'],
        'signed' => !empty($state['secret']),
    ];
}

function validate_current($lmdApi, $client) {
    $state = &$_SESSION['apotek_demo'];
    $json = lmd_post($lmdApi . '/licenses/validate', [
        'license_key' => $state['license_key'],
        'instance_key' => $state['instance_key'],
        'domain' => $state['domain'],
    ], $state['secret']);
    if (!is_array($json)) {
        respond(502, ['ok' => false, 'message' => 'Server lisensi Central SaaS tidak dapat dihubungi.', 'state' => public_state($state)]);
    }
    $data = $json['data'] ?? [];
    // Secret hanya dikirim LMD sekali (validasi pertama): simpan di server, jangan diteruskan ke browser
    if (!empty($data['instance_secret'])) {
        $state['secret'] = $data['instance_secret'];
        $json['data']['instance_secret'] = '(disimpan di server demo, tidak dikirim ke browser)';
    }
    // Standar Integrasi v1: lisensi aktif milik aplikasi lain tetap ditolak
    $valid = !empty($data['valid']) && ($data['app_code'] ?? null) === $client['app_code'];
    return ['ok' => true, 'valid' => $valid, 'lmd' => $json, 'state' => public_state($state)];
}

if (empty($_SESSION['apotek_demo'])) {
    $_SESSION['apotek_demo'] = DEMO_DEFAULT;
}
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'state' && $method === 'GET') {
    respond(200, ['ok' => true, 'state' => public_state($_SESSION['apotek_demo'])]);
}
// Aksi yang mengubah data hanya lewat POST JSON (form lintas situs tidak bisa mengirim application/json tanpa preflight)
if ($method !== 'POST' || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    respond(405, ['ok' => false, 'message' => 'Gunakan POST application/json.']);
}

if ($action === 'validate') {
    respond(200, validate_current($lmdApi, $client));
}

if ($action === 'register') {
    $n = random_int(100, 999);
    $instanceKey = $client['instance_key_prefix'] . 'BAROKAH-' . $n;
    $domain = "barokah{$n}.com";
    $json = lmd_post($lmdApi . '/licenses/register', [
        'app_code' => $client['app_code'],
        'package_code' => 'APO_BASIC',
        'customer_name' => "Apotek Sehat Barokah {$n}",
        'customer_email' => "owner{$n}@sehatbarokah.com",
        'customer_phone' => "0812{$n}8899",
        'instance_key' => $instanceKey,
        'domain' => $domain,
    ]);
    if (!is_array($json)) {
        respond(502, ['ok' => false, 'message' => 'Server lisensi Central SaaS tidak dapat dihubungi.']);
    }
    if (empty($json['success']) || empty($json['data']['license_key'])) {
        respond(422, ['ok' => false, 'message' => $json['message'] ?? 'Registrasi ditolak LMD.', 'lmd' => $json]);
    }
    $_SESSION['apotek_demo'] = ['license_key' => $json['data']['license_key'], 'instance_key' => $instanceKey, 'domain' => $domain, 'secret' => null];
    $result = validate_current($lmdApi, $client);
    $result['registered'] = $json;
    respond(200, $result);
}

respond(404, ['ok' => false, 'message' => 'Aksi tidak dikenal.']);
