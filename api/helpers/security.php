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
    public static function generateActivationKey($prefix = 'ACT') {
        $cleanPrefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix)) . '-';
        return 'ACT-' . $cleanPrefix . strtoupper(bin2hex(random_bytes(8)));
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
    public static function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
    }
}
