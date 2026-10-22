<?php
require_once __DIR__ . '/../config/database.php';

class ApplicationModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT a.*, 
                (SELECT COUNT(*) FROM modules m WHERE m.application_id = a.id AND m.deleted_at IS NULL) as modules_count,
                (SELECT COUNT(*) FROM packages p WHERE p.application_id = a.id AND p.deleted_at IS NULL) as packages_count,
                (SELECT COUNT(*) FROM licenses l WHERE l.application_id = a.id) as licenses_count
            FROM applications a 
            WHERE a.deleted_at IS NULL 
            ORDER BY a.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM applications WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO applications (code, name, slug, description, status) 
            VALUES (:code, :name, :slug, :description, :status)
        ");
        $stmt->execute([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'slug' => strtolower(trim($data['slug'] ?? str_replace(' ', '-', $data['name']))),
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE applications 
            SET code = :code, name = :name, slug = :slug, description = :description, status = :status, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        return $stmt->execute([
            'id' => $id,
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'slug' => strtolower(trim($data['slug'])),
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active'
        ]);
    }

    public function delete($id) {
        $stmt = $this->db->prepare("UPDATE applications SET deleted_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
