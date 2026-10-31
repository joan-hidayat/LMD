<?php
/**
 * Manifest integrasi demo client Apotek — Standar Integrasi v1.2 bagian 4.4 (T-34).
 * Aplikasi on-premise: launch_url null (customer memasukkan license key saat instalasi).
 */
$client = require __DIR__ . '/lmd-client.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$modules = [];
$order = 0;
foreach ($client['modules'] as $code => [$name, $description]) {
    $modules[] = ['code' => $code, 'name' => $name, 'description' => $description, 'module_type' => 'standard', 'sort_order' => ++$order, 'available' => true];
}

echo json_encode([
    'manifest_version' => 1,
    'standard' => 'lmd-integration/1.2',
    'app_code' => $client['app_code'],
    'name' => $client['app_name'],
    'description' => $client['app_description'],
    'type' => 'on_premise',
    'launch_url' => null,
    // client_demo/api.php menandatangani request bila LMD menerbitkan secret (T-29)
    'require_signature' => true,
    'modules' => $modules,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
