<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/security.php';

class SubscriptionModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                   a.name as application_name, a.code as application_code,
                   p.name as package_name, p.code as package_code,
                   l.license_key, l.status as license_status,
                   (SELECT ai.domain FROM application_instances ai WHERE ai.license_id = l.id ORDER BY ai.id ASC LIMIT 1) as instance_domain
            FROM subscriptions s
            JOIN customers c ON s.customer_id = c.id
            JOIN applications a ON s.application_id = a.id
            JOIN packages p ON s.package_id = p.id
            LEFT JOIN licenses l ON l.subscription_id = s.id
            ORDER BY s.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        $this->db->beginTransaction();
        try {
            // Get Package details
            $pkgStmt = $this->db->prepare("SELECT * FROM packages WHERE id = :id");
            $pkgStmt->execute(['id' => $data['package_id']]);
            $package = $pkgStmt->fetch();

            if (!$package) {
                throw new Exception("Package not found");
            }

            $subNo = 'SUB-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $startDate = $data['start_date'] ?? date('Y-m-d');
            $endDate = $data['end_date'] ?? date('Y-m-d', strtotime('+1 year', strtotime($startDate)));
            $maxUsers = $data['max_users'] ?? $package['max_users'];
            $basePrice = $data['base_price'] ?? $package['base_price'];
            $discount = $data['discount_amount'] ?? 0.00;
            $tax = $data['tax_amount'] ?? ($basePrice * 0.11);
            $totalPrice = ($basePrice - $discount) + $tax;

            // 1. Insert Subscription
            $subStmt = $this->db->prepare("
                INSERT INTO subscriptions 
                (subscription_no, customer_id, application_id, package_id, start_date, end_date, billing_cycle, max_users, base_price, addon_price, discount_amount, tax_amount, total_price, status)
                VALUES 
                (:sub_no, :customer_id, :application_id, :package_id, :start_date, :end_date, :billing_cycle, :max_users, :base_price, 0.00, :discount_amount, :tax_amount, :total_price, 'active')
            ");
            $subStmt->execute([
                'sub_no' => $subNo,
                'customer_id' => $data['customer_id'],
                'application_id' => $package['application_id'],
                'package_id' => $package['id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'billing_cycle' => $data['billing_cycle'] ?? $package['billing_cycle'],
                'max_users' => $maxUsers,
                'base_price' => $basePrice,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_price' => $totalPrice
            ]);
            $subscriptionId = $this->db->lastInsertId();

            // 2. Fetch package modules and insert subscription_modules
            $pmStmt = $this->db->prepare("SELECT module_id, additional_price FROM package_modules WHERE package_id = :package_id");
            $pmStmt->execute(['package_id' => $package['id']]);
            $pkgModules = $pmStmt->fetchAll();

            $smStmt = $this->db->prepare("INSERT INTO subscription_modules (subscription_id, module_id, source, price) VALUES (:sub_id, :mod_id, 'package', :price)");
            $moduleIds = [];
            foreach ($pkgModules as $pm) {
                $smStmt->execute([
                    'sub_id' => $subscriptionId,
                    'mod_id' => $pm['module_id'],
                    'price' => $pm['additional_price']
                ]);
                $moduleIds[] = $pm['module_id'];
            }

            // Get Application Code for License Key Prefix
            $appStmt = $this->db->prepare("SELECT code FROM applications WHERE id = :id");
            $appStmt->execute(['id' => $package['application_id']]);
            $app = $appStmt->fetch();

            // 3. Generate License
            $licenseKey = SecurityHelper::generateLicenseKey($app['code'] ?? 'APP');
            $licStmt = $this->db->prepare("
                INSERT INTO licenses (license_key, subscription_id, application_id, issued_at, expires_at, status)
                VALUES (:license_key, :subscription_id, :application_id, NOW(), :expires_at, 'active')
            ");
            $licStmt->execute([
                'license_key' => $licenseKey,
                'subscription_id' => $subscriptionId,
                'application_id' => $package['application_id'],
                'expires_at' => $endDate . ' 23:59:59'
            ]);
            $licenseId = $this->db->lastInsertId();

            // 4. Insert license_modules
            $lmStmt = $this->db->prepare("INSERT INTO license_modules (license_id, module_id) VALUES (:license_id, :module_id)");
            foreach ($moduleIds as $modId) {
                $lmStmt->execute(['license_id' => $licenseId, 'module_id' => $modId]);
            }

            $this->db->commit();
            return [
                'subscription_id' => $subscriptionId,
                'subscription_no' => $subNo,
                'license_id' => $licenseId,
                'license_key' => $licenseKey
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
