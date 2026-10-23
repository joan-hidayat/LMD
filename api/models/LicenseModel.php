<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/security.php';

class LicenseModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT l.*, 
                   s.subscription_no, s.max_users, s.end_date as sub_expires,
                   c.name as customer_name, c.email as customer_email,
                   a.name as application_name, a.code as application_code,
                   (SELECT COUNT(*) FROM application_instances ai WHERE ai.license_id = l.id) as active_instances_count,
                   (SELECT COUNT(*) FROM license_modules lm WHERE lm.license_id = l.id) as modules_count
            FROM licenses l
            JOIN subscriptions s ON l.subscription_id = s.id
            JOIN customers c ON s.customer_id = c.id
            JOIN applications a ON l.application_id = a.id
            ORDER BY l.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT l.*, s.subscription_no, s.max_users, c.name as customer_name, a.name as application_name, a.code as application_code
            FROM licenses l
            JOIN subscriptions s ON l.subscription_id = s.id
            JOIN customers c ON s.customer_id = c.id
            JOIN applications a ON l.application_id = a.id
            WHERE l.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $license = $stmt->fetch();

        if ($license) {
            // Get Modules
            $modStmt = $this->db->prepare("
                SELECT m.id, m.code, m.name, m.module_type 
                FROM license_modules lm
                JOIN modules m ON lm.module_id = m.id
                WHERE lm.license_id = :license_id
            ");
            $modStmt->execute(['license_id' => $id]);
            $license['modules'] = $modStmt->fetchAll();

            // Get Instances
            $instStmt = $this->db->prepare("
                SELECT * FROM application_instances WHERE license_id = :license_id ORDER BY id DESC
            ");
            $instStmt->execute(['license_id' => $id]);
            $license['instances'] = $instStmt->fetchAll();
        }

        return $license;
    }

    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE licenses SET status = :status, updated_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Core License Validation API for Client Apps (Apotek, TMS, etc.)
     */
    public function validateLicense($licenseKey, $instanceKey, $domain = null, $ipAddress = null) {
        $ip = $ipAddress ?? SecurityHelper::getClientIp();
        $requestId = 'REQ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $requestData = [
            'license_key' => $licenseKey,
            'instance_key' => $instanceKey,
            'domain' => $domain,
            'ip' => $ip
        ];

        // 1. Fetch License
        $stmt = $this->db->prepare("
            SELECT l.*, s.max_users, s.status as sub_status, a.code as app_code, a.name as app_name
            FROM licenses l
            JOIN subscriptions s ON l.subscription_id = s.id
            JOIN applications a ON l.application_id = a.id
            WHERE l.license_key = :license_key
        ");
        $stmt->execute(['license_key' => $licenseKey]);
        $license = $stmt->fetch();

        if (!$license) {
            $this->logAction(null, null, 'validate', $requestId, $ip, $requestData, ['valid' => false, 'reason' => 'License key not found'], 'failed');
            return ['valid' => false, 'message' => 'Invalid or non-existent license key.'];
        }

        // Check License Status
        if ($license['status'] !== 'active') {
            $this->logAction($license['id'], null, 'validate', $requestId, $ip, $requestData, ['valid' => false, 'reason' => "License is {$license['status']}"], 'failed');
            return ['valid' => false, 'message' => "License status is {$license['status']}."];
        }

        // Check Expiry
        if (!empty($license['expires_at']) && strtotime($license['expires_at']) < time()) {
            $this->updateStatus($license['id'], 'expired');
            $this->logAction($license['id'], null, 'validate', $requestId, $ip, $requestData, ['valid' => false, 'reason' => 'License expired'], 'failed');
            return ['valid' => false, 'message' => 'License has expired. Please renew subscription.'];
        }

        // 2. Fetch or Register Instance
        $instStmt = $this->db->prepare("SELECT * FROM application_instances WHERE instance_key = :instance_key AND license_id = :license_id");
        $instStmt->execute(['instance_key' => $instanceKey, 'license_id' => $license['id']]);
        $instance = $instStmt->fetch();

        if (!$instance) {
            // Auto-register instance if missing
            $regStmt = $this->db->prepare("
                INSERT INTO application_instances (license_id, instance_key, instance_name, domain, environment, last_seen_at, status)
                VALUES (:license_id, :instance_key, :instance_name, :domain, 'production', NOW(), 'active')
            ");
            $regStmt->execute([
                'license_id' => $license['id'],
                'instance_key' => $instanceKey,
                'instance_name' => "Instance (" . ($domain ?? $ip) . ")",
                'domain' => $domain
            ]);
            $instanceId = $this->db->lastInsertId();
        } else {
            $instanceId = $instance['id'];
            // Update last seen & domain
            $upInst = $this->db->prepare("UPDATE application_instances SET last_seen_at = NOW(), domain = COALESCE(:domain, domain) WHERE id = :id");
            $upInst->execute(['domain' => $domain, 'id' => $instanceId]);
        }

        // Update license last checked
        $upLic = $this->db->prepare("UPDATE licenses SET last_checked_at = NOW() WHERE id = :id");
        $upLic->execute(['id' => $license['id']]);

        // 3. Fetch Entitled Modules
        $modStmt = $this->db->prepare("
            SELECT m.code, m.name, m.module_type
            FROM license_modules lm
            JOIN modules m ON lm.module_id = m.id
            WHERE lm.license_id = :license_id AND m.status = 'active'
        ");
        $modStmt->execute(['license_id' => $license['id']]);
        $entitledModules = $modStmt->fetchAll();

        $moduleCodes = array_column($entitledModules, 'code');

        $responseData = [
            'valid' => true,
            'license_key' => $license['license_key'],
            'application' => $license['app_name'],
            'app_code' => $license['app_code'],
            'expires_at' => $license['expires_at'],
            'max_users' => (int) $license['max_users'],
            'modules' => $moduleCodes
        ];

        // 4. Log Success Audit
        $this->logAction($license['id'], $instanceId, 'validate', $requestId, $ip, $requestData, $responseData, 'success');

        return $responseData;
    }

    /**
     * Audit Log Helper
     */
    public function logAction($licenseId, $instanceId, $action, $requestId, $ip, $reqData, $respData, $result) {
        $stmt = $this->db->prepare("
            INSERT INTO license_logs (license_id, instance_id, action, request_id, ip_address, request_data, response_data, result)
            VALUES (:license_id, :instance_id, :action, :request_id, :ip_address, :request_data, :response_data, :result)
        ");
        $stmt->execute([
            'license_id' => $licenseId,
            'instance_id' => $instanceId,
            'action' => $action,
            'request_id' => $requestId,
            'ip_address' => $ip,
            'request_data' => json_encode($reqData),
            'response_data' => json_encode($respData),
            'result' => $result
        ]);
    }

    /**
     * Auto-Register Customer, Subscription, License, and Instance from Client App Installation
     */
    public function registerAutoLicense($data) {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();
        try {
            $appCode = strtoupper(trim($data['app_code'] ?? 'APOTEK'));
            $pkgCode = strtoupper(trim($data['package_code'] ?? 'APO_BASIC'));
            $custName = trim($data['customer_name'] ?? 'Customer Baru');
            $custEmail = trim($data['customer_email'] ?? 'info@customer.com');
            $custPhone = $data['customer_phone'] ?? null;
            $instanceKey = $data['instance_key'] ?? SecurityHelper::generateInstanceKey($appCode, $custName);
            $domain = $data['domain'] ?? null;
            $ip = SecurityHelper::getClientIp();

            // 1. Fetch App & Package
            $appStmt = $db->prepare("SELECT id, name FROM applications WHERE code = :code AND deleted_at IS NULL");
            $appStmt->execute(['code' => $appCode]);
            $app = $appStmt->fetch();
            if (!$app) throw new Exception("Application with code '{$appCode}' not found.");

            $pkgStmt = $db->prepare("SELECT * FROM packages WHERE code = :code AND application_id = :app_id AND deleted_at IS NULL");
            $pkgStmt->execute(['code' => $pkgCode, 'app_id' => $app['id']]);
            $package = $pkgStmt->fetch();
            if (!$package) throw new Exception("Package with code '{$pkgCode}' not found.");

            // 2. Find or Create Customer
            $custStmt = $db->prepare("SELECT id FROM customers WHERE email = :email AND deleted_at IS NULL");
            $custStmt->execute(['email' => $custEmail]);
            $customer = $custStmt->fetch();

            if (!$customer) {
                $custCode = 'CUST-' . strtoupper(bin2hex(random_bytes(3)));
                $inCust = $db->prepare("INSERT INTO customers (code, name, email, phone, status) VALUES (:code, :name, :email, :phone, 'active')");
                $inCust->execute(['code' => $custCode, 'name' => $custName, 'email' => $custEmail, 'phone' => $custPhone]);
                $customerId = $db->lastInsertId();
            } else {
                $customerId = $customer['id'];
            }

            // 3. Create Subscription
            $subNo = 'SUB-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime('+1 year'));

            $inSub = $db->prepare("
                INSERT INTO subscriptions (subscription_no, customer_id, application_id, package_id, start_date, end_date, billing_cycle, max_users, base_price, total_price, status)
                VALUES (:sub_no, :customer_id, :app_id, :package_id, :start_date, :end_date, :billing_cycle, :max_users, :base_price, :total_price, 'active')
            ");
            $inSub->execute([
                'sub_no' => $subNo,
                'customer_id' => $customerId,
                'app_id' => $app['id'],
                'package_id' => $package['id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'billing_cycle' => $package['billing_cycle'],
                'max_users' => $package['max_users'],
                'base_price' => $package['base_price'],
                'total_price' => $package['base_price']
            ]);
            $subId = $db->lastInsertId();

            // 4. Copy package modules to subscription_modules
            $pmStmt = $db->prepare("SELECT module_id FROM package_modules WHERE package_id = :pkg_id");
            $pmStmt->execute(['pkg_id' => $package['id']]);
            $pkgMods = $pmStmt->fetchAll();

            $inSubMod = $db->prepare("INSERT INTO subscription_modules (subscription_id, module_id, source, price) VALUES (:sub_id, :mod_id, 'package', 0.00)");
            $modIds = [];
            foreach ($pkgMods as $pm) {
                $inSubMod->execute(['sub_id' => $subId, 'mod_id' => $pm['module_id']]);
                $modIds[] = $pm['module_id'];
            }

            // 5. Generate License Key
            $licKey = SecurityHelper::generateLicenseKey($appCode);
            $inLic = $db->prepare("INSERT INTO licenses (license_key, subscription_id, application_id, issued_at, expires_at, status) VALUES (:lic_key, :sub_id, :app_id, NOW(), :expires_at, 'active')");
            $inLic->execute(['lic_key' => $licKey, 'sub_id' => $subId, 'app_id' => $app['id'], 'expires_at' => $endDate . ' 23:59:59']);
            $licId = $db->lastInsertId();

            // 6. Insert license_modules
            $inLicMod = $db->prepare("INSERT INTO license_modules (license_id, module_id) VALUES (:lic_id, :mod_id)");
            foreach ($modIds as $mId) {
                $inLicMod->execute(['lic_id' => $licId, 'mod_id' => $mId]);
            }

            // 7. Register Instance
            $inInst = $db->prepare("INSERT INTO application_instances (license_id, instance_key, instance_name, domain, environment, last_seen_at, status) VALUES (:lic_id, :inst_key, :inst_name, :domain, 'production', NOW(), 'active')");
            $inInst->execute(['lic_id' => $licId, 'inst_key' => $instanceKey, 'inst_name' => $custName . ' Instance', 'domain' => $domain]);
            $instId = $db->lastInsertId();

            // 8. Create Activation
            $actKey = SecurityHelper::generateActivationKey();
            $inAct = $db->prepare("INSERT INTO license_activations (license_id, instance_id, activation_key, activated_at, last_seen_at, ip_address, status) VALUES (:lic_id, :inst_id, :act_key, NOW(), NOW(), :ip, 'active')");
            $inAct->execute(['lic_id' => $licId, 'inst_id' => $instId, 'act_key' => $actKey, 'ip' => $ip]);

            // Get module codes
            $modCodeStmt = $db->prepare("SELECT code FROM modules WHERE id IN (" . implode(',', array_map('intval', $modIds)) . ")");
            $modCodeStmt->execute();
            $modCodes = array_column($modCodeStmt->fetchAll(), 'code');

            $db->commit();

            return [
                'auto_registered' => true,
                'customer_name' => $custName,
                'customer_email' => $custEmail,
                'application' => $app['name'],
                'package' => $package['name'],
                'license_key' => $licKey,
                'instance_key' => $instanceKey,
                'activation_key' => $actKey,
                'max_users' => (int) $package['max_users'],
                'modules' => $modCodes
            ];
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    

    /**
     * COMMERCIAL PHASE: Create Pending Order from Showcase
     */
    public function createPendingOrder($data) {
        $db = $this->db;
        $db->beginTransaction();

        try {
            $appCode = $data['app_code'] ?? 'APOTEK';
            $pkgCode = $data['package_code'] ?? 'APO_PRO';
            $custName = $data['customer_name'] ?? 'Pelanggan Baru';
            $custEmail = $data['customer_email'];
            $custPhone = $data['customer_phone'] ?? '08123456789';
            $subdomain = $data['subdomain'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $custName));
            $paymentMethod = $data['payment_method'] ?? 'QRIS';
            $paymentRef = $data['payment_ref'] ?? ('PAY-' . strtoupper(bin2hex(random_bytes(4))));

            // 1. Fetch App & Package
            $appStmt = $db->prepare("SELECT id, name, slug FROM applications WHERE code = :code AND deleted_at IS NULL");
            $appStmt->execute(['code' => $appCode]);
            $app = $appStmt->fetch();
            if (!$app) throw new Exception("Application with code '{$appCode}' not found.");

            $pkgStmt = $db->prepare("SELECT * FROM packages WHERE code = :code AND application_id = :app_id AND deleted_at IS NULL");
            $pkgStmt->execute(['code' => $pkgCode, 'app_id' => $app['id']]);
            $package = $pkgStmt->fetch();
            if (!$package) {
                // Fallback to first package of app
                $pkgStmt2 = $db->prepare("SELECT * FROM packages WHERE application_id = :app_id AND deleted_at IS NULL LIMIT 1");
                $pkgStmt2->execute(['app_id' => $app['id']]);
                $package = $pkgStmt2->fetch();
            }

            // 2. Customer
            $custStmt = $db->prepare("SELECT id FROM customers WHERE email = :email AND deleted_at IS NULL");
            $custStmt->execute(['email' => $custEmail]);
            $customer = $custStmt->fetch();

            if (!$customer) {
                $custCode = 'CUST-' . strtoupper(bin2hex(random_bytes(3)));
                $inCust = $db->prepare("INSERT INTO customers (code, name, email, phone, status) VALUES (:code, :name, :email, :phone, 'active')");
                $inCust->execute(['code' => $custCode, 'name' => $custName, 'email' => $custEmail, 'phone' => $custPhone]);
                $customerId = $db->lastInsertId();
            } else {
                $customerId = $customer['id'];
            }

            // 3. Subscription (status = pending)
            $subNo = 'SUB-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime('+1 year'));

            $inSub = $db->prepare("
                INSERT INTO subscriptions (subscription_no, customer_id, application_id, package_id, start_date, end_date, billing_cycle, max_users, base_price, total_price, status)
                VALUES (:sub_no, :customer_id, :app_id, :package_id, :start_date, :end_date, :billing_cycle, :max_users, :base_price, :total_price, 'pending')
            ");
            $inSub->execute([
                'sub_no' => $subNo,
                'customer_id' => $customerId,
                'app_id' => $app['id'],
                'package_id' => $package['id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'billing_cycle' => $package['billing_cycle'],
                'max_users' => $package['max_users'],
                'base_price' => $package['base_price'],
                'total_price' => $package['base_price']
            ]);
            $subId = $db->lastInsertId();

            // 4. Copy package modules
            $pmStmt = $db->prepare("SELECT module_id FROM package_modules WHERE package_id = :pkg_id");
            $pmStmt->execute(['pkg_id' => $package['id']]);
            $pkgMods = $pmStmt->fetchAll();

            $inSubMod = $db->prepare("INSERT INTO subscription_modules (subscription_id, module_id, source, price) VALUES (:sub_id, :mod_id, 'package', 0.00)");
            $modIds = [];
            foreach ($pkgMods as $pm) {
                $inSubMod->execute(['sub_id' => $subId, 'mod_id' => $pm['module_id']]);
                $modIds[] = $pm['module_id'];
            }

            // 5. Generate License Key (status = suspended until payment approved)
            $licKey = SecurityHelper::generateLicenseKey($appCode);
            $inLic = $db->prepare("INSERT INTO licenses (license_key, subscription_id, application_id, issued_at, expires_at, status) VALUES (:lic_key, :sub_id, :app_id, NOW(), :expires_at, 'suspended')");
            $inLic->execute(['lic_key' => $licKey, 'sub_id' => $subId, 'app_id' => $app['id'], 'expires_at' => $endDate . ' 23:59:59']);
            $licId = $db->lastInsertId();

            // 6. Insert license_modules
            $inLicMod = $db->prepare("INSERT INTO license_modules (license_id, module_id) VALUES (:lic_id, :mod_id)");
            foreach ($modIds as $mId) {
                $inLicMod->execute(['lic_id' => $licId, 'mod_id' => $mId]);
            }

            // 7. Register Instance
            $instanceKey = 'INST-' . strtoupper(substr($appCode, 0, 4)) . '-' . strtoupper(bin2hex(random_bytes(3)));
            $inInst = $db->prepare("INSERT INTO application_instances (license_id, instance_key, instance_name, domain, environment, status) VALUES (:lic_id, :inst_key, :inst_name, :domain, 'production', 'pending')");
            $inInst->execute([
                'lic_id' => $licId,
                'inst_key' => $instanceKey,
                'inst_name' => $custName . ' Store',
                'domain' => $subdomain . '.pos.local'
            ]);
            $instId = $db->lastInsertId();

            // Log Audit Trail (Digital Trail in LMD)
            $requestId = 'ORD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $auditPayload = [
                'event' => 'CLIENT_REGISTRATION_ORDER',
                'customer_name' => $custName,
                'customer_email' => $custEmail,
                'customer_phone' => $custPhone,
                'package_code' => $pkgCode,
                'subdomain' => $subdomain . '.pos.local',
                'order_no' => 'INV-' . date('Ymd') . '-' . str_pad($subId, 4, '0', STR_PAD_LEFT),
                'subscription_no' => $subNo,
                'has_password_set' => !empty($data['admin_password'])
            ];
            $this->logAction($licId, $instId, 'order_registration', $requestId, $ip, $auditPayload, ['status' => 'pending_payment'], 'success');

            $db->commit();

            return [
                'order_no' => 'INV-' . date('Ymd') . '-' . str_pad($subId, 4, '0', STR_PAD_LEFT),
                'subscription_no' => $subNo,
                'customer_name' => $custName,
                'customer_email' => $custEmail,
                'app_name' => $app['name'],
                'package_name' => $package['name'],
                'max_users' => (int) $package['max_users'],
                'total_amount' => (float) $package['base_price'],
                'payment_method' => $paymentMethod,
                'payment_ref' => $paymentRef,
                'license_key' => $licKey,
                'instance_domain' => $subdomain . '.pos.local',
                'status' => 'pending_payment'
            ];
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * PROVISIONING PHASE: Admin Approves Payment & Provisions Client Domain/Instance
     */
    public function approveOrder($subNo) {
        $stmt = $this->db->prepare("
            SELECT s.id as sub_id, s.customer_id, s.application_id, l.id as lic_id, l.license_key, 
                   ai.id as inst_id, ai.domain, ai.instance_key, c.name as customer_name
            FROM subscriptions s
            JOIN licenses l ON l.subscription_id = s.id
            JOIN customers c ON s.customer_id = c.id
            LEFT JOIN application_instances ai ON ai.license_id = l.id
            WHERE s.subscription_no = :sub_no
        ");
        $stmt->execute(['sub_no' => $subNo]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception("Order with subscription {$subNo} not found.");
        }

        // 1. Activate Subscription
        $this->db->prepare("UPDATE subscriptions SET status = 'active', updated_at = NOW() WHERE id = :id")->execute(['id' => $order['sub_id']]);

        // 2. Activate License
        $this->db->prepare("UPDATE licenses SET status = 'active', updated_at = NOW() WHERE id = :id")->execute(['id' => $order['lic_id']]);

        // 3. Activate Application Instance
        if ($order['inst_id']) {
            $this->db->prepare("UPDATE application_instances SET status = 'active', last_seen_at = NOW() WHERE id = :id")->execute(['id' => $order['inst_id']]);
        }

        // 4. Create Activation Record
        $actKey = SecurityHelper::generateActivationKey();
        $inAct = $this->db->prepare("INSERT INTO license_activations (license_id, instance_id, activation_key, activated_at, last_seen_at, status) VALUES (:lic_id, :inst_id, :act_key, NOW(), NOW(), 'active')");
        $inAct->execute([
            'lic_id' => $order['lic_id'],
            'inst_id' => $order['inst_id'],
            'act_key' => $actKey
        ]);

        // Log Approval Audit Trail
        $requestId = 'APP-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $auditApproval = [
            'event' => 'SUBSCRIPTION_ORDER_APPROVED',
            'subscription_no' => $subNo,
            'customer_name' => $order['customer_name'],
            'license_key' => $order['license_key'],
            'domain' => $order['domain'],
            'status' => 'activated'
        ];
        $this->logAction($order['lic_id'], $order['inst_id'], 'order_approved', $requestId, SecurityHelper::getClientIp(), $auditApproval, ['status' => 'active'], 'success');

        return [
            'approved' => true,
            'subscription_no' => $subNo,
            'status' => 'active',
            'customer_name' => $order['customer_name'],
            'license_key' => $order['license_key'],
            'client_domain' => $order['domain'],
            'instance_url' => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080') . '/mini-pos/?tenant=' . urlencode($order['domain']) . '&lic=' . urlencode($order['license_key'])
        ];
    }

    /**
     * RUNTIME PHASE: Get Order / Approval Status (Polling by Client Screen)
     */
    public function getOrderStatus($subNo) {
        $stmt = $this->db->prepare("
            SELECT s.subscription_no, s.status as sub_status, s.total_price,
                   l.license_key, l.status as license_status,
                   c.name as customer_name,
                   a.name as app_name,
                   ai.domain, ai.instance_key
            FROM subscriptions s
            JOIN customers c ON s.customer_id = c.id
            JOIN applications a ON s.application_id = a.id
            LEFT JOIN licenses l ON l.subscription_id = s.id
            LEFT JOIN application_instances ai ON ai.license_id = l.id
            WHERE s.subscription_no = :sub_no
        ");
        $stmt->execute(['sub_no' => $subNo]);
        $res = $stmt->fetch();

        if (!$res) return null;

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $res['is_approved'] = ($res['sub_status'] === 'active');
        $res['app_url'] = 'http://' . $host . '/mini-pos/?tenant=' . urlencode($res['domain'] ?? 'store') . '&lic=' . urlencode($res['license_key']);

        return $res;
    }

    /**
     * Get All Pending Orders for Admin Dashboard
     */
    public function getPendingOrders() {
        $stmt = $this->db->prepare("
            SELECT s.id as sub_id, s.subscription_no, s.total_price, s.created_at, s.status as sub_status,
                   c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                   a.name as app_name, p.name as package_name, p.max_users,
                   l.license_key,
                   ai.domain
            FROM subscriptions s
            JOIN customers c ON s.customer_id = c.id
            JOIN applications a ON s.application_id = a.id
            JOIN packages p ON s.package_id = p.id
            LEFT JOIN licenses l ON l.subscription_id = s.id
            LEFT JOIN application_instances ai ON ai.license_id = l.id
            WHERE s.status = 'pending'
            ORDER BY s.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
