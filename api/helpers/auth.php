<?php
/**
 * Sesi admin dashboard LMD (T-04).
 *
 * Cookie HttpOnly + SameSite=Strict (permintaan dari situs lain tidak membawa sesi), berlaku untuk
 * seluruh folder LMD agar halaman dashboard dan /api memakai sesi yang sama. Sesi berakhir setelah
 * IDLE_TIMEOUT detik tanpa aktivitas.
 */
require_once __DIR__ . '/../models/SystemUserModel.php';
require_once __DIR__ . '/../models/ApiTokenModel.php';

const ADMIN_IDLE_TIMEOUT = 7200;

function start_admin_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $appPath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')), '/\\');
    session_name('LMDSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $appPath . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Admin yang sedang login, atau null. Memperpanjang sesi bila masih aktif.
 */
function current_admin() {
    start_admin_session();
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    if (time() - ($_SESSION['admin_last_activity'] ?? 0) > ADMIN_IDLE_TIMEOUT) {
        logout_admin();
        return null;
    }
    $admin = (new SystemUserModel())->findById((int) $_SESSION['admin_id']);
    if (!$admin) {
        logout_admin();
        return null;
    }
    $_SESSION['admin_last_activity'] = time();
    return $admin;
}

function login_admin(array $admin) {
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_last_activity'] = time();
}

function logout_admin() {
    start_admin_session();
    $_SESSION = [];
    session_regenerate_id(true);
}

/**
 * Menghentikan request dengan 401 bila belum login admin. Token API otomasi (Authorization: Bearer lmdtok_…)
 * diterima hanya untuk endpoint dalam scope token itu.
 */
function require_admin($endpoint = null) {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        // Apache (mod_php) kadang tidak meneruskan Authorization ke $_SERVER
        $header = array_change_key_case(getallheaders(), CASE_LOWER)['authorization'] ?? '';
    }
    if ($endpoint && preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        $token = (new ApiTokenModel())->authorize($m[1], $endpoint);
        if ($token) {
            return ['id' => null, 'name' => 'token:' . $token['name'], 'email' => null];
        }
        sendJsonResponse(null, 403, 'Token API tidak valid atau tidak berhak mengakses endpoint ini.');
    }
    $admin = current_admin();
    if (!$admin) {
        sendJsonResponse(['login_required' => true], 401, 'Login admin diperlukan.');
    }
    return $admin;
}
