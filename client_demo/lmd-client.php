<?php
/**
 * Identitas demo client Apotek untuk Central SaaS LMD (Standar Integrasi v1).
 * app_code harus sama dengan registry LMD (Applications & Modules).
 */
return [
    'app_code' => 'APOTEK',
    // null = otomatis: API LMD di host yang sama ({scheme}://{host}/License-Management-Dashboard/api/index.php)
    'lmd_api_url' => null,
    'instance_key_prefix' => 'INST-APO-',
];
