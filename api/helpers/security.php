<?php
/**
 * Security & License Utility Helper
 */
class SecurityHelper {
    /**
     * Generate unique License Key format: LIC-{APP_CODE}-{RANDOM_HEX}
     */
    public static function generateLicenseKey($appCode = 'APP') {
        $prefix = 'LIC-' . strtoupper(preg_replace('/[^A-Z0-9]/', '', $appCode)) . '-';
        $randomBytes = bin2hex(random_bytes(12));
        return $prefix . strtoupper($randomBytes);
    }

    /**
     * Generate Activation Key format: ACT-{INSTANCE_PREFIX}-{RANDOM_HEX}
     */
    public static function generateActivationKey($prefix = '') {
        $cleanPrefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($prefix));
        return 'ACT-' . ($cleanPrefix !== '' ? $cleanPrefix . '-' : '') . strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * Generate Instance Key format: INST-{APP_CODE}-{BRANCH}-{RANDOM}
     */
    public static function generateInstanceKey($appCode = 'APP', $name = 'DEFAULT') {
        $cleanApp = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $appCode), 0, 4));
        $cleanName = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $name), 0, 4));
        return "INST-{$cleanApp}-{$cleanName}-" . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Get Client IP Address safely
     */
    /**
     * IP proxy/load balancer yang boleh menentukan IP asli lewat X-Forwarded-For.
     * Kosong = header itu diabaikan (bisa diisi bebas oleh siapa pun, T-05).
     */
    const TRUSTED_PROXIES = [];

    public static function getClientIp() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (in_array($remote, self::TRUSTED_PROXIES, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        return $remote;
    }
}
