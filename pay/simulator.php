<?php
/**
 * Halaman bayar SIMULATOR (pengembangan). Berperan sebagai payment gateway:
 * tombol "Bayar" mengirim webhook bertanda tangan ke POST /api/payments/webhook/simulator,
 * sama seperti gateway sungguhan memberi tahu server kita. Tidak ada uang yang berpindah.
 * Dimatikan lewat api/config/payment.php (simulator.enabled = false).
 */
require_once __DIR__ . '/../api/models/PaymentModel.php';

$provider = payment_provider(SimulatorPaymentProvider::NAME);
if (!$provider) {
    http_response_code(404);
    exit('Simulator pembayaran tidak aktif.');
}

session_name('LMDPAYSIMSESSID');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$model = new PaymentModel();
$token = (string) ($_GET['token'] ?? '');
$payment = $model->findForCheckout($token);
$message = null;

function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function rp($n) { return 'Rp ' . number_format((float) $n, 0, ',', '.'); }

/**
 * Mengirim notifikasi seperti gateway: HTTP POST ke webhook LMD dengan tanda tangan HMAC.
 */
function send_simulator_webhook(SimulatorPaymentProvider $provider, array $payment, $status) {
    $body = json_encode([
        'payment_no' => $payment['payment_no'],
        'status' => $status,
        'amount' => (float) $payment['amount'],
        'transaction_id' => 'SIM-' . strtoupper(bin2hex(random_bytes(5))),
        'method' => $payment['method'],
        'sent_at' => date('c'),
    ]);
    $raw = @file_get_contents(lmd_base_url() . '/api/index.php/payments/webhook/simulator', false, stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nX-Simulator-Signature: " . $provider->sign($body) . "\r\n",
        'content' => $body,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]));
    return $raw === false ? null : json_decode($raw, true);
}

if ($payment && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $message = ['error', 'Sesi halaman kedaluwarsa. Muat ulang lalu coba lagi.'];
    } else {
        $action = $_POST['action'] ?? '';
        if (in_array($action, ['paid', 'failed'], true) && $payment['status'] === 'pending' && $payment['subscription_status'] === 'pending') {
            $res = send_simulator_webhook($provider, $payment, $action);
            if (!empty($res['success'])) {
                header('Location: ?token=' . rawurlencode($token));
                exit;
            }
            $message = ['error', 'Webhook ditolak LMD: ' . ($res['message'] ?? 'server tidak terjangkau')];
        } elseif ($action === 'retry') {
            try {
                $new = $model->retry($token);
                header('Location: ?token=' . rawurlencode($new['checkout_token']));
                exit;
            } catch (DomainException $ex) {
                $message = ['error', $ex->getMessage()];
            }
        }
    }
    $payment = $model->findForCheckout($token);
}

