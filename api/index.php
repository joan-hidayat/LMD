<?php
/**
 * REST API Router - Central SaaS License Management
 */

// Error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/AppController.php';
require_once __DIR__ . '/controllers/PackageController.php';
require_once __DIR__ . '/controllers/CustomerController.php';
require_once __DIR__ . '/controllers/SubscriptionController.php';
require_once __DIR__ . '/controllers/LicenseController.php';
require_once __DIR__ . '/controllers/AuditController.php';

// Parse Request URI
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query strings
$rawPath = parse_url($requestUri, PHP_URL_PATH);

// Clean path: strip everything up to and including /api/ or /api/index.php/
$path = preg_replace('#^.*?/api/(index\.php/)?#i', '', $rawPath);
$path = trim($path, '/');

$segments = explode('/', $path);
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$action = $segments[2] ?? null;

try {
    switch ($resource) {
        case 'dashboard':
        case '':
            $controller = new DashboardController();
            $controller->index();
            break;

        case 'applications':
            $controller = new AppController();
            if ($method === 'GET') {
                if ($id === 'modules' && isset($_GET['app_id'])) {
                    $controller->getModules($_GET['app_id']);
                } elseif ($id && is_numeric($id) && $action === 'modules') {
                    $controller->getModules($id);
                } else {
                    $controller->index();
                }
            } elseif ($method === 'POST') {
                if ($id === 'modules') {
                    $controller->storeModule();
                } else {
                    $controller->store();
                }
            } elseif ($method === 'DELETE' && $id === 'modules' && !empty($_GET['id'])) {
                $controller->deleteModule($_GET['id']);
            }
            break;

        case 'packages':
            $controller = new PackageController();
            if ($method === 'GET') {
                if ($id && is_numeric($id)) {
                    $controller->show($id);
                } else {
                    $controller->index();
                }
            } elseif ($method === 'POST') {
                $controller->store();
            }
            break;

        case 'customers':
            $controller = new CustomerController();
            if ($method === 'GET') {
                $controller->index();
            } elseif ($method === 'POST') {
                $controller->store();
            }
            break;

        case 'subscriptions':
            $controller = new SubscriptionController();
            if ($method === 'GET') {
                $controller->index();
            } elseif ($method === 'POST') {
                $controller->store();
            }
            break;

        case 'licenses':
            $controller = new LicenseController();
            if ($id === 'validate') {
                $controller->validate();
            } elseif ($id === 'register') {
                $controller->register();
            } elseif ($id === 'order') {
                $controller->createOrder();
            } elseif ($id === 'order_status') {
                $controller->getOrderStatus();
            } elseif ($id === 'pending_orders') {
                $controller->getPendingOrders();
            } elseif ($id === 'approve_order') {
                $controller->approveOrder();
            } elseif ($method === 'GET') {
                if ($id && is_numeric($id) && $action === 'instances') {
                    $controller->getInstances($id);
                } elseif ($id && is_numeric($id)) {
                    $controller->show($id);
                } else {
                    $controller->index();
                }
            } elseif ($method === 'PUT' || $method === 'POST') {
                if ($id && is_numeric($id) && $action === 'status') {
                    $controller->updateStatus($id);
                }
            }
            break;


        case 'audit':
        case 'logs':
            $controller = new AuditController();
            $controller->index();
            break;

        default:
            sendJsonResponse(null, 404, "Endpoint '/{$path}' not found.");
            break;
    }
} catch (Throwable $e) {
    sendJsonResponse(null, 500, 'Internal Server Error: ' . $e->getMessage());
}