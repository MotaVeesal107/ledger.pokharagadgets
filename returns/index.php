<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$stmt = $pdo->query(
    "SELECT r.*, p.name AS product_name, s.invoice_no
     FROM returns_adjustments r
     JOIN products p ON p.id = r.product_id
     LEFT JOIN sales s ON s.id = r.related_sale_id
     ORDER BY r.adjustment_date DESC, r.id DESC"
);
$rows = $stmt->fetchAll();

$pageTitle = 'Returns & Adjustments';
$active = 'returns';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Returns &amp; Adjustments</div>
    <div class="page-subtitle">Customer returns and damaged/lost write-offs. Excluded from normal sales/purchase totals.</div>
  </div>
  <a href="/returns/add.php" class="btn btn-accent">+ New entry</a>
</div>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Serial</th><th>Related Sale</th><th>Restocked</th><th>Note</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['adjustment_date']) ?></td>
        <td><?= e($r['product_name']) ?></td>
        <td><span class="badge text-bg-light border"><?= e(str_replace('_', ' ', ucfirst($r['type']))) ?></span></td>
        <td><?= (int)$r['qty'] ?></td>
        <td><?= e($r['serial_no']) ?></td>
        <td><?= $r['invoice_no'] ? '<a href="/sales/view.php?id=' . (int)$r['related_sale_id'] . '">' . e($r['invoice_no']) . '</a>' : '—' ?></td>
        <td><?= $r['type'] === 'customer_return' ? ($r['restock'] ? 'Yes' : 'No (damaged)') : '—' ?></td>
        <td class="small text-muted"><?= e($r['note']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
      <tr><td colspan="8" class="text-center text-muted py-4">No returns or adjustments recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
