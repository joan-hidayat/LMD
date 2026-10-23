<?php
require_once __DIR__ . '/../models/LicenseModel.php';
require_once __DIR__ . '/../helpers/response.php';

class LicenseController {
    private $licenseModel;

    public function __construct() {
        $this->licenseModel = new LicenseModel();
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
    

    public function createOrder() {
        $input = getJsonInput();
        if (empty($input['customer_email']) || empty($input['customer_name'])) {
            sendJsonResponse(null, 400, 'customer_name and customer_email are required.');
        }

        try {
            $order = $this->licenseModel->createPendingOrder($input);
            sendJsonResponse($order, 201, 'Order created successfully, awaiting payment verification.');
        } catch (Exception $e) {
            sendJsonResponse(null, 500, $e->getMessage());
        }
    }

    public function getOrderStatus() {
        $subNo = $_GET['subscription_no'] ?? null;
        if (empty($subNo)) {
            sendJsonResponse(null, 400, 'subscription_no query parameter is required.');
        }

        $res = $this->licenseModel->getOrderStatus($subNo);
        if (!$res) {
            sendJsonResponse(null, 404, 'Subscription order not found.');
        }
        sendJsonResponse($res, 200, 'Order status retrieved.');
    }

    public function getPendingOrders() {
        $orders = $this->licenseModel->getPendingOrders();
        sendJsonResponse($orders, 200, 'Pending orders fetched.');
    }

    public function approveOrder() {
        $input = getJsonInput();
        $subNo = $input['subscription_no'] ?? null;
        if (empty($subNo)) {
            sendJsonResponse(null, 400, 'subscription_no is required.');
        }

        try {
            $result = $this->licenseModel->approveOrder($subNo);
            sendJsonResponse($result, 200, 'Order approved, license and instance successfully provisioned!');
        } catch (Exception $e) {
            sendJsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Get Application Instances for a specific license
     */
    public function getInstances($licenseId) {
        $license = $this->licenseModel->getById($licenseId);
        if (!$license) {
            sendJsonResponse(null, 404, 'License not found.');
        }
        sendJsonResponse([
            'license_key'  => $license['license_key'],
            'customer_name'=> $license['customer_name'],
            'max_users'    => $license['max_users'],
            'status'       => $license['status'],
            'instances'    => $license['instances'] ?? []
        ], 200, 'Instances fetched successfully.');
    }
}
