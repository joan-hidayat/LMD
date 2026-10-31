<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ApplicationModel.php';
require_once __DIR__ . '/ModuleModel.php';

/**
 * Impor aplikasi & modul dari manifest client (Standar Integrasi v1.2 bagian 4.4, T-34).
 * Kode modul diambil dari aplikasi client sendiri, jadi admin LMD tidak perlu menghafal/mengetiknya.
 *
 * Aturan aman:
 * - Aplikasi baru dibuat lengkap dari manifest. Aplikasi yang sudah ada TIDAK diubah (perbedaan hanya ditampilkan;
 *   ubah lewat tombol Ubah bila memang perlu).
 * - Modul hanya DITAMBAH (atau dipulihkan bila pernah dihapus). Modul yang ada di LMD tetapi tidak dikenal client
 *   ditandai, tidak dihapus otomatis.
 * - Impor selalu mengambil ulang manifest di server; pratinjau dari browser tidak dipercaya.
 */
class ManifestImporter {
    const MAX_BYTES = 200000;
    const CODE_PATTERN = '/^[A-Z][A-Z0-9_]{1,49}$/';

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function preview($url) {
        [$manifest, $source] = $this->fetch($url);
        $app = $this->findApp($manifest['app_code']);
        $existing = $app ? $this->modulesByCode($app['id']) : [];

        $modules = [];
        foreach ($manifest['modules'] as $module) {
            $current = $existing[$module['code']] ?? null;
            $status = !$current ? 'new' : ($current['deleted_at'] ? 'deleted' : 'exists');
            $modules[] = $module + [
                'status' => $status,
                'lmd_name' => $current['name'] ?? null,
            ];
        }
        $manifestCodes = array_column($manifest['modules'], 'code');
        $lmdOnly = [];
        foreach ($existing as $code => $row) {
            if (!$row['deleted_at'] && !in_array($code, $manifestCodes, true)) {
                $lmdOnly[] = ['code' => $code, 'name' => $row['name']];
            }
        }

        $differences = [];
        if ($app) {
            $fields = [
                'name' => [$app['name'], $manifest['name']],
                'launch_url' => [$app['launch_url'], $manifest['launch_url']],
                'require_signature' => [(bool) $app['require_signature'], $manifest['require_signature']],
            ];
            foreach ($fields as $field => [$lmd, $client]) {
                if ($lmd !== $client) {
                    $differences[] = ['field' => $field, 'lmd' => $lmd, 'manifest' => $client];
                }
            }
        }

        return [
            'source' => $source,
            'manifest' => $manifest,
            'application' => $app ? ['status' => 'exists', 'id' => (int) $app['id'], 'differences' => $differences] : ['status' => 'new'],
            'modules' => $modules,
            'lmd_only_modules' => $lmdOnly,
            'changes' => count(array_filter($modules, function ($m) { return $m['status'] !== 'exists'; })) + ($app ? 0 : 1),
        ];
    }

    public function import($url) {
        $plan = $this->preview($url);
        $manifest = $plan['manifest'];
        $appModel = new ApplicationModel();
        $moduleModel = new ModuleModel();

        $appId = $plan['application']['id'] ?? null;
        if (!$appId) {
            $appId = (int) $appModel->create([
                'code' => $manifest['app_code'],
                'name' => $manifest['name'],
                'description' => $manifest['description'],
                'launch_url' => $manifest['launch_url'],
                'require_signature' => $manifest['require_signature'],
            ]);
        }

        $created = $restored = [];
        foreach ($plan['modules'] as $module) {
            if ($module['status'] === 'new') {
                $moduleModel->create([
                    'application_id' => $appId,
                    'code' => $module['code'],
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'module_type' => $module['module_type'],
                    'sort_order' => $module['sort_order'],
                ]);
                $created[] = $module['code'];
            } elseif ($module['status'] === 'deleted') {
                $this->restoreModule($appId, $module['code']);
                $restored[] = $module['code'];
            }
        }

        return [
            'application_id' => $appId,
            'application_created' => $plan['application']['status'] === 'new',
            'modules_created' => $created,
            'modules_restored' => $restored,
            'lmd_only_modules' => $plan['lmd_only_modules'],
        ];
    }

