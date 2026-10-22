<?php
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance()->getConnection();

echo "=== 1. Max Users Distribution ===\n";
$userDist = $db->query("
    SELECT 
        SUM(CASE WHEN max_users <= 3 THEN 1 ELSE 0 END) as tier_starter,
        SUM(CASE WHEN max_users > 3 AND max_users <= 10 THEN 1 ELSE 0 END) as tier_medium,
        SUM(CASE WHEN max_users > 10 THEN 1 ELSE 0 END) as tier_enterprise
    FROM subscriptions
    WHERE status = 'active'
")->fetch();
print_r($userDist);

echo "=== 2. Billing Cycle Breakdown ===\n";
$cycles = $db->query("
    SELECT billing_cycle, COUNT(*) as count, SUM(total_price) as total_rev
    FROM subscriptions
    WHERE status = 'active'
    GROUP BY billing_cycle
")->fetchAll();
print_r($cycles);

echo "=== 3. Instance Activity (Last Seen) ===\n";
$instActivity = $db->query("
    SELECT 
        SUM(CASE WHEN last_seen_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as active_24h,
        SUM(CASE WHEN last_seen_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as idle_7d,
        SUM(CASE WHEN last_seen_at < DATE_SUB(NOW(), INTERVAL 7 DAY) OR last_seen_at IS NULL THEN 1 ELSE 0 END) as dormant
    FROM application_instances
")->fetch();
print_r($instActivity);

echo "=== 4. API Validation Success vs Failed ===\n";
$apiLogs = $db->query("
    SELECT 
        SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed_count
    FROM license_logs
")->fetch();
print_r($apiLogs);
