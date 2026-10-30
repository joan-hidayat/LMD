<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/LicenseModel.php';

/**
 * Self-Service Order Flow (Software Showcase -> Central SaaS)
 *
 * order        : customer + subscription 'pending' + license 'suspended' + instance (subdomain)
 * order_status : polling oleh Showcase; license key hanya dibuka setelah approve
 * approve      : subscription & license 'active', masa aktif dihitung dari tanggal approve
 * reject       : subscription 'cancelled', license 'revoked'
 *
 * Lisensi memakai status 'suspended' selama pending karena enum licenses.status
 * tidak memiliki 'pending' (tanpa perubahan schema). Status pesanan dibaca dari subscriptions.status.
 */
class OrderModel {
    const TAX_RATE = 0.11;
    const INSTANCE_DOMAIN_SUFFIX = '.pos.local';

    private $db;
    private $licenseModel;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->licenseModel = new LicenseModel();
    }

    public function createOrder($data) {
        $appCode = strtoupper(trim($data['app_code'] ?? ''));
        $pkgCode = strtoupper(trim($data['package_code'] ?? ''));
        $custName = trim($data['customer_name'] ?? '');
        $custEmail = strtolower(trim($data['customer_email'] ?? ''));
        $custPhone = trim($data['customer_phone'] ?? '') ?: null;
        $storeName = trim($data['store_name'] ?? '') ?: $custName;
        $subdomain = strtolower(trim($data['subdomain'] ?? ''));

        if (!$appCode || !$pkgCode || !$custName || !$custEmail || !$subdomain) {
            throw new InvalidArgumentException('app_code, package_code, customer_name, customer_email, and subdomain are required.');
        }
        if (!filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('customer_email is not a valid email address.');
        }
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/', $subdomain)) {
            throw new InvalidArgumentException('subdomain must be 3-40 characters: lowercase letters, numbers, and hyphens (not at the start or end).');
        }
        $domain = $subdomain . self::INSTANCE_DOMAIN_SUFFIX;

        $this->db->beginTransaction();
        try {
            $package = $this->findOrderablePackage($appCode, $pkgCode);

            $dupStmt = $this->db->prepare("
                SELECT ai.id FROM application_instances ai
                JOIN licenses l ON ai.license_id = l.id
                WHERE ai.domain = :domain AND l.status <> 'revoked'
                LIMIT 1
            ");
            $dupStmt->execute(['domain' => $domain]);
            if ($dupStmt->fetch()) {
                throw new DomainException("Subdomain '{$domain}' is already in use.");
            }

            $customerId = $this->findOrCreateCustomer($custName, $custEmail, $custPhone);

            $basePrice = (float) $package['base_price'];
            $taxAmount = round($basePrice * self::TAX_RATE, 2);
            $totalPrice = $basePrice + $taxAmount;
            $subNo = 'SUB-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $inSub = $this->db->prepare("
                INSERT INTO subscriptions (subscription_no, customer_id, application_id, package_id, start_date, end_date, billing_cycle, max_users, max_instances, base_price, tax_amount, total_price, status)
                VALUES (:sub_no, :customer_id, :app_id, :package_id, CURDATE(), NULL, :billing_cycle, :max_users, :max_instances, :base_price, :tax_amount, :total_price, 'pending')
            ");
            $inSub->execute([
                'sub_no' => $subNo,
                'customer_id' => $customerId,
                'app_id' => $package['application_id'],
                'package_id' => $package['id'],
                'billing_cycle' => $package['billing_cycle'],
                'max_users' => $package['max_users'],
                'max_instances' => $package['max_instances'],
                'base_price' => $basePrice,
                'tax_amount' => $taxAmount,
                'total_price' => $totalPrice
            ]);
            $subId = $this->db->lastInsertId();

            $pmStmt = $this->db->prepare("SELECT module_id FROM package_modules WHERE package_id = :pkg_id");
            $pmStmt->execute(['pkg_id' => $package['id']]);
            $modIds = array_column($pmStmt->fetchAll(), 'module_id');

            $inSubMod = $this->db->prepare("INSERT INTO subscription_modules (subscription_id, module_id, source, price) VALUES (:sub_id, :mod_id, 'package', 0.00)");
            foreach ($modIds as $mId) {
                $inSubMod->execute(['sub_id' => $subId, 'mod_id' => $mId]);
            }

            $licKey = SecurityHelper::generateLicenseKey($appCode);
            $inLic = $this->db->prepare("INSERT INTO licenses (license_key, subscription_id, application_id, issued_at, expires_at, status) VALUES (:lic_key, :sub_id, :app_id, NOW(), NULL, 'suspended')");
            $inLic->execute(['lic_key' => $licKey, 'sub_id' => $subId, 'app_id' => $package['application_id']]);
            $licId = $this->db->lastInsertId();

            $inLicMod = $this->db->prepare("INSERT INTO license_modules (license_id, module_id) VALUES (:lic_id, :mod_id)");
            foreach ($modIds as $mId) {
                $inLicMod->execute(['lic_id' => $licId, 'mod_id' => $mId]);
            }

            // Instance key mengikuti konvensi client Mini POS: INST-STORE-{8 karakter terakhir license key}
            $instanceKey = 'INST-STORE-' . substr($licKey, -8);
            $inInst = $this->db->prepare("INSERT INTO application_instances (license_id, instance_key, instance_name, domain, environment, status) VALUES (:lic_id, :inst_key, :inst_name, :domain, 'production', 'inactive')");
            $inInst->execute(['lic_id' => $licId, 'inst_key' => $instanceKey, 'inst_name' => $storeName, 'domain' => $domain]);
            $instId = $this->db->lastInsertId();

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $result = [
            'subscription_no' => $subNo,
            'status' => 'pending',
            'customer_name' => $custName,
            'customer_email' => $custEmail,
            'store_name' => $storeName,
            'package_code' => $package['code'],
            'package_name' => $package['name'],
            'max_users' => (int) $package['max_users'],
            'max_instances' => (int) $package['max_instances'],
            'billing_cycle' => $package['billing_cycle'],
            'base_price' => $basePrice,
            'tax_amount' => $taxAmount,
            'total_price' => $totalPrice,
            'payment_method' => $data['payment_method'] ?? null,
            'instance_domain' => $domain
        ];
        $this->log($licId, $instId, 'order', $data, $result);
        return $result;
    }

    public function getOrderStatus($subscriptionNo) {
        $order = $this->findOrder($subscriptionNo);
        if (!$order) {
            return null;
        }

        $isApproved = $order['subscription_status'] === 'active' && $order['license_status'] === 'active';
        return [
            'subscription_no' => $order['subscription_no'],
            'status' => $order['subscription_status'],
            'is_approved' => $isApproved,
            'is_rejected' => $order['subscription_status'] === 'cancelled',
            'app_code' => $order['app_code'],
            'app_name' => $order['app_name'],
            'package_name' => $order['package_name'],
            'instance_domain' => $order['domain'],
            'license_key' => $isApproved ? $order['license_key'] : null,
            'expires_at' => $isApproved ? $order['expires_at'] : null,
            // null = aplikasi on-premise: customer memasukkan license key saat instalasi
            'app_url' => $isApproved ? $this->buildAppUrl($order['launch_url'], $order['domain'], $order['license_key']) : null
        ];
    }

    public function approveOrder($subscriptionNo) {
        $this->db->beginTransaction();
        try {
            $order = $this->findOrder($subscriptionNo, true);
            if (!$order) {
                throw new OutOfBoundsException("Order '{$subscriptionNo}' not found.");
            }
            if ($order['subscription_status'] !== 'pending') {
                throw new DomainException("Order '{$subscriptionNo}' is already {$order['subscription_status']}.");
            }

            $endDate = $this->calculateEndDate($order['billing_cycle']);

            $this->db->prepare("UPDATE subscriptions SET status = 'active', start_date = CURDATE(), end_date = :end_date WHERE id = :id")
                ->execute(['end_date' => $endDate, 'id' => $order['subscription_id']]);
            $this->db->prepare("UPDATE licenses SET status = 'active', issued_at = NOW(), expires_at = :expires_at WHERE id = :id")
                ->execute(['expires_at' => $endDate ? $endDate . ' 23:59:59' : null, 'id' => $order['license_id']]);
            $this->db->prepare("UPDATE application_instances SET status = 'active' WHERE id = :id")
                ->execute(['id' => $order['instance_id']]);

            $actKey = SecurityHelper::generateActivationKey();
            $this->db->prepare("INSERT INTO license_activations (license_id, instance_id, activation_key, activated_at, ip_address, status) VALUES (:lic_id, :inst_id, :act_key, NOW(), :ip, 'active')")
                ->execute(['lic_id' => $order['license_id'], 'inst_id' => $order['instance_id'], 'act_key' => $actKey, 'ip' => SecurityHelper::getClientIp()]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $result = $this->getOrderStatus($subscriptionNo);
        $this->log($order['license_id'], $order['instance_id'], 'approve_order', ['subscription_no' => $subscriptionNo], $result);
        return $result;
    }

    public function rejectOrder($subscriptionNo, $reason = null) {
        $this->db->beginTransaction();
        try {
            $order = $this->findOrder($subscriptionNo, true);
            if (!$order) {
                throw new OutOfBoundsException("Order '{$subscriptionNo}' not found.");
            }
            if ($order['subscription_status'] !== 'pending') {
                throw new DomainException("Order '{$subscriptionNo}' is already {$order['subscription_status']}.");
            }

            $this->db->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE id = :id")->execute(['id' => $order['subscription_id']]);
            $this->db->prepare("UPDATE licenses SET status = 'revoked' WHERE id = :id")->execute(['id' => $order['license_id']]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $result = $this->getOrderStatus($subscriptionNo);
        $this->log($order['license_id'], $order['instance_id'], 'reject_order', ['subscription_no' => $subscriptionNo, 'reason' => $reason], $result);
        return $result;
    }

    public function listPending() {
        $stmt = $this->db->prepare("
            SELECT s.subscription_no, s.total_price, s.created_at,
                   c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                   a.code as app_code, p.code as package_code, p.name as package_name, s.max_users,
                   (SELECT ai.domain FROM application_instances ai JOIN licenses l ON ai.license_id = l.id
                    WHERE l.subscription_id = s.id ORDER BY ai.id ASC LIMIT 1) as instance_domain
            FROM subscriptions s
            JOIN customers c ON s.customer_id = c.id
            JOIN applications a ON s.application_id = a.id
            JOIN packages p ON s.package_id = p.id
            WHERE s.status = 'pending'
            ORDER BY s.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function findOrderablePackage($appCode, $pkgCode) {
        $stmt = $this->db->prepare("
            SELECT p.* FROM packages p
            JOIN applications a ON p.application_id = a.id
            WHERE a.code = :app_code AND p.code = :pkg_code
              AND a.deleted_at IS NULL AND p.deleted_at IS NULL
              AND a.status = 'active' AND p.status = 'active' AND p.is_public = 1
        ");
        $stmt->execute(['app_code' => $appCode, 'pkg_code' => $pkgCode]);
        $package = $stmt->fetch();
        if (!$package) {
            throw new OutOfBoundsException("Package '{$pkgCode}' for application '{$appCode}' is not available.");
        }
        return $package;
    }

    private function findOrCreateCustomer($name, $email, $phone) {
        $stmt = $this->db->prepare("SELECT id FROM customers WHERE email = :email AND deleted_at IS NULL");
        $stmt->execute(['email' => $email]);
        $customer = $stmt->fetch();
        if ($customer) {
            return $customer['id'];
        }

        $code = 'CUST-' . strtoupper(bin2hex(random_bytes(3)));
        $this->db->prepare("INSERT INTO customers (code, name, email, phone, status) VALUES (:code, :name, :email, :phone, 'active')")
            ->execute(['code' => $code, 'name' => $name, 'email' => $email, 'phone' => $phone]);
        return $this->db->lastInsertId();
    }

    private function findOrder($subscriptionNo, $forUpdate = false) {
        $stmt = $this->db->prepare("
            SELECT s.id as subscription_id, s.subscription_no, s.status as subscription_status, s.billing_cycle,
                   p.name as package_name, a.code as app_code, a.name as app_name, a.launch_url,
                   l.id as license_id, l.license_key, l.status as license_status, l.expires_at,
                   ai.id as instance_id, ai.domain
            FROM subscriptions s
            JOIN packages p ON s.package_id = p.id
            JOIN applications a ON s.application_id = a.id
            JOIN licenses l ON l.subscription_id = s.id
            LEFT JOIN application_instances ai ON ai.license_id = l.id
            WHERE s.subscription_no = :sub_no
            ORDER BY l.id ASC, ai.id ASC
            LIMIT 1
        " . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute(['sub_no' => $subscriptionNo]);
        return $stmt->fetch();
    }

    private function calculateEndDate($billingCycle) {
        $intervals = [
            'monthly' => '+1 month',
            'quarterly' => '+3 months',
            'semi_annual' => '+6 months',
            'annual' => '+1 year'
        ];
        return isset($intervals[$billingCycle]) ? date('Y-m-d', strtotime($intervals[$billingCycle])) : null;
    }

    /**
     * URL aplikasi dari template applications.launch_url (lihat docs/STANDAR_INTEGRASI.md).
     * Placeholder: {tenant}, {license_key}. Template berawalan "/" memakai host yang sama dengan LMD.
     */
    private function buildAppUrl($template, $domain, $licenseKey) {
        if (empty($template)) {
            return null;
        }
        $url = strtr($template, ['{tenant}' => rawurlencode((string) $domain), '{license_key}' => rawurlencode($licenseKey)]);
        if ($url[0] === '/') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080') . $url;
        }
        return $url;
    }

    private function log($licenseId, $instanceId, $action, $request, $response) {
        // admin_password dari Showcase tidak pernah disimpan maupun dicatat
        unset($request['admin_password']);
        $this->licenseModel->logAction(
            $licenseId, $instanceId, $action, 'REQ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
            SecurityHelper::getClientIp(), $request, $response, 'success'
        );
    }
}
