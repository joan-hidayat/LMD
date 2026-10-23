<?php
require_once __DIR__ . '/../models/LicenseModel.php';
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../helpers/response.php';

class LicenseController {
    private $licenseModel;
    private $orderModel;

    public function __construct() {
        $this->licenseModel = new LicenseModel();
        $this->orderModel = new OrderModel();
    }

    public function index() {
        $licenses = $this->licenseModel->getAll();
        sendJsonResponse($licenses, 200, 'Licenses fetched successfully.');
    }

    public function show($id) {
        $license = $this->licenseModel->getById($id);
        if (!$license) {
            sendJsonResponse(null, 404, 'License not found.');
        }
        sendJsonResponse($license, 200, 'License details fetched.');
    }

    public function updateStatus($id) {
        $input = getJsonInput();
        if (empty($input['status'])) {
            sendJsonResponse(null, 400, 'Status is required.');
        }

        $this->licenseModel->updateStatus($id, $input['status']);
        sendJsonResponse(null, 200, 'License status updated.');
    }

    /**
     * API for Client Applications to validate license
     */
    public function validate() {
        $input = getJsonInput();
        $licenseKey = $input['license_key'] ?? $_GET['license_key'] ?? null;
        $instanceKey = $input['instance_key'] ?? $_GET['instance_key'] ?? null;
        $domain = $input['domain'] ?? $_GET['domain'] ?? null;

        if (empty($licenseKey) || empty($instanceKey)) {
            sendJsonResponse(['valid' => false, 'message' => 'license_key and instance_key parameters are required.'], 400);
        }

        $result = $this->licenseModel->validateLicense($licenseKey, $instanceKey, $domain);
        $status = !empty($result['valid']) ? 200 : 403;
        sendJsonResponse($result, $status, $result['message'] ?? 'Validation completed');
    }

    /**
     * API for Client Applications to Auto-Register Customer & License upon installation
     */
    public function register() {
        $input = getJsonInput();
        if (empty($input['app_code']) || empty($input['package_code']) || empty($input['customer_name']) || empty($input['customer_email'])) {
            sendJsonResponse(['success' => false, 'message' => 'app_code, package_code, customer_name, and customer_email are required.'], 400);
        }

        try {
            $result = $this->licenseModel->registerAutoLicense($input);
            sendJsonResponse($result, 201, 'Customer & License auto-registered successfully!');
        } catch (Exception $e) {
            sendJsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Self-Service Order dari Software Showcase (status pending sampai pembayaran di-approve)
     */
    public function order() {
        try {
            $result = $this->orderModel->createOrder(getJsonInput());
            sendJsonResponse($result, 201, 'Order created. Waiting for payment approval.');
        } catch (InvalidArgumentException $e) {
            sendJsonResponse(null, 400, $e->getMessage());
        } catch (OutOfBoundsException $e) {
            sendJsonResponse(null, 404, $e->getMessage());
        } catch (DomainException $e) {
            sendJsonResponse(null, 409, $e->getMessage());
        }
    }

    /**
     * Polling status pesanan oleh Showcase
     */
    public function orderStatus() {
        $subscriptionNo = trim($_GET['subscription_no'] ?? '');
        if ($subscriptionNo === '') {
            sendJsonResponse(null, 400, 'subscription_no parameter is required.');
        }
        $status = $this->orderModel->getOrderStatus($subscriptionNo);
        if (!$status) {
            sendJsonResponse(null, 404, "Order '{$subscriptionNo}' not found.");
        }
        sendJsonResponse($status, 200, 'Order status fetched.');
    }

    /**
     * Persetujuan pembayaran oleh Admin LMD (atau otomatis oleh n8n)
     */
    public function approveOrder() {
        $this->handleOrderDecision(function ($subscriptionNo, $input) {
            return [$this->orderModel->approveOrder($subscriptionNo), 'Order approved. License is now active.'];
        });
    }

    /**
     * Daftar pesanan pending (untuk otomasi seperti n8n; dashboard memakai data subscriptions)
     */
    public function pendingOrders() {
        sendJsonResponse($this->orderModel->listPending(), 200, 'Pending orders fetched.');
    }

    /**
     * Instance (perangkat/server client) yang terhubung ke satu lisensi
     */
    public function instances($licenseId) {
        $license = $this->licenseModel->getById($licenseId);
        if (!$license) {
            sendJsonResponse(null, 404, 'License not found.');
        }
        sendJsonResponse([
            'license_key' => $license['license_key'],
            'customer_name' => $license['customer_name'],
            'application_name' => $license['application_name'],
            'max_users' => (int) $license['max_users'],
            'max_instances' => (int) $license['max_instances'],
            'status' => $license['status'],
            'expires_at' => $license['expires_at'],
            'instances' => $license['instances']
        ], 200, 'Instances fetched successfully.');
    }

    public function updateInstanceStatus($instanceId) {
        $status = getJsonInput()['status'] ?? '';
        if (!in_array($status, ['active', 'blocked'], true)) {
            sendJsonResponse(null, 400, "status must be 'active' or 'blocked'.");
        }
        try {
            $this->licenseModel->setInstanceStatus((int) $instanceId, $status);
            sendJsonResponse(['id' => (int) $instanceId, 'status' => $status], 200, 'Instance status updated.');
        } catch (OutOfBoundsException $e) {
            sendJsonResponse(null, 404, $e->getMessage());
        } catch (DomainException $e) {
            sendJsonResponse(null, 409, $e->getMessage());
        }
    }

    public function rejectOrder() {
        $this->handleOrderDecision(function ($subscriptionNo, $input) {
            return [$this->orderModel->rejectOrder($subscriptionNo, $input['reason'] ?? null), 'Order rejected.'];
        });
    }

    private function handleOrderDecision(callable $decide) {
        $input = getJsonInput();
        $subscriptionNo = trim($input['subscription_no'] ?? '');
        if ($subscriptionNo === '') {
            sendJsonResponse(null, 400, 'subscription_no is required.');
        }
        try {
            [$result, $message] = $decide($subscriptionNo, $input);
            sendJsonResponse($result, 200, $message);
        } catch (OutOfBoundsException $e) {
            sendJsonResponse(null, 404, $e->getMessage());
        } catch (DomainException $e) {
            sendJsonResponse(null, 409, $e->getMessage());
        }
    }
    
}