$statusText = [
    'pending' => ['Menunggu pembayaran', 'pending'],
    'paid' => ['Lunas — lisensi diaktifkan otomatis', 'paid'],
    'failed' => ['Pembayaran gagal', 'failed'],
    'expired' => ['Tagihan kedaluwarsa', 'failed'],
    'cancelled' => ['Tagihan dibatalkan (pesanan sudah diputuskan admin)', 'failed'],
];
$methodLabel = ['QRIS' => 'QRIS', 'TRANSFER_BCA' => 'Transfer Bank BCA'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>Simulator Pembayaran — Central SaaS</title>
  <style>
    :root { --bg: #f1f5f9; --card: #fff; --text: #0f172a; --muted: #64748b; --line: #e2e8f0; --ok: #059669; --bad: #dc2626; --warn: #b45309; --primary: #4f46e5; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
    .card { background: var(--card); width: 100%; max-width: 420px; border-radius: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); overflow: hidden; }
    .sim-banner { background: #fef3c7; color: var(--warn); font-size: 12px; font-weight: 700; padding: 8px 16px; text-align: center; }
    .head { padding: 20px 24px 12px; border-bottom: 1px solid var(--line); }
    .head small { color: var(--muted); font-size: 12px; }
    .head h1 { font-size: 26px; margin-top: 4px; }
    .body { padding: 16px 24px 24px; }
    .row { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; padding: 6px 0; }
    .row span:first-child { color: var(--muted); }
    .row span:last-child { font-weight: 600; text-align: right; word-break: break-all; }
    .status { margin: 14px 0; padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 700; }
    .status.pending { background: #eef2ff; color: var(--primary); }
    .status.paid { background: #ecfdf5; color: var(--ok); }
    .status.failed { background: #fef2f2; color: var(--bad); }
    .actions { display: grid; gap: 8px; margin-top: 8px; }
    button { font: 700 14px system-ui, sans-serif; padding: 12px; border-radius: 10px; border: none; cursor: pointer; }
    .btn-pay { background: var(--ok); color: #fff; }
    .btn-fail { background: transparent; color: var(--bad); border: 1px solid #fecaca; }
    .btn-retry { background: var(--primary); color: #fff; }
    .msg { background: #fef2f2; color: var(--bad); font-size: 13px; padding: 10px 12px; border-radius: 10px; margin-bottom: 10px; }
    .note { font-size: 12px; color: var(--muted); margin-top: 14px; line-height: 1.6; }
  </style>
</head>
<body>
  <div class="card">
    <div class="sim-banner">SIMULATOR — tidak ada uang yang berpindah</div>
    <?php if (!$payment): ?>
      <div class="body"><p class="status failed">Tagihan tidak ditemukan. Buka halaman bayar dari Software Showcase.</p></div>
    <?php else: [$label, $cls] = $statusText[$payment['status']]; ?>
      <div class="head">
        <small>Total tagihan • <?php echo e($payment['payment_no']); ?></small>
        <h1><?php echo e(rp($payment['amount'])); ?></h1>
      </div>
      <div class="body">
        <?php if ($message): ?><div class="msg"><?php echo e($message[1]); ?></div><?php endif; ?>
        <div class="row"><span>Produk</span><span><?php echo e($payment['app_name'] . ' — ' . $payment['package_name']); ?></span></div>
        <div class="row"><span>Harga paket</span><span><?php echo e(rp($payment['base_price'])); ?></span></div>
        <div class="row"><span>PPN</span><span><?php echo e(rp($payment['tax_amount'])); ?></span></div>
        <div class="row"><span>Pelanggan</span><span><?php echo e($payment['customer_name'] . ' (' . $payment['customer_email'] . ')'); ?></span></div>
        <div class="row"><span>No. pesanan</span><span><?php echo e($payment['subscription_no']); ?></span></div>
        <div class="row"><span>Metode</span><span><?php echo e($methodLabel[$payment['method']] ?? ($payment['method'] ?: '-')); ?></span></div>
        <div class="row"><span>Berlaku sampai</span><span><?php echo e(date('d M Y H:i', strtotime($payment['expires_at']))); ?></span></div>

        <div class="status <?php echo e($cls); ?>"><?php echo e($label); ?></div>

        <?php if ($payment['status'] === 'pending' && $payment['subscription_status'] === 'pending'): ?>
          <form method="post" class="actions">
            <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf']); ?>">
            <button class="btn-pay" name="action" value="paid">Bayar <?php echo e(rp($payment['amount'])); ?></button>
            <button class="btn-fail" name="action" value="failed">Simulasikan pembayaran gagal</button>
          </form>
        <?php elseif (in_array($payment['status'], ['failed', 'expired'], true) && $payment['subscription_status'] === 'pending'): ?>
          <form method="post" class="actions">
            <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf']); ?>">
            <button class="btn-retry" name="action" value="retry">Buat tagihan baru &amp; coba lagi</button>
          </form>
        <?php elseif ($payment['status'] === 'paid'): ?>
          <p class="note">Kembali ke tab Software Showcase: halaman pesanan akan menampilkan link aplikasi dan license key Anda.</p>
        <?php endif; ?>
        <p class="note">Gateway sungguhan (Midtrans / Xendit) akan menggantikan halaman ini. Alurnya sama: gateway mengirim notifikasi bertanda tangan ke LMD, lalu LMD mengaktifkan lisensi.</p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
