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
    
}
