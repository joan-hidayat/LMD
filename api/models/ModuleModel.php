<?php
require_once __DIR__ . '/../config/database.php';

class ModuleModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByAppId($appId) {
        $stmt = $this->db->prepare("
            SELECT m.*, a.name as application_name 
            FROM modules m
            JOIN applications a ON m.application_id = a.id
            WHERE m.application_id = :app_id AND m.deleted_at IS NULL
            ORDER BY m.sort_order ASC, m.id ASC
        ");
        $stmt->execute(['app_id' => $appId]);
        return $stmt->fetchAll();
    }

    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT m.*, a.name as application_name, a.code as application_code 
            FROM modules m
            JOIN applications a ON m.application_id = a.id
            WHERE m.deleted_at IS NULL
            ORDER BY m.application_id ASC, m.sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Membuat modul baru sekaligus memberikannya ke paket/subscription/lisensi dengan future_module_access (T-13).
     * Mengembalikan [id modul, ringkasan pemberian].
     */
    public function create($data) {
        $this->db->beginTransaction();
        try {
            $moduleId = $this->insert($data);
            $granted = self::syncFutureModules($this->db, (int) $moduleId);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return [$moduleId, $granted];
    }

    /**
     * Aturan "future module" (ERD level 9, bagian 17): modul yang dibuat SETELAH sebuah paket/subscription
     * dengan future_module_access = 1 otomatis masuk ke paket itu, subscription-nya (source 'future_module'),
     * dan lisensinya, tanpa customer perlu upgrade. Modul yang sudah ada saat paket dibuat tidak disentuh,
     * sehingga isi paket yang sengaja dipilih admin tetap utuh.
     *
     * Idempoten (INSERT IGNORE + unique key). $moduleId null = sinkronisasi semua modul (dipakai migrate.php).
     */
    public static function syncFutureModules(PDO $db, $moduleId = null) {
        $moduleFilter = $moduleId ? ' AND m.id = :module_id' : '';
        $params = $moduleId ? ['module_id' => $moduleId] : [];

        $packages = $db->prepare("
            INSERT IGNORE INTO package_modules (package_id, module_id, additional_price)
            SELECT p.id, m.id, 0.00
            FROM packages p
            JOIN modules m ON m.application_id = p.application_id
            WHERE p.future_module_access = 1 AND p.deleted_at IS NULL
              AND m.status = 'active' AND m.deleted_at IS NULL AND m.created_at > p.created_at{$moduleFilter}
        ");
        $packages->execute($params);

        $subscriptions = $db->prepare("
            INSERT IGNORE INTO subscription_modules (subscription_id, module_id, source, price)
            SELECT s.id, m.id, 'future_module', 0.00
            FROM subscriptions s
            JOIN packages p ON p.id = s.package_id
            JOIN modules m ON m.application_id = s.application_id
            WHERE p.future_module_access = 1 AND s.status IN ('pending', 'active', 'suspended')
              AND m.status = 'active' AND m.deleted_at IS NULL AND m.created_at > s.created_at{$moduleFilter}
        ");
        $subscriptions->execute($params);

        // Lisensi yang akan menerima modul baru dicatat dulu untuk audit log
        $pending = $db->prepare("
            SELECT l.id as license_id, sm.module_id, m.code as module_code
            FROM licenses l
            JOIN subscription_modules sm ON sm.subscription_id = l.subscription_id AND sm.source = 'future_module'
            JOIN modules m ON m.id = sm.module_id
            LEFT JOIN license_modules lm ON lm.license_id = l.id AND lm.module_id = sm.module_id
            WHERE l.status <> 'revoked' AND lm.id IS NULL{$moduleFilter}
        ");
        $pending->execute($params);
        $grants = $pending->fetchAll();

        $insert = $db->prepare("INSERT IGNORE INTO license_modules (license_id, module_id) VALUES (:license_id, :module_id)");
        $log = $db->prepare("
            INSERT INTO license_logs (license_id, action, request_id, request_data, response_data, result)
            VALUES (:license_id, 'future_module_granted', :request_id, :request_data, :response_data, 'success')
        ");
        foreach ($grants as $grant) {
            $insert->execute(['license_id' => $grant['license_id'], 'module_id' => $grant['module_id']]);
            $log->execute([
                'license_id' => $grant['license_id'],
                'request_id' => 'REQ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'request_data' => json_encode(['module_code' => $grant['module_code'], 'rule' => 'future_module_access']),
                'response_data' => json_encode(['granted' => true]),
            ]);
        }

        return [
            'packages' => $packages->rowCount(),
            'subscriptions' => $subscriptions->rowCount(),
            'licenses' => count($grants),
        ];
    }

    private function insert($data) {
        $stmt = $this->db->prepare("
            INSERT INTO modules (application_id, code, name, slug, module_type, description, status, sort_order)
            VALUES (:application_id, :code, :name, :slug, :module_type, :description, :status, :sort_order)
        ");
        $stmt->execute([
            'application_id' => $data['application_id'],
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'slug' => strtolower(trim($data['slug'] ?? str_replace(' ', '-', $data['name']))),
            'module_type' => $data['module_type'] ?? 'standard',
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? 0
        ]);
        return $this->db->lastInsertId();
    }

    public function delete($id) {
        $stmt = $this->db->prepare("UPDATE modules SET deleted_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
