<?php
$_SERVER['REQUEST_URI'] = '/api/licenses/validate';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'license_key' => 'LIC-APO-8A3F9C2B1D7E401A99FE88AA',
    'instance_key' => 'INST-APO-JKT-001',
    'domain' => 'apotek-jkt.sehatsentosa.co.id'
];
require __DIR__ . '/../api/index.php';
