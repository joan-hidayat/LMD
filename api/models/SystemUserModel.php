<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Akun admin dashboard LMD (ERD level 1-4: System Users). Saat ini dibatasi satu akun (T-04).
 * Akun dibuat lewat command line: php database/admin.php create …
 */
class SystemUserModel {
    const MAX_ATTEMPTS = 5;
    const LOCK_MINUTES = 15;
    const MIN_PASSWORD = 10;

    private $db;

    public function __construct(PDO $db = null) {
        $this->db = $db ?: Database::getInstance()->getConnection();
    }

    public function count() {
        return (int) $this->db->query("SELECT COUNT(*) FROM system_users")->fetchColumn();
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT id, name, email, last_login_at FROM system_users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM system_users WHERE email = :email");
        $stmt->execute(['email' => strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function create($name, $email, $password) {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Format email tidak valid.');
        }
        if ($this->count() > 0) {
            throw new DomainException('Akun admin LMD sudah ada (saat ini dibatasi satu akun). Gunakan perintah reset-password.');
        }
        $this->assertPassword($password);
        $this->db->prepare("INSERT INTO system_users (name, email, password_hash) VALUES (:name, :email, :hash)")
            ->execute(['name' => trim($name) ?: 'Admin LMD', 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
        return $this->findByEmail($email);
    }

    public function resetPassword($email, $password) {
        $user = $this->findByEmail($email);
        if (!$user) {
            throw new InvalidArgumentException("Akun {$email} tidak ditemukan.");
        }
        $this->assertPassword($password);
        $this->db->prepare("UPDATE system_users SET password_hash = :hash, failed_attempts = 0, locked_until = NULL WHERE id = :id")
            ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
    }

    /**
     * Mengembalikan [user|null, pesan error|null]. Akun dikunci sementara setelah percobaan gagal berulang.
     */
    public function attemptLogin($email, $password) {
        $user = $this->findByEmail($email);
        $generic = 'Email atau password salah.';
        if (!$user) {
            // Tetap jalankan hash agar waktu respons tidak membedakan email terdaftar/tidak
            password_verify($password, password_hash('dummy-password', PASSWORD_DEFAULT));
            return [null, $generic];
        }
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            return [null, "Akun dikunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam {$minutes} menit."];
        }
        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int) $user['failed_attempts'] + 1;
            $locked = $attempts >= self::MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null;
            $this->db->prepare("UPDATE system_users SET failed_attempts = :a, locked_until = :l WHERE id = :id")
                ->execute(['a' => $locked ? 0 : $attempts, 'l' => $locked, 'id' => $user['id']]);
            return [null, $generic];
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare("UPDATE system_users SET password_hash = :h WHERE id = :id")
                ->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }
        $this->db->prepare("UPDATE system_users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id")
            ->execute(['id' => $user['id']]);
        return [$this->findById($user['id']), null];
    }

    private function assertPassword($password) {
        if (strlen($password) < self::MIN_PASSWORD) {
            throw new InvalidArgumentException('Password minimal ' . self::MIN_PASSWORD . ' karakter.');
        }
        if (strlen($password) > 72) {
            throw new InvalidArgumentException('Password maksimal 72 karakter.');
        }
    }
}
