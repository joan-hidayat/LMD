<?php
/**
 * Adapter payment gateway. Setiap provider menyediakan:
 *  - checkoutUrl($payment): ke mana customer diarahkan untuk membayar
 *  - parseNotification($rawBody, $headers): verifikasi tanda tangan webhook lalu terjemahkan ke format seragam
 *      ['payment_no' => …, 'status' => 'paid'|'failed'|'expired', 'amount' => float, 'provider_ref' => …]
 * Provider baru (Midtrans, Xendit) cukup menambah satu kelas di sini + entri di payment_provider().
 */

class PaymentSignatureException extends RuntimeException {}

interface PaymentProvider {
    public function checkoutUrl(array $payment);
    public function parseNotification($rawBody, array $headers);
}

/**
 * Gateway tiruan untuk pengembangan: halaman bayar pay/simulator.php mengirim webhook
 * bertanda tangan HMAC-SHA256(webhook_secret, raw body) di header X-Simulator-Signature,
 * persis seperti gateway sungguhan mengirim notifikasi ke server kita.
 */
class SimulatorPaymentProvider implements PaymentProvider {
    const NAME = 'simulator';
    private $secret;

    public function __construct($secret) {
        $this->secret = $secret;
    }

    public function checkoutUrl(array $payment) {
        return lmd_base_url() . '/pay/simulator.php?token=' . rawurlencode($payment['checkout_token']);
    }

    public function sign($rawBody) {
        return hash_hmac('sha256', $rawBody, $this->secret);
    }

    public function parseNotification($rawBody, array $headers) {
        $signature = $headers['x-simulator-signature'] ?? '';
        if (!is_string($signature) || !hash_equals($this->sign($rawBody), $signature)) {
            throw new PaymentSignatureException('Invalid simulator signature.');
        }
        $data = json_decode($rawBody, true);
        if (!is_array($data) || empty($data['payment_no']) || !in_array($data['status'] ?? null, ['paid', 'failed', 'expired'], true)) {
            throw new InvalidArgumentException('Malformed simulator notification.');
        }
        return [
            'payment_no' => (string) $data['payment_no'],
            'status' => $data['status'],
            'amount' => (float) ($data['amount'] ?? 0),
            'provider_ref' => isset($data['transaction_id']) ? (string) $data['transaction_id'] : null,
        ];
    }
}

function payment_config($key = null) {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/payment.php';
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

/**
 * Provider berdasarkan nama; null bila tidak dikenal atau dimatikan di konfigurasi.
 */
function payment_provider($name = null) {
    $name = $name ?? payment_config('provider');
    if ($name === SimulatorPaymentProvider::NAME) {
        $sim = payment_config('simulator');
        return !empty($sim['enabled']) ? new SimulatorPaymentProvider($sim['webhook_secret']) : null;
    }
    return null;
}

/**
 * URL root LMD (mis. http://localhost:8080/License-Management-Dashboard), dari skrip di api/ atau pay/.
 */
function lmd_base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $root = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'))), '/');
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $root;
}
