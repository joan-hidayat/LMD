<?php
/**
 * SIMULASI INSTALASI & AUTO-REGISTER CLIENT DMS (Distributor Management System)
 */

$apiUrl = 'http://localhost:8080/License-Management-Dashboard/api/index.php/licenses/register';

$payload = [
    'app_code' => 'DISTRIBUTOR',
    'package_code' => 'DIST_PRO',
    'customer_name' => 'PT Distribution Logistics Jaya',
    'customer_email' => 'admin@distribusijaya.co.id',
    'customer_phone' => '081299001122',
    'instance_key' => 'INST-DIST-LOG-001',
    'domain' => 'app.distribusijaya.co.id'
];

echo "=======================================================\n";
echo "🚀 SIMULASI INSTALASI APLIKASI DMS CLIENT\n";
echo "=======================================================\n";
echo "Mengirim payload auto-register dari Installer Client DMS...\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response JSON dari Central SaaS:\n";
$responseData = json_decode($response, true);
echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n\n";

if ($httpCode === 201 && isset($responseData['data']['license_key'])) {
    $licenseKey = $responseData['data']['license_key'];
    echo "✅ Auto-Register Sukses!\n";
    echo "🔑 License Key Diterbitkan: $licenseKey\n";
    echo "=======================================================\n";
    echo "⚡ SIMULASI DILANJUTKAN: DMS Client Melakukan Validasi Lisensi Pertamanya...\n";

    $valUrl = 'http://localhost:8080/License-Management-Dashboard/api/index.php/licenses/validate';
    $valPayload = [
        'license_key' => $licenseKey,
        'instance_key' => 'INST-DIST-LOG-001',
        'domain' => 'app.distribusijaya.co.id'
    ];

    $ch2 = curl_init($valUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($valPayload));
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $valRes = curl_exec($ch2);
    $valCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    echo "HTTP Status Validasi: $valCode\n";
    echo "Response Validasi:\n";
    echo json_encode(json_decode($valRes, true), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ Registration Failed: $response\n";
}
