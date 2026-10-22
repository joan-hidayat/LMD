<?php
require_once __DIR__ . '/../models/ApplicationModel.php';
require_once __DIR__ . '/../models/ModuleModel.php';
require_once __DIR__ . '/../helpers/response.php';

class AppController {
    private $appModel;
    private $moduleModel;

    public function __construct() {
        $this->appModel = new ApplicationModel();
        $this->moduleModel = new ModuleModel();
    }

    public function index() {
        $apps = $this->appModel->getAll();
        sendJsonResponse($apps, 200, 'Applications fetched successfully.');
    }

    public function store() {
        $input = getJsonInput();
        if (empty($input['code']) || empty($input['name'])) {
            sendJsonResponse(null, 400, 'Application code and name are required.');
        }

        $appId = $this->appModel->create($input);
        $app = $this->appModel->getById($appId);
        sendJsonResponse($app, 201, 'Application created successfully.');
    }

    public function getModules($appId) {
        $modules = $this->moduleModel->getByAppId($appId);
        sendJsonResponse($modules, 200, 'Modules fetched successfully.');
    }

    public function storeModule() {
        $input = getJsonInput();
        if (empty($input['application_id']) || empty($input['code']) || empty($input['name'])) {
            sendJsonResponse(null, 400, 'Application ID, module code, and name are required.');
        }

        $modId = $this->moduleModel->create($input);
        sendJsonResponse(['id' => $modId], 201, 'Module created successfully.');
    }

    public function deleteModule($id) {
        $this->moduleModel->delete($id);
        sendJsonResponse(null, 200, 'Module deleted successfully.');
    }
}
