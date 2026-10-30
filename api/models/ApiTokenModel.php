<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Token API untuk otomasi (mis. n8n menyetujui pembayaran), T-04.
 * Token hanya ditampilkan sekali saat dibuat; yang disimpan hanya hash SHA-256.
 * Token tidak memberi akses penuh admin: hanya endpoint di SCOPES.
 */
class ApiTokenModel {
    const PREFIX = 'lmdtok_';
    const SCOPES = [
        'orders' => ['licenses/pending_orders', 'licenses/approve_order', 'licenses/reject_order', 'licenses/order_status'],
    ];

    private $db;

    public function __construct(PDO $db = null) {
        $this->db = $db ?: Database::getInstance()->getConnection();
    }

    public function create($name, $scope = 'orders') {
        if (!isset(self::SCOPES[$scope])) {
            throw new InvalidArgumentException("Scope '{$scope}' tidak dikenal. Pilihan: " . implode(', ', array_keys(self::SCOPES)));
        }
        $token = self::PREFIX . bin2hex(random_bytes(24));
        $this->db->prepare("INSERT INTO api_tokens (name, scope, token_hash) VALUES (:name, :scope, :hash)")
            ->execute(['name' => trim($name) ?: 'token', 'scope' => $scope, 'hash' => hash('sha256', $token)]);
        return $token;
    }

    public function revoke($id) {
        $stmt = $this->db->prepare("UPDATE api_tokens SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function all() {
        return $this->db->query("SELECT id, name, scope, last_used_at, created_at, revoked_at FROM api_tokens ORDER BY id")->fetchAll();
    }

    /**
     * Token aktif yang boleh mengakses endpoint ini, atau null.
     */
    public function authorize($token, $endpoint) {
        if (strpos((string) $token, self::PREFIX) !== 0) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT id, name, scope FROM api_tokens WHERE token_hash = :hash AND revoked_at IS NULL");
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row || !in_array($endpoint, self::SCOPES[$row['scope']] ?? [], true)) {
            return null;
        }
        $this->db->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id")->execute(['id' => $row['id']]);
        return $row;
    }
}
