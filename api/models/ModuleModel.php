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

    public function create($data) {
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
