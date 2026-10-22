<?php
require_once __DIR__ . '/../config/database.php';

class AnalyticsModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Expiration Radar: Segments licenses by expiration proximity
     */
    public function getExpiringRadar() {
        $stmt = $this->db->query("
            SELECT 
                SUM(CASE WHEN expires_at < NOW() OR status = 'expired' THEN 1 ELSE 0 END) as expired_count,
                SUM(CASE WHEN status = 'active' AND expires_at >= NOW() AND expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as critical_7d,
                SUM(CASE WHEN status = 'active' AND expires_at > DATE_ADD(NOW(), INTERVAL 7 DAY) AND expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as warning_30d,
                SUM(CASE WHEN status = 'active' AND expires_at > DATE_ADD(NOW(), INTERVAL 30 DAY) AND expires_at <= DATE_ADD(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as safe_90d,
                SUM(CASE WHEN status = 'active' AND (expires_at > DATE_ADD(NOW(), INTERVAL 90 DAY) OR expires_at IS NULL) THEN 1 ELSE 0 END) as long_term
            FROM licenses
        ");
        $res = $stmt->fetch();
        return [
            'expired' => (int)($res['expired_count'] ?? 0),
            'critical_7d' => (int)($res['critical_7d'] ?? 0),
            'warning_30d' => (int)($res['warning_30d'] ?? 0),
            'safe_90d' => (int)($res['safe_90d'] ?? 0),
            'long_term' => (int)($res['long_term'] ?? 0),
        ];
    }

    /**
     * Licenses expiring soon (<= 30 days) or already expired for immediate follow-up
     */
    public function getExpiringAttentionList($limit = 5) {
        $stmt = $this->db->prepare("
            SELECT l.id, l.license_key, l.expires_at, l.status,
                   DATEDIFF(l.expires_at, NOW()) as days_left,
                   c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                   a.name as application_name, a.code as application_code,
                   p.name as package_name, s.subscription_no, s.total_price
            FROM licenses l
            JOIN subscriptions s ON l.subscription_id = s.id
            JOIN customers c ON s.customer_id = c.id
            JOIN applications a ON l.application_id = a.id
            JOIN packages p ON s.package_id = p.id
            WHERE l.expires_at IS NOT NULL AND (DATEDIFF(l.expires_at, NOW()) <= 30 OR l.status = 'expired')
            ORDER BY days_left ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Revenue contribution per application
     */
    public function getRevenueByApplication() {
        $stmt = $this->db->query("
            SELECT a.id, a.name as application_name, a.code as application_code,
                   COALESCE(SUM(s.total_price), 0) as total_revenue,
                   COUNT(s.id) as total_subscriptions
            FROM applications a
            LEFT JOIN subscriptions s ON s.application_id = a.id AND s.status = 'active'
            WHERE a.deleted_at IS NULL
            GROUP BY a.id, a.name, a.code
            ORDER BY total_revenue DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * License status distribution
     */
    public function getLicenseStatusDistribution() {
        $stmt = $this->db->query("
            SELECT status, COUNT(*) as count
            FROM licenses
            GROUP BY status
        ");
        $rows = $stmt->fetchAll();
        $dist = ['active' => 0, 'expired' => 0, 'suspended' => 0, 'revoked' => 0];
        foreach ($rows as $r) {
            $dist[$r['status']] = (int)$r['count'];
        }
        return $dist;
    }

    /**
     * Top adopted modules
     */
    public function getModulePopularity($limit = 7) {
        $stmt = $this->db->prepare("
            SELECT m.id, m.name as module_name, m.code as module_code, a.code as app_code,
                   COUNT(lm.license_id) as adoption_count
            FROM modules m
            JOIN applications a ON m.application_id = a.id
            LEFT JOIN license_modules lm ON m.id = lm.module_id
            WHERE m.deleted_at IS NULL
            GROUP BY m.id, m.name, m.code, a.code
            ORDER BY adoption_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Monthly revenue and subscription trend
     */
    public function getMonthlyRevenueTrend() {
        $stmt = $this->db->query("
            SELECT DATE_FORMAT(start_date, '%Y-%m') as ym,
                   DATE_FORMAT(start_date, '%b %Y') as label,
                   COALESCE(SUM(total_price), 0) as revenue,
                   COUNT(id) as count
            FROM subscriptions
            GROUP BY ym, label
            ORDER BY ym ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Package billing cycle distribution
     */
    public function getBillingCycleDistribution() {
        $stmt = $this->db->query("
            SELECT billing_cycle, COUNT(*) as count, COALESCE(SUM(total_price), 0) as revenue
            FROM subscriptions
            WHERE status = 'active'
            GROUP BY billing_cycle
        ");
        return $stmt->fetchAll();
    }

    /**
     * User tier distribution (max_users capacity tiers)
     */
    public function getUserTierDistribution() {
        $stmt = $this->db->query("
            SELECT 
                SUM(CASE WHEN max_users <= 3 THEN 1 ELSE 0 END) as tier_starter,
                SUM(CASE WHEN max_users > 3 AND max_users <= 10 THEN 1 ELSE 0 END) as tier_medium,
                SUM(CASE WHEN max_users > 10 THEN 1 ELSE 0 END) as tier_enterprise
            FROM subscriptions
            WHERE status = 'active'
        ");
        $res = $stmt->fetch();
        return [
            'starter' => (int)($res['tier_starter'] ?? 0),
            'medium' => (int)($res['tier_medium'] ?? 0),
            'enterprise' => (int)($res['tier_enterprise'] ?? 0)
        ];
    }

    /**
     * API Validation Traffic Health (Success vs Failed)
     */
    public function getApiHealthStats() {
        $stmt = $this->db->query("
            SELECT 
                SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed_count
            FROM license_logs
        ");
        $res = $stmt->fetch();
        return [
            'success' => (int)($res['success_count'] ?? 0),
            'failed' => (int)($res['failed_count'] ?? 0)
        ];
    }

    /**
     * Instance Activity & Health (last_seen_at)
     */
    public function getInstanceActivityStats() {
        $stmt = $this->db->query("
            SELECT 
                SUM(CASE WHEN last_seen_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as active_24h,
                SUM(CASE WHEN last_seen_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as idle_7d,
                SUM(CASE WHEN last_seen_at < DATE_SUB(NOW(), INTERVAL 7 DAY) OR last_seen_at IS NULL THEN 1 ELSE 0 END) as dormant
            FROM application_instances
        ");
        $res = $stmt->fetch();
        return [
            'active_24h' => (int)($res['active_24h'] ?? 0),
            'idle_7d' => (int)($res['idle_7d'] ?? 0),
            'dormant' => (int)($res['dormant'] ?? 0)
        ];
    }
}
