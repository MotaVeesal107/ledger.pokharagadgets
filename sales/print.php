<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT s.*, pa.name AS customer_name, pa.phone AS customer_phone, pa.address AS customer_address FROM sales s LEFT JOIN parties pa ON pa.id = s.party_id WHERE s.id = ?');
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) {
    flash_set('error', 'Sale not found.');
    redirect('/sales/index.php');
}

$stmt = $pdo->prepare('SELECT si.*, p.name AS product_name FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY id');
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

$settings = get_settings($pdo);
$isTaxInvoice = (float)$sale['vat_amount'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($sale['invoice_no']) ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #222; max-width: 700px; margin: 2rem auto; }
  h1 { font-size: 1.3rem; margin-bottom: 0.1rem; }
  .muted { color: #666; }
  table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
  th, td { border-bottom: 1px solid #ddd; padding: 6px 4px; text-align: left; }
  th:last-child, td:last-child, th:nth-child(3), td:nth-child(3), th:nth-child(4), td:nth-child(4) { text-align: right; }
  .totals td { border-bottom: none; }
  .grand { font-weight: bold; font-size: 1.05rem; }
  .header-row { display: flex; justify-content: space-between; align-items: flex-start; }
  .bill-label { font-size: 1.1rem; font-weight: bold; text-align: right; }
  @media print { .no-print { display: none; } body { margin: 0; } }
</style>
</head>
<body>
  <button class="no-print" onclick="window.print()">Print</button>
  <div class="header-row">
    <div>
      <h1><?= e($settings['shop_name']) ?></h1>
      <?php if ($settings['pan_number']): ?><div class="muted">PAN: <?= e($settings['pan_number']) ?></div><?php endif; ?>
      <?php if ($settings['address']): ?><div class="muted"><?= e($settings['address']) ?></div><?php endif; ?>
      <?php if ($settings['phone']): ?><div class="muted"><?= e($settings['phone']) ?></div><?php endif; ?>
    </div>
    <div>
      <div class="bill-label"><?= $isTaxInvoice ? 'Tax Invoice' : 'Bill No.' ?></div>
      <div><?= e($sale['invoice_no']) ?></div>
      <div class="muted"><?= e($sale['sale_date']) ?></div>
    </div>
  </div>

  <div class="muted" style="margin-top:1rem;">
    Customer: <?= e($sale['customer_name'] ?: ($sale['walkin_name'] ?: 'Walk-in')) ?>
    <?php if ($sale['customer_phone']): ?> · <?= e($sale['customer_phone']) ?><?php endif; ?>
  </div>

  <table>
    <thead><tr><th>Item</th><th>Serial</th><th>Qty</th><th>Price</th><th>Disc.</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= e($it['product_name']) ?></td>
        <td><?= e($it['serial_no']) ?></td>
        <td><?= (int)$it['qty'] ?></td>
        <td><?= format_currency($it['sell_price']) ?></td>
        <td><?= $it['discount_percent'] > 0 ? e($it['discount_percent']) . '%' : '—' ?></td>
        <td><?= format_currency($it['line_total']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot class="totals">
      <tr><td colspan="5" style="text-align:right;">Subtotal</td><td><?= format_currency($sale['subtotal']) ?></td></tr>
      <?php if ($isTaxInvoice): ?>
      <tr><td colspan="5" style="text-align:right;">VAT</td><td><?= format_currency($sale['vat_amount']) ?></td></tr>
      <?php endif; ?>
      <tr class="grand"><td colspan="5" style="text-align:right;">Total</td><td><?= format_currency($sale['total']) ?></td></tr>
    </tfoot>
  </table>

  <table>
    <thead><tr><th>Payment method</th><th style="text-align:right;">Amount</th></tr></thead>
    <tbody>
      <?php foreach ($payments as $p): ?>
      <tr><td><?= e(ucfirst($p['method'])) ?></td><td style="text-align:right;"><?= format_currency($p['amount']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p class="muted" style="margin-top:2rem;">Thank you for your business.</p>
</body>
</html>
