<?php
require_once __DIR__ . '/../models/SubscriptionModel.php';
require_once __DIR__ . '/../helpers/response.php';

class SubscriptionController {
    private $subModel;

    public function __construct() {
        $this->subModel = new SubscriptionModel();
    }

    public function index() {
        $subs = $this->subModel->getAll();
        sendJsonResponse($subs, 200, 'Subscriptions fetched successfully.');
    }

    public function store() {
        $input = getJsonInput();
        if (empty($input['customer_id']) || empty($input['package_id'])) {
            sendJsonResponse(null, 400, 'Customer ID and Package ID are required.');
        }

        try {
            $result = $this->subModel->create($input);
            sendJsonResponse($result, 201, 'Subscription created & license generated successfully.');
        } catch (Exception $e) {
            sendJsonResponse(null, 500, $e->getMessage());
        }
    }
}
