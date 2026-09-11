<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csv_export.php';
require_login();

$productId = (int)($_GET['product_id'] ?? 0);
$products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();

$rows = [];
if ($productId) {
    $stmt = $pdo->prepare(
        'SELECT * FROM stock_movements WHERE product_id = ? ORDER BY movement_date ASC, id ASC'
    );
    $stmt->execute([$productId]);
    $running = 0;
    foreach ($stmt->fetchAll() as $m) {
        $running += $m['type'] === 'in' ? $m['qty'] : -$m['qty'];
        $m['running'] = $running;
        $rows[] = $m;
    }
    $rows = array_reverse($rows);
}

if (($_GET['export'] ?? '') === 'csv' && $productId) {
    $csvRows = array_map(fn($r) => [$r['movement_date'], $r['type'], $r['qty'], $r['ref_type'], $r['ref_id'], $r['running'], $r['note']], $rows);
    export_csv('stock_ledger.csv', ['Date', 'Type', 'Qty', 'Ref Type', 'Ref Id', 'Running Stock', 'Note'], $csvRows);
}

$pageTitle = 'Stock Ledger';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Stock Ledger</div></div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto">
    <select name="product_id" class="form-select form-select-sm" required>
      <option value="">Select product...</option>
      <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">View</button></div>
  <?php if ($productId): ?>
  <div class="col-auto"><a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">Export CSV</a></div>
  <?php endif; ?>
</form>

<?php if ($productId): ?>
<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Type</th><th>Qty</th><th>Source</th><th class="text-end">Running Stock</th><th>Note</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['movement_date']) ?></td>
        <td><span class="badge <?= $r['type'] === 'in' ? 'badge-instock' : 'badge-out' ?>"><?= strtoupper($r['type']) ?></span></td>
        <td><?= (int)$r['qty'] ?></td>
        <td><?= e(ucfirst($r['ref_type'])) ?> <?= $r['ref_id'] ? '#' . (int)$r['ref_id'] : '' ?></td>
        <td class="text-end fw-semibold"><?= (int)$r['running'] ?></td>
        <td class="small text-muted"><?= e($r['note']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No stock movements for this product yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
