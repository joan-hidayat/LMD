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

    // Untuk lmd-manifest.php (Standar v1.2 bagian 4.4): admin LMD mengimpor aplikasi & modul dari sini
    'app_name' => 'Apotek Management System',
    'app_description' => 'Sistem apotek on-premise: master obat, inventori, kasir, resep, BPJS.',
    'modules' => [
        // kode => [nama, keterangan]; harus sama dengan modul yang dicek aplikasi (apotek.html: allModules)
        'DASHBOARD' => ['Dashboard', 'Ringkasan penjualan & stok'],
        'MASTER_OBAT' => ['Master Obat', 'Data obat, golongan, harga'],
        'INVENTORY' => ['Inventori', 'Stok gudang & mutasi'],
        'POS_KASIR' => ['Kasir', 'Penjualan obat'],
        'RESEP_DOKTER' => ['Resep Dokter', 'Penebusan resep'],
        'BPJS_INTEGRATION' => ['Integrasi BPJS', 'Klaim BPJS'],
    ],
];
