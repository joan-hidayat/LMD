<?php
$_SERVER['REQUEST_METHOD'] = 'GET';

$endpoints = [
    '/api/dashboard',
    '/api/applications',
    '/api/packages',
    '/api/customers',
    '/api/subscriptions',
    '/api/licenses',
    '/api/audit'
];

foreach ($endpoints as $ep) {
    echo "Testing $ep ... ";
    $_SERVER['REQUEST_URI'] = $ep;
    ob_start();
    try {
        require __DIR__ . '/../api/index.php';
    } catch (Throwable $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
    $out = ob_get_clean();
    $data = json_decode($out, true);
    if (isset($data['success']) && $data['success']) {
        echo "OK\n";
    } else {
        echo "FAILED: " . ($data['message'] ?? $out) . "\n";
    }
}
