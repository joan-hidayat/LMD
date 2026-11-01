<?php
/**
 * Bukti pembayaran (kuitansi) pesanan Showcase. Dibuka lewat link rahasia (checkout token tagihan),
 * bisa dicetak / disimpan sebagai PDF dari browser. Tidak bergantung pada provider pembayaran.
 */
require_once __DIR__ . '/../api/models/PaymentModel.php';

$payment = (new PaymentModel())->findForCheckout((string) ($_GET['token'] ?? ''));

function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function rp($n) { return 'Rp ' . number_format((float) $n, 0, ',', '.'); }
function tgl($d) { return $d ? date('d M Y H:i', strtotime($d)) : '-'; }

$methodLabel = ['QRIS' => 'QRIS', 'TRANSFER_BCA' => 'Transfer Bank BCA'];
$period = null;
if ($payment && $payment['status'] === 'paid') {
    $stmt = Database::getInstance()->getConnection()->prepare("SELECT start_date, end_date FROM subscriptions WHERE id = :id");
    $stmt->execute(['id' => $payment['subscription_id']]);
    $period = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>Bukti Pembayaran<?php echo $payment ? ' ' . e($payment['payment_no']) : ''; ?> — Central SaaS</title>
  <style>
    :root { --bg: #f1f5f9; --text: #0f172a; --muted: #64748b; --line: #e2e8f0; --ok: #059669; --bad: #dc2626; --primary: #4f46e5; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); padding: 24px 16px; }
    .sheet { background: #fff; max-width: 560px; margin: 0 auto; border-radius: 14px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); padding: 28px; }
    .head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; border-bottom: 2px solid var(--text); padding-bottom: 14px; }
    .head h1 { font-size: 20px; }
    .head small { color: var(--muted); font-size: 12px; display: block; margin-top: 4px; }
    .stamp { border: 2px solid var(--ok); color: var(--ok); font-weight: 800; padding: 6px 10px; border-radius: 8px; transform: rotate(-4deg); font-size: 13px; white-space: nowrap; }
    .stamp.unpaid { border-color: var(--bad); color: var(--bad); }
    h2 { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin: 18px 0 6px; }
    .row { display: flex; justify-content: space-between; gap: 12px; font-size: 14px; padding: 5px 0; }
    .row span:first-child { color: var(--muted); }
    .row span:last-child { text-align: right; font-weight: 600; word-break: break-all; }
    .total { border-top: 1px dashed var(--line); margin-top: 6px; padding-top: 10px; font-size: 18px; }
    .total span:last-child { font-weight: 800; }
    .note { font-size: 12px; color: var(--muted); margin-top: 18px; line-height: 1.6; }
    .actions { display: flex; gap: 8px; justify-content: center; margin: 18px auto 0; max-width: 560px; flex-wrap: wrap; }
    .actions a, .actions button { font: 700 14px system-ui, sans-serif; padding: 10px 16px; border-radius: 10px; border: 1px solid var(--line); background: #fff; color: var(--text); text-decoration: none; cursor: pointer; }
    .actions .primary { background: var(--primary); border-color: var(--primary); color: #fff; }
    @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; } .actions { display: none; } }
  </style>
</head>
<body>
  <?php if (!$payment): ?>
    <div class="sheet"><p>Bukti pembayaran tidak ditemukan. Buka dari menu <strong>Cek Pesanan</strong> di Software Showcase.</p></div>
  <?php else: $paid = $payment['status'] === 'paid'; ?>
    <div class="sheet">
      <div class="head">
        <div>
          <h1><?php echo $paid ? 'Bukti Pembayaran' : 'Tagihan'; ?></h1>
          <small>Central SaaS · <?php echo e($payment['payment_no']); ?></small>
        </div>
        <div class="stamp <?php echo $paid ? '' : 'unpaid'; ?>"><?php echo $paid ? 'LUNAS' : strtoupper(e($payment['status'])); ?></div>
      </div>

      <h2>Pelanggan</h2>
      <div class="row"><span>Nama</span><span><?php echo e($payment['customer_name']); ?></span></div>
      <div class="row"><span>Email</span><span><?php echo e($payment['customer_email']); ?></span></div>

      <h2>Pesanan</h2>
      <div class="row"><span>No. pesanan</span><span><?php echo e($payment['subscription_no']); ?></span></div>
      <div class="row"><span>Produk</span><span><?php echo e($payment['app_name'] . ' — ' . $payment['package_name']); ?></span></div>
      <?php if ($period && $period['end_date']): ?>
        <div class="row"><span>Masa aktif</span><span><?php echo e(date('d M Y', strtotime($period['start_date'])) . ' – ' . date('d M Y', strtotime($period['end_date']))); ?></span></div>
      <?php endif; ?>

      <h2>Pembayaran</h2>
      <div class="row"><span>Metode</span><span><?php echo e($methodLabel[$payment['method']] ?? ($payment['method'] ?: '-')); ?></span></div>
      <div class="row"><span>Dibayar</span><span><?php echo e(tgl($payment['paid_at'])); ?></span></div>
      <div class="row"><span>Ref. transaksi</span><span><?php echo e($payment['provider_ref'] ?: '-'); ?></span></div>
      <div class="row"><span>Harga paket</span><span><?php echo e(rp($payment['base_price'])); ?></span></div>
      <div class="row"><span>PPN</span><span><?php echo e(rp($payment['tax_amount'])); ?></span></div>
      <div class="row total"><span>Total</span><span><?php echo e(rp($payment['amount'])); ?></span></div>

      <p class="note">
        <?php if ($payment['provider'] === SimulatorPaymentProvider::NAME): ?>Dibayar melalui <strong>simulator</strong> (lingkungan pengembangan, tidak ada uang yang berpindah). <?php endif; ?>
        Simpan halaman ini atau cetak / simpan sebagai PDF. Link ini juga bisa dibuka lagi dari menu <strong>Cek Pesanan</strong> di Software Showcase dengan nomor pesanan & email pemesan.
      </p>
    </div>
    <div class="actions">
      <button type="button" class="primary" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
      <?php if ($returnUrl = PaymentModel::returnUrl($payment, $payment['subscription_no'])): ?>
        <a href="<?php echo e($returnUrl); ?>">← Kembali ke Showcase</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</body>
</html>
