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
                   s.subscription_no, s.max_users, s.max_instances, s.end_date as sub_expires,
                   c.name as customer_name, c.email as customer_email,
                   a.name as application_name, a.code as application_code,
                   (SELECT COUNT(*) FROM application_instances ai WHERE ai.license_id = l.id AND ai.status <> 'blocked') as active_instances_count,
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
            SELECT l.*, s.subscription_no, s.max_users, s.max_instances, c.name as customer_name, a.name as application_name, a.code as application_code
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
                SELECT id, license_id, instance_key, instance_name, domain, environment, last_seen_at, status,
                       created_at, updated_at, (secret IS NOT NULL) as has_secret
                FROM application_instances WHERE license_id = :license_id ORDER BY id DESC
            ");
            $instStmt->execute(['license_id' => $id]);
            $license['instances'] = $instStmt->fetchAll();
        }

        return $license;
    }

    /**
     * Blokir / aktifkan instance (keputusan admin). Mengaktifkan kembali tetap dibatasi kuota instalasi.
     */
    public function setInstanceStatus($instanceId, $status) {
        $stmt = $this->db->prepare("
            SELECT ai.*, s.max_instances FROM application_instances ai
            JOIN licenses l ON ai.license_id = l.id
            JOIN subscriptions s ON l.subscription_id = s.id
            WHERE ai.id = :id
        ");
        $stmt->execute(['id' => $instanceId]);
        $instance = $stmt->fetch();
        if (!$instance) {
            throw new OutOfBoundsException('Instance not found.');
        }

        if ($status === 'active' && $instance['status'] === 'blocked') {
            $usedStmt = $this->db->prepare("SELECT COUNT(*) FROM application_instances WHERE license_id = :license_id AND status <> 'blocked'");
            $usedStmt->execute(['license_id' => $instance['license_id']]);
            if ((int) $usedStmt->fetchColumn() >= max(1, (int) $instance['max_instances'])) {
                throw new DomainException("Installation limit reached ({$instance['max_instances']}). Block another installation first.");
            }
        }

        $this->db->prepare("UPDATE application_instances SET status = :status WHERE id = :id")
            ->execute(['status' => $status, 'id' => $instanceId]);
        $this->logAction($instance['license_id'], $instanceId, 'instance_' . ($status === 'blocked' ? 'blocked' : 'activated'),
            'REQ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))), SecurityHelper::getClientIp(),
            ['instance_key' => $instance['instance_key'], 'status' => $status], ['status' => $status], 'success');
    }

    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE licenses SET status = :status, updated_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Core License Validation API for Client Apps (Apotek, TMS, etc.)
     */
    const SIGNATURE_MAX_SKEW = 300;

    /**
     * $signature (T-22): ['timestamp' => X-LMD-Timestamp, 'signature' => X-LMD-Signature, 'body' => raw request body].
     */
    public function validateLicense($licenseKey, $instanceKey, $domain = null, $ipAddress = null, array $signature = []) {
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
            SELECT l.*, s.max_users, s.max_instances, s.status as sub_status, a.code as app_code, a.name as app_name,
                   a.require_signature,
                   c.name as customer_name, c.email as customer_email
            FROM licenses l
            JOIN subscriptions s ON l.subscription_id = s.id
            JOIN applications a ON l.application_id = a.id
            JOIN customers c ON s.customer_id = c.id
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

        // 2. Instance binding (T-02): instance terdaftar dikunci ke domainnya; instance baru dibatasi kuota paket
        $instStmt = $this->db->prepare("SELECT * FROM application_instances WHERE instance_key = :instance_key");
        $instStmt->execute(['instance_key' => $instanceKey]);
        $instance = $instStmt->fetch();

        $reject = function ($reason, $message, $instanceId = null) use ($license, $requestId, $ip, $requestData) {
            $this->logAction($license['id'], $instanceId, 'validate', $requestId, $ip, $requestData, ['valid' => false, 'reason' => $reason], 'failed');
            return ['valid' => false, 'reason' => $reason, 'message' => $message];
        };

        if ($instance && (int) $instance['license_id'] !== (int) $license['id']) {
            return $reject('instance_key_conflict', 'Instance key is already registered to another license.');
        }

        if ($instance) {
            $instanceId = $instance['id'];
            $instanceName = $instance['instance_name'];
            if ($instance['status'] === 'blocked') {
                return $reject('instance_blocked', 'This installation has been blocked by the license administrator.', $instanceId);
            }
            if ($domain !== null && $instance['domain'] !== null && strcasecmp($domain, $instance['domain']) !== 0) {
                return $reject('domain_mismatch', "License instance is bound to domain '{$instance['domain']}'.", $instanceId);
            }
            // Domain hanya diisi sekali (saat pertama terikat), tidak pernah ditimpa
            $upInst = $this->db->prepare("UPDATE application_instances SET last_seen_at = NOW(), domain = COALESCE(domain, :domain) WHERE id = :id");
            $upInst->execute(['domain' => $domain, 'id' => $instanceId]);
        } else {
            $usedStmt = $this->db->prepare("SELECT COUNT(*) FROM application_instances WHERE license_id = :license_id AND status <> 'blocked'");
            $usedStmt->execute(['license_id' => $license['id']]);
            $maxInstances = max(1, (int) $license['max_instances']);
            if ((int) $usedStmt->fetchColumn() >= $maxInstances) {
                return $reject('instance_limit_reached', "Installation limit reached ({$maxInstances}). Block an unused installation or upgrade the package.");
            }

            $regStmt = $this->db->prepare("
                INSERT INTO application_instances (license_id, instance_key, instance_name, domain, environment, last_seen_at, status)
                VALUES (:license_id, :instance_key, :instance_name, :domain, 'production', NOW(), 'active')
            ");
            $instanceName = "Instance (" . ($domain ?? $ip) . ")";
            $regStmt->execute([
                'license_id' => $license['id'],
                'instance_key' => $instanceKey,
                'instance_name' => $instanceName,
                'domain' => $domain
            ]);
            $instanceId = $this->db->lastInsertId();
            $instance = ['id' => $instanceId, 'secret' => null];
        }

        // 2b. Request bertanda tangan (T-22): instalasi pertama menerima secret sekali (trust on first use),
        //     request berikutnya wajib HMAC-SHA256(secret, timestamp + "\n" + body).
        $issuedSecret = null;
        if (!empty($license['require_signature'])) {
            if (empty($instance['secret'])) {
                $issuedSecret = bin2hex(random_bytes(32));
                $this->db->prepare("UPDATE application_instances SET secret = :secret WHERE id = :id")
                    ->execute(['secret' => $issuedSecret, 'id' => $instanceId]);
            } else {
                $failure = $this->verifySignature($instance['secret'], $signature);
                if ($failure) {
                    return $reject($failure[0], $failure[1], $instanceId);
                }
            }
        }

        // Update license last checked
        $upLic = $this->db->prepare("UPDATE licenses SET last_checked_at = NOW() WHERE id = :id");
        $upLic->execute(['id' => $license['id']]);

        // 3. Fetch Entitled Modules
        $modStmt = $this->db->prepare("
            SELECT m.code, m.name, m.module_type
            FROM license_modules lm
            JOIN modules m ON lm.module_id = m.id
            WHERE lm.license_id = :license_id AND m.status = 'active' AND m.deleted_at IS NULL
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
            'max_instances' => (int) $license['max_instances'],
            'modules' => $moduleCodes,
            // Identitas pemilik lisensi & nama instans, dipakai client untuk aktivasi akun admin pertama
            'licensee' => [
                'name' => $license['customer_name'],
                'email' => $license['customer_email']
            ],
            'instance_name' => $instanceName,
            'signature' => empty($license['require_signature']) ? 'not_required' : ($issuedSecret ? 'issued' : 'verified')
        ];

        // 4. Log Success Audit (secret tidak pernah ikut dicatat)
        $this->logAction($license['id'], $instanceId, 'validate', $requestId, $ip, $requestData, $responseData, 'success');

        if ($issuedSecret) {
            // Hanya dikirim sekali; client wajib menyimpannya dan menandatangani request berikutnya
            $responseData['instance_secret'] = $issuedSecret;
        }
        return $responseData;
    }

    /**
     * Mengembalikan null bila tanda tangan sah, atau [reason, pesan].
     */
    private function verifySignature($secret, array $signature) {
        $timestamp = $signature['timestamp'] ?? '';
        $provided = strtolower((string) ($signature['signature'] ?? ''));
        if ($timestamp === '' || $provided === '') {
            return ['signature_required', 'This installation must sign requests with its instance secret.'];
        }
        if (!ctype_digit((string) $timestamp) || abs(time() - (int) $timestamp) > self::SIGNATURE_MAX_SKEW) {
            return ['signature_expired', 'Request timestamp is missing or outside the allowed window (5 minutes).'];
        }
        $expected = hash_hmac('sha256', $timestamp . "\n" . ($signature['body'] ?? ''), $secret);
        if (!hash_equals($expected, $provided)) {
            return ['signature_invalid', 'Request signature does not match this installation.'];
        }
        return null;
    }

    /**
     * Admin menghapus secret instalasi (mis. database client hilang); validasi berikutnya menerbitkan secret baru.
     */
    public function resetInstanceSecret($instanceId) {
        $stmt = $this->db->prepare("UPDATE application_instances SET secret = NULL WHERE id = :id");
        $stmt->execute(['id' => $instanceId]);
        if ($stmt->rowCount() === 0 && !$this->db->query("SELECT COUNT(*) FROM application_instances WHERE id = " . (int) $instanceId)->fetchColumn()) {
            throw new OutOfBoundsException('Instance not found.');
        }
        $licenseId = $this->db->query("SELECT license_id FROM application_instances WHERE id = " . (int) $instanceId)->fetchColumn();
        $this->logAction($licenseId, $instanceId, 'instance_secret_reset',
            'REQ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))), SecurityHelper::getClientIp(),
            ['instance_id' => (int) $instanceId], ['reset' => true], 'success');
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
                INSERT INTO subscriptions (subscription_no, customer_id, application_id, package_id, start_date, end_date, billing_cycle, max_users, max_instances, base_price, total_price, status)
                VALUES (:sub_no, :customer_id, :app_id, :package_id, :start_date, :end_date, :billing_cycle, :max_users, :max_instances, :base_price, :total_price, 'active')
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
                'max_instances' => $package['max_instances'],
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
    
}
