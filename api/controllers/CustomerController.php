<?php
require_once __DIR__ . '/../models/CustomerModel.php';
require_once __DIR__ . '/../helpers/response.php';

class CustomerController {
    private $customerModel;

    public function __construct() {
        $this->customerModel = new CustomerModel();
    }

    public function index() {
        $customers = $this->customerModel->getAll();
        sendJsonResponse($customers, 200, 'Customers fetched successfully.');
    }

    public function store() {
        $input = getJsonInput();
        if (empty($input['name']) || empty($input['email'])) {
            sendJsonResponse(null, 400, 'Customer name and email are required.');
        }

        $id = $this->customerModel->create($input);
        sendJsonResponse(['id' => $id], 201, 'Customer created successfully.');
    }
}
