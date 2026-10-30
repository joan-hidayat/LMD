<?php
/**
 * Sesi admin dashboard LMD (T-04).
 *
 * Cookie HttpOnly + SameSite=Strict (permintaan dari situs lain tidak membawa sesi), berlaku untuk
 * seluruh folder LMD agar halaman dashboard dan /api memakai sesi yang sama. Sesi berakhir setelah
 * IDLE_TIMEOUT detik tanpa aktivitas.
 */
require_once __DIR__ . '/../models/SystemUserModel.php';

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
 * Menghentikan request dengan 401 bila belum login.
 */
function require_admin() {
    $admin = current_admin();
    if (!$admin) {
        sendJsonResponse(['login_required' => true], 401, 'Login admin diperlukan.');
    }
    return $admin;
}
