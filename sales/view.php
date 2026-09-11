<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT s.*, pa.name AS customer_name FROM sales s LEFT JOIN parties pa ON pa.id = s.party_id WHERE s.id = ?');
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) {
    flash_set('error', 'Sale not found.');
    redirect('/sales/index.php');
}

$stmt = $pdo->prepare('SELECT si.*, p.name AS product_name, p.sku FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY id');
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

$pageTitle = $sale['invoice_no'];
$active = 'sales';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4"><?= e($sale['invoice_no']) ?></div>
    <div class="page-subtitle"><?= e($sale['sale_date']) ?> · <?= e($sale['customer_name'] ?: ($sale['walkin_name'] ?: 'Walk-in')) ?></div>
  </div>
  <div>
    <a href="/sales/print.php?id=<?= $id ?>" class="btn btn-outline-secondary" target="_blank">Print invoice</a>
    <?php if ($sale['party_id']): ?><a href="/parties/view.php?id=<?= (int)$sale['party_id'] ?>" class="btn btn-outline-secondary">Customer ledger</a><?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <table class="table mb-0">
    <thead><tr><th>Product</th><th>Serial</th><th>Qty</th><th class="text-end">Price</th><th class="text-end">Line Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= e($it['product_name']) ?> <span class="text-muted small"><?= e($it['sku']) ?></span></td>
        <td><?= e($it['serial_no']) ?></td>
        <td><?= (int)$it['qty'] ?></td>
        <td class="text-end"><?= format_currency($it['sell_price']) ?></td>
        <td class="text-end"><?= format_currency($it['line_total']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end"><?= format_currency($sale['subtotal']) ?></td></tr>
      <?php if ($sale['vat_amount'] > 0): ?>
      <tr><td colspan="4" class="text-end">VAT</td><td class="text-end"><?= format_currency($sale['vat_amount']) ?></td></tr>
      <?php endif; ?>
      <tr><td colspan="4" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold"><?= format_currency($sale['total']) ?></td></tr>
    </tfoot>
  </table>
</div>

<div class="card p-3" style="max-width:420px;">
  <div class="fw-semibold mb-2">Payments</div>
  <?php foreach ($payments as $p): ?>
  <div class="d-flex justify-content-between">
    <span><?= e(ucfirst($p['method'])) ?></span>
    <span><?= format_currency($p['amount']) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
