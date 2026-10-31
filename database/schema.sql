-- Central SaaS License Management Database Schema
-- Based on docs/ERD/9. Physical ERD — Final.txt

CREATE DATABASE IF NOT EXISTS `central_saas` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `central_saas`;

-- 1. applications
CREATE TABLE IF NOT EXISTS `applications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `require_signature` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Wajib request bertanda tangan secret per instalasi (T-22)',
    `launch_url` VARCHAR(255) NULL COMMENT 'Template URL aplikasi hosted, mis. /mini-pos/?tenant={tenant}&lic={license_key}; NULL = aplikasi on-premise',
    `status` ENUM('draft', 'active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. modules
CREATE TABLE IF NOT EXISTS `modules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `module_type` ENUM('standard', 'advanced') NOT NULL DEFAULT 'standard',
    `description` TEXT NULL,
    `status` ENUM('draft', 'active', 'inactive') NOT NULL DEFAULT 'active',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    CONSTRAINT `fk_modules_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `unique_app_module_code` UNIQUE (`application_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. packages
CREATE TABLE IF NOT EXISTS `packages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `base_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `billing_cycle` ENUM('monthly', 'quarterly', 'semi_annual', 'annual', 'one_time') NOT NULL DEFAULT 'monthly',
    `max_users` INT UNSIGNED NOT NULL DEFAULT 1,
    `max_instances` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Kuota instalasi/lokasi aplikasi client per lisensi',
    `future_module_access` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active', 'inactive') NOT NULL DEFAULT 'active',
    `is_public` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    CONSTRAINT `fk_packages_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. package_modules
CREATE TABLE IF NOT EXISTS `package_modules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `package_id` BIGINT UNSIGNED NOT NULL,
    `module_id` BIGINT UNSIGNED NOT NULL,
    `additional_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pkg_modules_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pkg_modules_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `unique_package_module` UNIQUE (`package_id`, `module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. customers
CREATE TABLE IF NOT EXISTS `customers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(50) NULL,
    `status` ENUM('active', 'inactive', 'blocked') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. subscriptions
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscription_no` VARCHAR(100) NOT NULL UNIQUE,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `package_id` BIGINT UNSIGNED NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `billing_cycle` ENUM('monthly', 'quarterly', 'semi_annual', 'annual', 'one_time') NOT NULL DEFAULT 'monthly',
    `max_users` INT UNSIGNED NOT NULL DEFAULT 1,
    `max_instances` INT UNSIGNED NOT NULL DEFAULT 1,
    `base_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `addon_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'active', 'suspended', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sub_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sub_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sub_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. subscription_modules
CREATE TABLE IF NOT EXISTS `subscription_modules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscription_id` BIGINT UNSIGNED NOT NULL,
    `module_id` BIGINT UNSIGNED NOT NULL,
    `source` ENUM('package', 'future_module', 'addon', 'custom') NOT NULL DEFAULT 'package',
    `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sub_mod_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sub_mod_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `unique_subscription_module` UNIQUE (`subscription_id`, `module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. licenses
CREATE TABLE IF NOT EXISTS `licenses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `license_key` VARCHAR(150) NOT NULL UNIQUE,
    `subscription_id` BIGINT UNSIGNED NOT NULL,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NULL,
    `status` ENUM('active', 'suspended', 'expired', 'revoked') NOT NULL DEFAULT 'active',
    `last_checked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lic_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lic_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. license_modules
CREATE TABLE IF NOT EXISTS `license_modules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `license_id` BIGINT UNSIGNED NOT NULL,
    `module_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lic_mod_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lic_mod_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `unique_license_module` UNIQUE (`license_id`, `module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. application_instances
CREATE TABLE IF NOT EXISTS `application_instances` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `license_id` BIGINT UNSIGNED NOT NULL,
    `instance_key` VARCHAR(150) NOT NULL UNIQUE,
    `instance_name` VARCHAR(150) NOT NULL,
    `domain` VARCHAR(255) NULL,
    `environment` ENUM('production', 'staging', 'development') NOT NULL DEFAULT 'production',
    `last_seen_at` DATETIME NULL,
    `secret` CHAR(64) NULL COMMENT 'Secret HMAC per instalasi (T-22); hanya dikirim sekali ke client',
    `status` ENUM('active', 'inactive', 'blocked') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_app_inst_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. license_activations
CREATE TABLE IF NOT EXISTS `license_activations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `license_id` BIGINT UNSIGNED NOT NULL,
    `instance_id` BIGINT UNSIGNED NOT NULL,
    `activation_key` VARCHAR(150) NOT NULL UNIQUE,
    `activated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at` DATETIME NULL,
    `ip_address` VARCHAR(45) NULL,
    `status` ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lic_act_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lic_act_instance` FOREIGN KEY (`instance_id`) REFERENCES `application_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. license_logs
CREATE TABLE IF NOT EXISTS `license_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `license_id` BIGINT UNSIGNED NULL,
    `instance_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL,
    `request_id` VARCHAR(100) NULL,
    `ip_address` VARCHAR(45) NULL,
    `request_data` JSON NULL,
    `response_data` JSON NULL,
    `result` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lic_log_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_lic_log_instance` FOREIGN KEY (`instance_id`) REFERENCES `application_instances` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. admin_reset_tokens (T-21): link sekali pakai untuk reset password admin aplikasi client
CREATE TABLE IF NOT EXISTS `admin_reset_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `license_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reset_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. system_users (T-04): akun admin dashboard LMD (ERD level 1-4). Dibuat lewat: php database/admin.php create
CREATE TABLE IF NOT EXISTS `system_users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. rate_limits (T-05): penghitung request per IP per endpoint, jendela 60 detik
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `bucket` VARCHAR(191) NOT NULL PRIMARY KEY,
    `window_start` INT UNSIGNED NOT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 0,
    KEY `idx_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. api_tokens (T-04): token otomasi (mis. n8n) dengan scope terbatas. Dibuat via: php database/admin.php token-create
CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `scope` VARCHAR(50) NOT NULL,
    `token_hash` CHAR(64) NOT NULL UNIQUE,
    `last_used_at` DATETIME NULL,
    `revoked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. payments: tagihan per pesanan Showcase. Dibayar lewat payment gateway (provider); webhook bertanda tangan
--     yang sah menyetujui pesanan otomatis. Provider 'simulator' untuk pengembangan (api/config/payment.php).
CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payment_no` VARCHAR(40) NOT NULL UNIQUE,
    `subscription_id` BIGINT UNSIGNED NOT NULL,
    `provider` VARCHAR(30) NOT NULL,
    `method` VARCHAR(30) NULL COMMENT 'Pilihan customer, mis. QRIS / TRANSFER_BCA',
    `amount` DECIMAL(15,2) NOT NULL,
    `status` ENUM('pending', 'paid', 'failed', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
    `checkout_token` CHAR(64) NOT NULL UNIQUE COMMENT 'Akses halaman bayar (tidak bisa ditebak)',
    `provider_ref` VARCHAR(100) NULL COMMENT 'ID transaksi dari provider',
    `paid_at` DATETIME NULL,
    `expires_at` DATETIME NOT NULL,
    `last_notification` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
    KEY `idx_payments_subscription` (`subscription_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
