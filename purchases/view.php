<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT pu.*, pa.name AS supplier_name FROM purchases pu JOIN parties pa ON pa.id = pu.party_id WHERE pu.id = ?');
$stmt->execute([$id]);
$purchase = $stmt->fetch();
if (!$purchase) {
    flash_set('error', 'Purchase not found.');
    redirect('/purchases/index.php');
}

$stmt = $pdo->prepare(
    'SELECT pi.*, p.name AS product_name, p.sku FROM purchase_items pi JOIN products p ON p.id = pi.product_id WHERE pi.purchase_id = ? ORDER BY pi.id'
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Group non-serialized/serialized rows of the same product+cost into one display line.
$grouped = [];
foreach ($items as $it) {
    $key = $it['product_id'] . '|' . $it['cost_price'] . '|' . ($it['serial_no'] ? 'S' : 'B');
    if (!isset($grouped[$key])) {
        $grouped[$key] = ['product_name' => $it['product_name'], 'sku' => $it['sku'], 'cost_price' => $it['cost_price'], 'qty' => 0, 'line_total' => 0, 'serials' => []];
    }
    $grouped[$key]['qty'] += (int)$it['qty'];
    $grouped[$key]['line_total'] += (float)$it['line_total'];
    if ($it['serial_no']) {
        $grouped[$key]['serials'][] = $it['serial_no'];
    }
}

$pageTitle = 'Purchase #' . $id;
$active = 'purchases';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Purchase #<?= $id ?></div>
    <div class="page-subtitle"><?= e($purchase['purchase_date']) ?> · <?= e($purchase['supplier_name']) ?> <?= $purchase['bill_ref'] ? '· Bill ' . e($purchase['bill_ref']) : '' ?></div>
  </div>
  <a href="/parties/view.php?id=<?= (int)$purchase['party_id'] ?>" class="btn btn-outline-secondary">Supplier ledger</a>
</div>

<?php if ($purchase['note']): ?><div class="alert alert-secondary py-2"><?= e($purchase['note']) ?></div><?php endif; ?>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Product</th><th>Qty</th><th class="text-end">Cost Price</th><th class="text-end">Line Total</th><th>Serials</th></tr></thead>
    <tbody>
      <?php foreach ($grouped as $g): ?>
      <tr>
        <td><?= e($g['product_name']) ?> <span class="text-muted small"><?= e($g['sku']) ?></span></td>
        <td><?= $g['qty'] ?></td>
        <td class="text-end"><?= format_currency($g['cost_price']) ?></td>
        <td class="text-end"><?= format_currency($g['line_total']) ?></td>
        <td class="small"><?= e(implode(', ', $g['serials'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="3" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold"><?= format_currency($purchase['total']) ?></td><td></td></tr></tfoot>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
