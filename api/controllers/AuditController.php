<?php
require_once __DIR__ . '/../models/LicenseLogModel.php';
require_once __DIR__ . '/../helpers/response.php';

class AuditController {
    private $logModel;

    public function __construct() {
        $this->logModel = new LicenseLogModel();
    }

    public function index() {
        $limit = $_GET['limit'] ?? 50;
        $logs = $this->logModel->getAll($limit);
        sendJsonResponse($logs, 200, 'Audit logs fetched successfully.');
    }
}
