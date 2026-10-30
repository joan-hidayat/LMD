<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/LicenseModel.php';

/**
 * Link reset password admin aplikasi client (T-21).
 *
 * LMD tidak menyimpan akun user aplikasi client. LMD hanya menerbitkan token sekali pakai untuk sebuah
 * lisensi; aplikasi client memverifikasi token itu ke LMD lalu mengganti password admin di database-nya sendiri.
 * Yang disimpan hanya hash SHA-256 token; token asli hanya tampil sekali kepada admin LMD.
 */
class AdminResetModel {
    const TTL_HOURS = 24;

    private $db;
    private $licenseModel;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->licenseModel = new LicenseModel();
    }

    /**
     * Menerbitkan token baru; token lama yang belum dipakai untuk lisensi ini langsung tidak berlaku.
     */
    public function issue($licenseId) {
        $stmt = $this->db->prepare("
            SELECT l.id, l.license_key, l.status, a.code as app_code, a.launch_url,
                   (SELECT ai.domain FROM application_instances ai
                    WHERE ai.license_id = l.id AND ai.status <> 'blocked' AND ai.domain IS NOT NULL
                    ORDER BY ai.id ASC LIMIT 1) as tenant
            FROM licenses l JOIN applications a ON a.id = l.application_id
            WHERE l.id = :id
        ");
        $stmt->execute(['id' => $licenseId]);
        $license = $stmt->fetch();
        if (!$license) {
            throw new OutOfBoundsException('License not found.');
        }
        if ($license['status'] !== 'active') {
            throw new DomainException("License is {$license['status']}; reset links are only issued for active licenses.");
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_HOURS * 3600);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM admin_reset_tokens WHERE license_id = :id AND used_at IS NULL")
                ->execute(['id' => $licenseId]);
            $this->db->prepare("INSERT INTO admin_reset_tokens (license_id, token_hash, expires_at) VALUES (:id, :hash, :expires)")
                ->execute(['id' => $licenseId, 'hash' => hash('sha256', $token), 'expires' => $expiresAt]);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $result = [
            'token' => $token,
            'expires_at' => $expiresAt,
            'tenant' => $license['tenant'],
            // null = aplikasi on-premise / belum ada instalasi: berikan token untuk dimasukkan manual
            'reset_url' => $this->buildResetUrl($license['launch_url'], $license['tenant'], $token),
        ];
        $this->log($licenseId, 'admin_reset_issued', ['expires_at' => $expiresAt], ['issued' => true]);
        return $result;
    }

    /**
     * Dipanggil aplikasi client. $consume = false hanya memeriksa (saat halaman reset dibuka),
     * $consume = true memeriksa sekaligus menghanguskan token (saat password baru disimpan).
     * Mengembalikan ['valid' => bool, 'reason' => ...].
     */
    public function check($licenseKey, $token, $consume) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT t.id, t.license_id, t.expires_at, t.used_at, l.status as license_status
                FROM admin_reset_tokens t JOIN licenses l ON l.id = t.license_id
                WHERE t.token_hash = :hash AND l.license_key = :license_key
                FOR UPDATE
            ");
            $stmt->execute(['hash' => hash('sha256', (string) $token), 'license_key' => $licenseKey]);
            $row = $stmt->fetch();

            $reason = null;
            if (!$row) {
                $reason = 'reset_token_invalid';
            } elseif ($row['used_at'] !== null) {
                $reason = 'reset_token_used';
            } elseif (strtotime($row['expires_at']) < time()) {
                $reason = 'reset_token_expired';
            } elseif ($row['license_status'] !== 'active') {
                $reason = 'license_inactive';
            }

            if (!$reason && $consume) {
                $this->db->prepare("UPDATE admin_reset_tokens SET used_at = NOW() WHERE id = :id")->execute(['id' => $row['id']]);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        if ($row && ($consume || $reason)) {
            $this->log($row['license_id'], $consume ? 'admin_reset_used' : 'admin_reset_checked',
                ['consume' => (bool) $consume], ['valid' => !$reason, 'reason' => $reason], $reason ? 'failed' : 'success');
        }
        return $reason ? ['valid' => false, 'reason' => $reason] : ['valid' => true, 'expires_at' => $row['expires_at']];
    }

    /**
     * Link reset = path dari launch_url aplikasi + ?tenant=…&reset_token=… (lihat docs/STANDAR_INTEGRASI.md).
     */
    private function buildResetUrl($launchUrl, $tenant, $token) {
        if (empty($launchUrl) || empty($tenant)) {
            return null;
        }
        $base = strtok($launchUrl, '?');
        if ($base[0] === '/') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080') . $base;
        }
        return $base . '?' . http_build_query(['tenant' => $tenant, 'reset_token' => $token]);
    }

    private function log($licenseId, $action, $request, $response, $result = 'success') {
        $this->licenseModel->logAction($licenseId, null, $action,
            'REQ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
            SecurityHelper::getClientIp(), $request, $response, $result);
    }
}
