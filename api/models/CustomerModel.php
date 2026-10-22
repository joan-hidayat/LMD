<?php
require_once __DIR__ . '/../config/database.php';

class CustomerModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM subscriptions s WHERE s.customer_id = c.id) as subscriptions_count
            FROM customers c
            WHERE c.deleted_at IS NULL
            ORDER BY c.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO customers (code, name, email, phone, status)
            VALUES (:code, :name, :email, :phone, :status)
        ");
        $code = !empty($data['code']) ? strtoupper(trim($data['code'])) : 'CUST-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt->execute([
            'code' => $code,
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }
}
