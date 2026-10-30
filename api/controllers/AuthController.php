<?php
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../models/LicenseModel.php';

class AuthController {
    public function me() {
        $admin = current_admin();
        if (!$admin) {
            $setupNeeded = (new SystemUserModel())->count() === 0;
            sendJsonResponse(['login_required' => true, 'setup_needed' => $setupNeeded], 401, 'Belum login.');
        }
        sendJsonResponse($admin, 200, 'Logged in.');
    }

    public function login() {
        $input = getJsonInput();
        $email = strtolower(trim($input['email'] ?? ''));
        [$admin, $error] = (new SystemUserModel())->attemptLogin($email, (string) ($input['password'] ?? ''));

        (new LicenseModel())->logAction(null, null, 'admin_login',
            'REQ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))), SecurityHelper::getClientIp(),
            ['email' => $email], ['success' => (bool) $admin], $admin ? 'success' : 'failed');

        if (!$admin) {
            sendJsonResponse(null, 401, $error);
        }
        login_admin($admin);
        sendJsonResponse($admin, 200, 'Login berhasil.');
    }

    public function logout() {
        logout_admin();
        sendJsonResponse(null, 200, 'Logout berhasil.');
    }
}
