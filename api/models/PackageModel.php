<?php
require_once __DIR__ . '/../config/database.php';

class PackageModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT p.*, a.name as application_name, a.code as application_code,
                   (SELECT COUNT(*) FROM package_modules pm WHERE pm.package_id = p.id) as modules_count
            FROM packages p
            JOIN applications a ON p.application_id = a.id
            WHERE p.deleted_at IS NULL
            ORDER BY p.application_id ASC, p.sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPublicByAppCode($appCode) {
        $stmt = $this->db->prepare("
            SELECT p.id, p.code, p.name, p.description, p.base_price, p.billing_cycle, p.max_users, p.future_module_access,
                   a.code as application_code, a.name as application_name
            FROM packages p
            JOIN applications a ON p.application_id = a.id
            WHERE a.code = :app_code AND a.deleted_at IS NULL AND p.deleted_at IS NULL
              AND a.status = 'active' AND p.status = 'active' AND p.is_public = 1
            ORDER BY p.sort_order ASC, p.base_price ASC
        ");
        $stmt->execute(['app_code' => strtoupper(trim($appCode))]);
        $packages = $stmt->fetchAll();

        $modStmt = $this->db->prepare("
            SELECT m.code, m.name FROM package_modules pm
            JOIN modules m ON pm.module_id = m.id
            WHERE pm.package_id = :package_id AND m.deleted_at IS NULL
            ORDER BY m.sort_order ASC
        ");
        foreach ($packages as &$package) {
            $modStmt->execute(['package_id' => $package['id']]);
            $package['modules'] = $modStmt->fetchAll();
        }
        return $packages;
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM packages WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $package = $stmt->fetch();

        if ($package) {
            $modStmt = $this->db->prepare("
                SELECT m.*, pm.additional_price 
                FROM package_modules pm
                JOIN modules m ON pm.module_id = m.id
                WHERE pm.package_id = :package_id AND m.deleted_at IS NULL
            ");
            $modStmt->execute(['package_id' => $id]);
            $package['modules'] = $modStmt->fetchAll();
        }

        return $package;
    }

    public function create($data) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO packages (application_id, code, name, slug, description, base_price, billing_cycle, max_users, future_module_access, status, is_public, sort_order)
                VALUES (:application_id, :code, :name, :slug, :description, :base_price, :billing_cycle, :max_users, :future_module_access, :status, :is_public, :sort_order)
            ");
            $stmt->execute([
                'application_id' => $data['application_id'],
                'code' => strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
                'slug' => strtolower(trim($data['slug'] ?? str_replace(' ', '-', $data['name']))),
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'] ?? 0.00,
                'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
                'max_users' => $data['max_users'] ?? 1,
                'future_module_access' => !empty($data['future_module_access']) ? 1 : 0,
                'status' => $data['status'] ?? 'active',
                'is_public' => isset($data['is_public']) ? ($data['is_public'] ? 1 : 0) : 1,
                'sort_order' => $data['sort_order'] ?? 0
            ]);
            $packageId = $this->db->lastInsertId();

            if (!empty($data['module_ids']) && is_array($data['module_ids'])) {
                $pmStmt = $this->db->prepare("INSERT INTO package_modules (package_id, module_id, additional_price) VALUES (:package_id, :module_id, 0.00)");
                foreach ($data['module_ids'] as $moduleId) {
                    $pmStmt->execute(['package_id' => $packageId, 'module_id' => $moduleId]);
                }
            }

            $this->db->commit();
            return $packageId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
