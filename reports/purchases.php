<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csv_export.php';
require_login();

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);
$deviceFilter = trim($_GET['device_model'] ?? '');
$brandFilter = trim($_GET['brand'] ?? '');

$where = [];
$params = [];
if ($from) { $where[] = 'pu.purchase_date >= ?'; $params[] = $from; }
if ($to) { $where[] = 'pu.purchase_date <= ?'; $params[] = $to; }
if ($supplierId) { $where[] = 'pu.party_id = ?'; $params[] = $supplierId; }
if ($productId) { $where[] = 'pi.product_id = ?'; $params[] = $productId; }
if ($deviceFilter !== '') { $where[] = 'p.device_model = ?'; $params[] = $deviceFilter; }
if ($brandFilter !== '') { $where[] = 'p.brand = ?'; $params[] = $brandFilter; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT pu.id, pu.purchase_date, pu.bill_ref, pa.name AS supplier_name,
               p.name AS product_name, p.brand, p.device_model, pi.qty, pi.cost_price, pi.line_total, pi.serial_no
        FROM purchase_items pi
        JOIN purchases pu ON pu.id = pi.purchase_id
        JOIN parties pa ON pa.id = pu.party_id
        JOIN products p ON p.id = pi.product_id
        $whereSql
        ORDER BY pu.purchase_date DESC, pu.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    $csvRows = array_map(fn($r) => [$r['purchase_date'], $r['bill_ref'], $r['supplier_name'], $r['product_name'], $r['brand'], $r['device_model'], $r['qty'], $r['cost_price'], $r['line_total'], $r['serial_no']], $rows);
    export_csv('purchases_report.csv', ['Date', 'Bill Ref', 'Supplier', 'Product', 'Brand', 'Device Model', 'Qty', 'Cost Price', 'Line Total', 'Serial'], $csvRows);
}

$suppliers = $pdo->query("SELECT id, name FROM parties WHERE type = 'supplier' ORDER BY name")->fetchAll();
$products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();
$deviceModels = $pdo->query(
    "SELECT DISTINCT device_model FROM products WHERE device_model IS NOT NULL AND device_model <> '' ORDER BY device_model"
)->fetchAll(PDO::FETCH_COLUMN);
$brands = $pdo->query(
    "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand"
)->fetchAll(PDO::FETCH_COLUMN);
$total = array_sum(array_column($rows, 'line_total'));

$pageTitle = 'Purchases Report';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Purchases Report</div></div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
  <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
  <div class="col-auto">
    <select name="supplier_id" class="form-select form-select-sm">
      <option value="">All suppliers</option>
      <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="product_id" class="form-select form-select-sm">
      <option value="">All products</option>
      <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="brand" class="form-select form-select-sm">
      <option value="">All brands</option>
      <?php foreach ($brands as $b): ?><option value="<?= e($b) ?>" <?= $brandFilter === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="device_model" class="form-select form-select-sm">
      <option value="">All device models</option>
      <?php foreach ($deviceModels as $dm): ?><option value="<?= e($dm) ?>" <?= $deviceFilter === $dm ? 'selected' : '' ?>><?= e($dm) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
  <div class="col-auto"><a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">Export CSV</a></div>
</form>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Bill</th><th>Supplier</th><th>Product</th><th>Brand</th><th>Device Model</th><th>Qty</th><th class="text-end">Cost</th><th class="text-end">Line Total</th><th>Serial</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['purchase_date']) ?></td>
        <td><?= e($r['bill_ref']) ?></td>
        <td><?= e($r['supplier_name']) ?></td>
        <td><?= e($r['product_name']) ?></td>
        <td><?= e($r['brand']) ?></td>
        <td><?= e($r['device_model']) ?></td>
        <td><?= (int)$r['qty'] ?></td>
        <td class="text-end"><?= format_currency($r['cost_price']) ?></td>
        <td class="text-end"><?= format_currency($r['line_total']) ?></td>
        <td class="small"><?= e($r['serial_no']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">No results.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows): ?><tfoot><tr><td colspan="8" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold"><?= format_currency($total) ?></td><td></td></tr></tfoot><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
