<?php
require_once __DIR__ . '/../config/database.php';

class LicenseLogModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($limit = 50) {
        $stmt = $this->db->prepare("
            SELECT lg.*, l.license_key, ai.instance_key, ai.instance_name
            FROM license_logs lg
            LEFT JOIN licenses l ON lg.license_id = l.id
            LEFT JOIN application_instances ai ON lg.instance_id = ai.id
            ORDER BY lg.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getDashboardStats() {
        $stats = [];

        // Total Apps
        $appStmt = $this->db->query("SELECT COUNT(*) as count FROM applications WHERE deleted_at IS NULL");
        $stats['total_apps'] = (int) $appStmt->fetch()['count'];

        // Total Customers
        $custStmt = $this->db->query("SELECT COUNT(*) as count FROM customers WHERE deleted_at IS NULL");
        $stats['total_customers'] = (int) $custStmt->fetch()['count'];

        // Active Subscriptions
        $subStmt = $this->db->query("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'");
        $stats['active_subscriptions'] = (int) $subStmt->fetch()['count'];

        // Active Licenses
        $licStmt = $this->db->query("SELECT COUNT(*) as count FROM licenses WHERE status = 'active'");
        $stats['active_licenses'] = (int) $licStmt->fetch()['count'];

        // Active Instances
        $instStmt = $this->db->query("SELECT COUNT(*) as count FROM application_instances WHERE status = 'active'");
        $stats['active_instances'] = (int) $instStmt->fetch()['count'];

        // Total Revenue
        $revStmt = $this->db->query("SELECT COALESCE(SUM(total_price), 0) as total FROM subscriptions WHERE status = 'active'");
        $stats['total_revenue'] = (float) $revStmt->fetch()['total'];

        return $stats;
    }
}
