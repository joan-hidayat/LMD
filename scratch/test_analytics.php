<?php
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance()->getConnection();

echo "=== 5. Monthly Revenue Trend ===\n";
$trend = $db->query("
    SELECT DATE_FORMAT(start_date, '%Y-%m') as ym,
           DATE_FORMAT(start_date, '%b %Y') as label,
           COALESCE(SUM(total_price), 0) as revenue,
           COUNT(id) as count
    FROM subscriptions
    GROUP BY ym, label
    ORDER BY ym ASC
")->fetchAll();
print_r($trend);

echo "=== 6. Expiring Licenses Attention List ===\n";
$expList = $db->query("
    SELECT l.id, l.license_key, l.expires_at, l.status,
           DATEDIFF(l.expires_at, NOW()) as days_left,
           c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
           a.name as application_name, p.name as package_name
    FROM licenses l
    JOIN subscriptions s ON l.subscription_id = s.id
    JOIN customers c ON s.customer_id = c.id
    JOIN applications a ON l.application_id = a.id
    JOIN packages p ON s.package_id = p.id
    WHERE l.expires_at IS NOT NULL AND (DATEDIFF(l.expires_at, NOW()) <= 30 OR l.status = 'expired')
    ORDER BY days_left ASC
")->fetchAll();
print_r($expList);
