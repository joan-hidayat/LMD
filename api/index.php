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
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/rate_limit.php';

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

/**
 * Endpoint yang boleh dipanggil tanpa login admin (T-04): dipakai Software Showcase dan aplikasi client.
 * Semua endpoint lain (dashboard, CRUD, approve, blokir instalasi, link reset, audit) wajib login.
 */
function is_public_endpoint($resource, $id, $method) {
    if ($resource === 'auth') {
        return true;
    }
    if ($resource === 'licenses') {
        return in_array($id, ['validate', 'register'], true)
            || ($id === 'order' && $method === 'POST')
            || ($id === 'order_status' && $method === 'GET')
            || ($id === 'reset_token' && $method === 'POST');
    }
    // Katalog paket publik untuk Showcase
    return $resource === 'packages' && $method === 'GET' && !empty($_GET['app_code']) && !$id;
}

if ($method === 'OPTIONS') {
    sendJsonResponse(null, 200);
}

try {
    enforce_rate_limit($resource, $id, $method);
    if (!is_public_endpoint($resource, $id, $method)) {
        require_admin($resource . '/' . $id);
    }

    switch ($resource) {
        case 'auth':
            $controller = new AuthController();
            if ($id === 'me' && $method === 'GET') {
                $controller->me();
            } elseif ($id === 'login' && $method === 'POST') {
                $controller->login();
            } elseif ($id === 'logout' && $method === 'POST') {
                $controller->logout();
            } else {
                sendJsonResponse(null, 404, "Endpoint '/{$path}' not found.");
            }
            break;

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
                } elseif (!empty($_GET['app_code'])) {
                    $controller->publicCatalog($_GET['app_code']);
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
            } elseif ($id === 'order' && $method === 'POST') {
                $controller->order();
            } elseif ($id === 'order_status' && $method === 'GET') {
                $controller->orderStatus();
            } elseif ($id === 'approve_order' && $method === 'POST') {
                $controller->approveOrder();
            } elseif ($id === 'reject_order' && $method === 'POST') {
                $controller->rejectOrder();
            } elseif ($id === 'pending_orders' && $method === 'GET') {
                $controller->pendingOrders();
            } elseif ($id === 'reset_token' && $method === 'POST') {
                $controller->checkResetToken();
            } elseif ($id && is_numeric($id) && $action === 'admin_reset' && $method === 'POST') {
                $controller->issueAdminReset($id);
            } elseif ($method === 'GET') {
                if ($id && is_numeric($id) && $action === 'instances') {
                    $controller->instances($id);
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

        case 'instances':
            $controller = new LicenseController();
            if ($method === 'POST' && $id && is_numeric($id) && $action === 'status') {
                $controller->updateInstanceStatus($id);
            } elseif ($method === 'POST' && $id && is_numeric($id) && $action === 'reset_secret') {
                $controller->resetInstanceSecret($id);
            } else {
                sendJsonResponse(null, 404, "Endpoint '/{$path}' not found.");
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