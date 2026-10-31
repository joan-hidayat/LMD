<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/payment_providers.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/LicenseModel.php';
require_once __DIR__ . '/OrderModel.php';

/**
 * Tagihan pesanan Showcase dan notifikasi payment gateway.
 *
 * create        : dipanggil OrderModel saat pesanan dibuat (dalam transaksi yang sama)
 * notification  : webhook provider → tanda tangan diverifikasi → status tagihan → paid = pesanan disetujui otomatis
 * retry         : tagihan gagal/kedaluwarsa diganti tagihan baru selama pesanan masih pending
 * Persetujuan manual admin tetap berlaku; tagihan pending lain lalu dibatalkan (cancelPending).
 */
class PaymentModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Membuat tagihan baru untuk subscription. Tidak membuka transaksi sendiri (dipakai di dalam createOrder).
     */
    public function create($subscriptionId, $amount, $method = null) {
        $provider = payment_provider();
        if (!$provider) {
            throw new DomainException('Payment provider is not configured.');
        }
        $paymentNo = 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $this->db->prepare("
            INSERT INTO payments (payment_no, subscription_id, provider, method, amount, status, checkout_token, expires_at)
            VALUES (:payment_no, :subscription_id, :provider, :method, :amount, 'pending', :token, DATE_ADD(NOW(), INTERVAL :hours HOUR))
        ")->execute([
            'payment_no' => $paymentNo,
            'subscription_id' => $subscriptionId,
            'provider' => payment_config('provider'),
            'method' => $method ? substr(strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', $method)), 0, 30) : null,
            'amount' => $amount,
            'token' => bin2hex(random_bytes(32)),
            'hours' => (int) payment_config('expires_hours'),
        ]);
        return $this->findBy('payment_no', $paymentNo);
    }

    /**
     * Tagihan untuk halaman bayar (berdasarkan checkout token), lengkap dengan data pesanan.
     */
    public function findForCheckout($token) {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $this->expireDue();
        $stmt = $this->db->prepare("
            SELECT pay.*, s.subscription_no, s.status AS subscription_status, s.base_price, s.tax_amount,
                   c.name AS customer_name, c.email AS customer_email, a.name AS app_name, p.name AS package_name
            FROM payments pay
            JOIN subscriptions s ON s.id = pay.subscription_id
            JOIN customers c ON c.id = s.customer_id
            JOIN applications a ON a.id = s.application_id
            JOIN packages p ON p.id = s.package_id
            WHERE pay.checkout_token = :token
        ");
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Ringkasan tagihan terbaru untuk order_status (Showcase).
     */
    public function latestSummary($subscriptionId) {
        $this->expireDue();
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE subscription_id = :id ORDER BY id DESC LIMIT 1");
        $stmt->execute(['id' => $subscriptionId]);
        $payment = $stmt->fetch();
        if (!$payment) {
            return null;
        }
        $provider = payment_provider($payment['provider']);
        return [
            'payment_no' => $payment['payment_no'],
            'status' => $payment['status'],
            'method' => $payment['method'],
            'amount' => (float) $payment['amount'],
            'expires_at' => $payment['expires_at'],
            'payment_url' => $payment['status'] === 'pending' && $provider ? $provider->checkoutUrl($payment) : null,
        ];
    }

    /**
     * Memproses webhook provider. Idempoten: notifikasi yang sama boleh datang berulang.
     */
    public function handleNotification($providerName, $rawBody, array $headers) {
        $provider = payment_provider($providerName);
        if (!$provider) {
            throw new OutOfBoundsException("Payment provider '{$providerName}' is not enabled.");
        }
        $note = $provider->parseNotification($rawBody, $headers);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE payment_no = :no AND provider = :provider FOR UPDATE");
            $stmt->execute(['no' => $note['payment_no'], 'provider' => $providerName]);
            $payment = $stmt->fetch();
            if (!$payment) {
                throw new OutOfBoundsException("Payment '{$note['payment_no']}' not found.");
            }
            if ($note['status'] === 'paid' && round((float) $payment['amount'], 2) !== round($note['amount'], 2)) {
                throw new DomainException('Paid amount does not match the invoice.');
            }
            $alreadyFinal = $payment['status'] !== 'pending';
            if (!$alreadyFinal) {
                $this->db->prepare("
                    UPDATE payments SET status = :status, provider_ref = :ref, last_notification = :raw,
                           paid_at = IF(:paid = 1, NOW(), NULL)
                    WHERE id = :id
                ")->execute([
                    'status' => $note['status'],
                    'ref' => $note['provider_ref'],
                    'raw' => $rawBody,
                    'paid' => $note['status'] === 'paid' ? 1 : 0,
                    'id' => $payment['id'],
                ]);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $subscriptionNo = $this->subscriptionNo($payment['subscription_id']);
        $result = ['payment_no' => $payment['payment_no'], 'status' => $alreadyFinal ? $payment['status'] : $note['status'], 'duplicate' => $alreadyFinal, 'order_approved' => false];
        if ($alreadyFinal) {
            return $result;
        }

        $this->log($payment['subscription_id'], 'payment_' . $note['status'], $note, $result);
        if ($note['status'] === 'paid') {
            try {
                (new OrderModel())->approveOrder($subscriptionNo);
                $result['order_approved'] = true;
            } catch (DomainException $e) {
                // Pesanan sudah diputuskan admin sebelum uang masuk: catat agar ditindaklanjuti (refund / aktivasi manual)
                $this->log($payment['subscription_id'], 'payment_after_decision', $note, ['message' => $e->getMessage()], 'failed');
            }
        }
        return $result;
    }

    /**
     * Tagihan baru menggantikan tagihan gagal/kedaluwarsa, selama pesanan masih pending.
     */
    public function retry($token) {
        $payment = $this->findForCheckout($token);
        if (!$payment || !in_array($payment['status'], ['failed', 'expired'], true) || $payment['subscription_status'] !== 'pending') {
            throw new DomainException('Tagihan ini tidak bisa diulang.');
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM payments WHERE subscription_id = :id AND status = 'pending'");
        $stmt->execute(['id' => $payment['subscription_id']]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new DomainException('Masih ada tagihan aktif untuk pesanan ini.');
        }
        return $this->create($payment['subscription_id'], $payment['amount'], $payment['method']);
    }

    /**
     * Dipanggil saat admin menyetujui/menolak manual: tagihan yang belum dibayar tidak bisa dipakai lagi.
     */
    public function cancelPending($subscriptionId) {
        $this->db->prepare("UPDATE payments SET status = 'cancelled' WHERE subscription_id = :id AND status = 'pending'")
            ->execute(['id' => $subscriptionId]);
    }

    private function expireDue() {
        $this->db->exec("UPDATE payments SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()");
    }

    private function findBy($column, $value) {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE {$column} = :value");
        $stmt->execute(['value' => $value]);
        return $stmt->fetch() ?: null;
    }

    private function subscriptionNo($subscriptionId) {
        $stmt = $this->db->prepare("SELECT subscription_no FROM subscriptions WHERE id = :id");
        $stmt->execute(['id' => $subscriptionId]);
        return $stmt->fetchColumn();
    }

    private function log($subscriptionId, $action, $request, $response, $result = 'success') {
        $stmt = $this->db->prepare("
            SELECT l.id AS license_id, (SELECT ai.id FROM application_instances ai WHERE ai.license_id = l.id ORDER BY ai.id LIMIT 1) AS instance_id
            FROM licenses l WHERE l.subscription_id = :id ORDER BY l.id LIMIT 1
        ");
        $stmt->execute(['id' => $subscriptionId]);
        $ids = $stmt->fetch() ?: ['license_id' => null, 'instance_id' => null];
        (new LicenseModel())->logAction(
            $ids['license_id'], $ids['instance_id'], $action, 'REQ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
            SecurityHelper::getClientIp(), $request, $response, $result
        );
    }
}
