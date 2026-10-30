<?php
/**
 * JSON Response Helper
 */
function sendJsonResponse($data, $statusCode = 200, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-LMD-Timestamp, X-LMD-Signature');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    http_response_code($statusCode);

    $response = [
        'success' => $statusCode >= 200 && $statusCode < 300,
        'message' => $message,
        'data' => $data
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput() {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        return $_POST;
    }
    $decoded = json_decode($rawInput, true);
    return is_array($decoded) ? $decoded : [];
}