    /**
     * Mengambil & memvalidasi manifest. URL boleh berupa folder aplikasi (otomatis + lmd-manifest.php)
     * atau path berawalan "/" (host yang sama dengan LMD).
     */
    private function fetch($url) {
        $url = trim((string) $url);
        if ($url === '') {
            throw new InvalidArgumentException('Alamat aplikasi wajib diisi, misalnya /mini-pos/.');
        }
        if ($url[0] === '/') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $url;
        }
        $parts = parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException('Alamat harus berupa URL http(s) atau path berawalan "/".');
        }
        if (!preg_match('/\.(php|json)$/i', $parts['path'] ?? '')) {
            $url = preg_replace('/[?#].*$/', '', $url);
            $url = rtrim($url, '/') . '/lmd-manifest.php';
        }

        $raw = @file_get_contents($url, false, stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n",
            'timeout' => 5,
            'follow_location' => 0,
            'ignore_errors' => true,
        ]]), 0, self::MAX_BYTES + 1);
        $statusLine = $http_response_header[0] ?? '';
        if ($raw === false) {
            throw new DomainException("Manifest tidak bisa diambil dari {$url}.");
        }
        if (!preg_match('#^HTTP/\S+\s+200\b#', $statusLine)) {
            throw new DomainException("Manifest tidak ditemukan di {$url} ({$statusLine}). Pastikan aplikasi punya lmd-manifest.php.");
        }
        if (strlen($raw) > self::MAX_BYTES) {
            throw new DomainException('Manifest terlalu besar.');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new DomainException('Manifest bukan JSON yang valid.');
        }
        return [$this->validate($data), $url];
    }

    private function validate(array $data) {
        $errors = [];
        $code = strtoupper(trim((string) ($data['app_code'] ?? '')));
        if (!preg_match(self::CODE_PATTERN, $code)) {
            $errors[] = 'app_code wajib: huruf besar/angka/garis bawah, diawali huruf (mis. MINIPOS).';
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            $errors[] = 'name wajib (maks 150 karakter).';
        }
        $launch = isset($data['launch_url']) && $data['launch_url'] !== '' ? trim((string) $data['launch_url']) : null;
        if ($launch !== null && (mb_strlen($launch) > 255 || !preg_match('#^(/|https?://)#i', $launch))) {
            $errors[] = 'launch_url harus berawalan "/" atau http(s):// (maks 255), atau null untuk aplikasi on-premise.';
        }
        $modules = [];
        if (empty($data['modules']) || !is_array($data['modules']) || count($data['modules']) > 100) {
            $errors[] = 'modules wajib berisi 1–100 modul.';
        } else {
            foreach (array_values($data['modules']) as $i => $module) {
                $mCode = strtoupper(trim((string) ($module['code'] ?? '')));
                $mName = trim((string) ($module['name'] ?? ''));
                $type = $module['module_type'] ?? 'standard';
                if (!preg_match(self::CODE_PATTERN, $mCode) || $mName === '' || mb_strlen($mName) > 100 || !in_array($type, ['standard', 'advanced'], true)) {
                    $errors[] = 'Modul ke-' . ($i + 1) . ': code (huruf besar/angka/_), name (maks 100) dan module_type standard|advanced.';
                    continue;
                }
                if (isset($modules[$mCode])) {
                    $errors[] = "Kode modul {$mCode} muncul dua kali.";
                    continue;
                }
                $modules[$mCode] = [
                    'code' => $mCode,
                    'name' => $mName,
                    'description' => isset($module['description']) ? mb_substr(trim((string) $module['description']), 0, 1000) : null,
                    'module_type' => $type,
                    'sort_order' => (int) ($module['sort_order'] ?? ($i + 1)),
                    'available' => !isset($module['available']) || (bool) $module['available'],
                ];
            }
        }
        if ($errors) {
            throw new DomainException('Manifest tidak valid: ' . implode(' ', $errors));
        }
        return [
            'manifest_version' => (int) ($data['manifest_version'] ?? 1),
            'standard' => (string) ($data['standard'] ?? ''),
            'app_code' => $code,
            'name' => $name,
            'description' => isset($data['description']) ? mb_substr(trim((string) $data['description']), 0, 2000) : null,
            'type' => $launch ? 'hosted' : 'on_premise',
            'launch_url' => $launch,
            'require_signature' => !array_key_exists('require_signature', $data) || (bool) $data['require_signature'],
            'modules' => array_values($modules),
        ];
    }

    private function findApp($code) {
        $stmt = $this->db->prepare("SELECT * FROM applications WHERE code = :code");
        $stmt->execute(['code' => $code]);
        $app = $stmt->fetch();
        if ($app && $app['deleted_at']) {
            throw new DomainException("Kode {$code} dipakai aplikasi yang sudah dihapus. Pulihkan atau ganti kode di client.");
        }
        return $app ?: null;
    }

    private function modulesByCode($appId) {
        $stmt = $this->db->prepare("SELECT code, name, deleted_at FROM modules WHERE application_id = :id");
        $stmt->execute(['id' => $appId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[$row['code']] = $row;
        }
        return $rows;
    }

    private function restoreModule($appId, $code) {
        $stmt = $this->db->prepare("UPDATE modules SET deleted_at = NULL, status = 'active' WHERE application_id = :app AND code = :code");
        $stmt->execute(['app' => $appId, 'code' => $code]);
        $id = $this->db->prepare("SELECT id FROM modules WHERE application_id = :app AND code = :code");
        $id->execute(['app' => $appId, 'code' => $code]);
        ModuleModel::syncFutureModules($this->db, (int) $id->fetchColumn());
    }
}
