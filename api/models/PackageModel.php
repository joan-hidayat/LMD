<?php
require_once __DIR__ . '/../config/database.php';

class PackageModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT p.*, a.name as application_name, a.code as application_code,
                   (SELECT COUNT(*) FROM package_modules pm JOIN modules m ON m.id = pm.module_id
                    WHERE pm.package_id = p.id AND m.deleted_at IS NULL) as modules_count,
                   (SELECT GROUP_CONCAT(m.code ORDER BY m.sort_order, m.id SEPARATOR ',') FROM package_modules pm JOIN modules m ON m.id = pm.module_id
                    WHERE pm.package_id = p.id AND m.deleted_at IS NULL) as module_codes,
                   (SELECT GROUP_CONCAT(pm.module_id) FROM package_modules pm JOIN modules m ON m.id = pm.module_id
                    WHERE pm.package_id = p.id AND m.deleted_at IS NULL) as module_ids,
                   (SELECT COUNT(*) FROM subscriptions s WHERE s.package_id = p.id) as subscriptions_count
            FROM packages p
            JOIN applications a ON p.application_id = a.id
            WHERE p.deleted_at IS NULL
            ORDER BY p.application_id ASC, p.sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPublicByAppCode($appCode) {
        $stmt = $this->db->prepare("
            SELECT p.id, p.code, p.name, p.description, p.base_price, p.billing_cycle, p.max_users, p.max_instances, p.future_module_access,
                   a.code as application_code, a.name as application_name
            FROM packages p
            JOIN applications a ON p.application_id = a.id
            WHERE a.code = :app_code AND a.deleted_at IS NULL AND p.deleted_at IS NULL
              AND a.status = 'active' AND p.status = 'active' AND p.is_public = 1
            ORDER BY p.sort_order ASC, p.base_price ASC
        ");
        $stmt->execute(['app_code' => strtoupper(trim($appCode))]);
        $packages = $stmt->fetchAll();

        $modStmt = $this->db->prepare("
            SELECT m.code, m.name FROM package_modules pm
            JOIN modules m ON pm.module_id = m.id
            WHERE pm.package_id = :package_id AND m.deleted_at IS NULL
            ORDER BY m.sort_order ASC
        ");
        foreach ($packages as &$package) {
            $modStmt->execute(['package_id' => $package['id']]);
            $package['modules'] = $modStmt->fetchAll();
        }
        return $packages;
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM packages WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $package = $stmt->fetch();

        if ($package) {
            $modStmt = $this->db->prepare("
                SELECT m.*, pm.additional_price 
                FROM package_modules pm
                JOIN modules m ON pm.module_id = m.id
                WHERE pm.package_id = :package_id AND m.deleted_at IS NULL
            ");
            $modStmt->execute(['package_id' => $id]);
            $package['modules'] = $modStmt->fetchAll();
        }

        return $package;
    }

    public function create($data) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO packages (application_id, code, name, slug, description, base_price, billing_cycle, max_users, max_instances, future_module_access, status, is_public, sort_order)
                VALUES (:application_id, :code, :name, :slug, :description, :base_price, :billing_cycle, :max_users, :max_instances, :future_module_access, :status, :is_public, :sort_order)
            ");
            $stmt->execute([
                'application_id' => $data['application_id'],
                'code' => strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
                'slug' => strtolower(trim($data['slug'] ?? str_replace(' ', '-', $data['name']))),
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'] ?? 0.00,
                'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
                'max_users' => $data['max_users'] ?? 1,
                'max_instances' => max(1, (int) ($data['max_instances'] ?? 1)),
                'future_module_access' => !empty($data['future_module_access']) ? 1 : 0,
                'status' => $data['status'] ?? 'active',
                'is_public' => isset($data['is_public']) ? ($data['is_public'] ? 1 : 0) : 1,
                'sort_order' => $data['sort_order'] ?? 0
            ]);
            $packageId = $this->db->lastInsertId();

            $this->replaceModules($packageId, $data['application_id'], $data['module_ids'] ?? []);

            $this->db->commit();
            return $packageId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mengubah paket (T-35). Perubahan harga/kuota/modul hanya berlaku untuk pesanan BARU:
     * subscription & lisensi yang sudah terbit menyimpan salinannya sendiri (lapisan Commercial/Provisioning).
     * Kode paket hanya bisa diubah selama belum dipakai subscription; aplikasi tidak bisa dipindah.
     */
    public function update($id, $data) {
        $package = $this->getById($id);
        if (!$package) {
            throw new OutOfBoundsException('Package not found.');
        }
        $code = strtoupper(trim($data['code'] ?? $package['code']));
        if ($code !== $package['code'] && $this->subscriptionCount($id) > 0) {
            throw new DomainException('Kode paket tidak bisa diubah karena sudah dipakai subscription.');
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                UPDATE packages SET code = :code, name = :name, description = :description, base_price = :base_price,
                       billing_cycle = :billing_cycle, max_users = :max_users, max_instances = :max_instances,
                       future_module_access = :future_module_access, is_public = :is_public, status = :status
                WHERE id = :id
            ")->execute([
                'id' => $id,
                'code' => $code,
                'name' => trim($data['name'] ?? $package['name']),
                'description' => $data['description'] ?? $package['description'],
                'base_price' => $data['base_price'] ?? $package['base_price'],
                'billing_cycle' => $data['billing_cycle'] ?? $package['billing_cycle'],
                'max_users' => max(1, (int) ($data['max_users'] ?? $package['max_users'])),
                'max_instances' => max(1, (int) ($data['max_instances'] ?? $package['max_instances'])),
                'future_module_access' => !empty($data['future_module_access']) ? 1 : 0,
                'is_public' => !empty($data['is_public']) ? 1 : 0,
                'status' => in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : $package['status'],
            ]);
            if (array_key_exists('module_ids', $data)) {
                $this->replaceModules($id, $package['application_id'], $data['module_ids']);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->getById($id);
    }

    /**
     * Hapus paket (soft delete): hilang dari Showcase & tidak bisa dipesan lagi.
     * Subscription & lisensi yang sudah ada tetap berjalan.
     */
    public function delete($id) {
        if (!$this->getById($id)) {
            throw new OutOfBoundsException('Package not found.');
        }
        $this->db->prepare("UPDATE packages SET deleted_at = NOW(), status = 'inactive', is_public = 0 WHERE id = :id")
            ->execute(['id' => $id]);
        return $this->subscriptionCount($id);
    }

    private function subscriptionCount($id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM subscriptions WHERE package_id = :id");
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Isi paket = daftar modul. Hanya modul aktif milik aplikasi paket yang diterima.
     */
    private function replaceModules($packageId, $applicationId, $moduleIds) {
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($moduleIds) ? $moduleIds : []))));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $check = $this->db->prepare("SELECT COUNT(*) FROM modules WHERE application_id = ? AND deleted_at IS NULL AND id IN ({$placeholders})");
            $check->execute(array_merge([$applicationId], $ids));
            if ((int) $check->fetchColumn() !== count($ids)) {
                throw new DomainException('Modul yang dipilih bukan milik aplikasi paket ini.');
            }
        }
        $this->db->prepare("DELETE FROM package_modules WHERE package_id = :id")->execute(['id' => $packageId]);
        $insert = $this->db->prepare("INSERT INTO package_modules (package_id, module_id, additional_price) VALUES (:package_id, :module_id, 0.00)");
        foreach ($ids as $moduleId) {
            $insert->execute(['package_id' => $packageId, 'module_id' => $moduleId]);
        }
    }
}
