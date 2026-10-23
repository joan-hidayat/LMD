<?php
require_once __DIR__ . '/../models/PackageModel.php';
require_once __DIR__ . '/../helpers/response.php';

class PackageController {
    private $packageModel;

    public function __construct() {
        $this->packageModel = new PackageModel();
    }

    public function index() {
        $packages = $this->packageModel->getAll();
        sendJsonResponse($packages, 200, 'Packages fetched successfully.');
    }

    /**
     * Katalog paket publik untuk Software Showcase (sumber tunggal harga & kuota)
     */
    public function publicCatalog($appCode) {
        $packages = $this->packageModel->getPublicByAppCode($appCode);
        sendJsonResponse($packages, 200, 'Public packages fetched successfully.');
    }

    public function show($id) {
        $package = $this->packageModel->getById($id);
        if (!$package) {
            sendJsonResponse(null, 404, 'Package not found.');
        }
        sendJsonResponse($package, 200, 'Package fetched successfully.');
    }

    public function store() {
        $input = getJsonInput();
        if (empty($input['application_id']) || empty($input['code']) || empty($input['name'])) {
            sendJsonResponse(null, 400, 'Application ID, package code, and name are required.');
        }

        try {
            $packageId = $this->packageModel->create($input);
            $package = $this->packageModel->getById($packageId);
            sendJsonResponse($package, 201, 'Package created successfully.');
        } catch (Exception $e) {
            sendJsonResponse(null, 500, $e->getMessage());
        }
    }
}
