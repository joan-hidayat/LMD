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
            INSERT INTO applications (code, name, slug, description, launch_url, require_signature, status)
            VALUES (:code, :name, :slug, :description, :launch_url, :require_signature, :status)
        ");
        $stmt->execute([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'slug' => strtolower(trim($data['slug'] ?? str_replace(' ', '-', $data['name']))),
            'description' => $data['description'] ?? null,
            'launch_url' => trim($data['launch_url'] ?? '') ?: null,
            // Standar Integrasi: aplikasi baru wajib request bertanda tangan (T-22) kecuali dimatikan eksplisit
            'require_signature' => array_key_exists('require_signature', $data) ? (!empty($data['require_signature']) ? 1 : 0) : 1,
            'status' => $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * Mengubah identitas aplikasi. Kode (app_code) tidak bisa diubah karena dipakai Showcase & client.
     */
    public function update($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE applications SET name = :name, description = :description, launch_url = :launch_url, require_signature = :require_signature
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            'id' => $id,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'launch_url' => trim($data['launch_url'] ?? '') ?: null,
            'require_signature' => !empty($data['require_signature']) ? 1 : 0,
        ]);
        return $stmt->rowCount() > 0 || $this->getById($id);
    }

    public function delete($id) {
        $stmt = $this->db->prepare("UPDATE applications SET deleted_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
