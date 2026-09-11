<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csv_export.php';
require_login();

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$customerId = (int)($_GET['customer_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);
$method = $_GET['method'] ?? '';

$where = [];
$params = [];
if ($from) { $where[] = 's.sale_date >= ?'; $params[] = $from; }
if ($to) { $where[] = 's.sale_date <= ?'; $params[] = $to; }
if ($customerId) { $where[] = 's.party_id = ?'; $params[] = $customerId; }
if ($productId) { $where[] = 'si.product_id = ?'; $params[] = $productId; }
if ($method) { $where[] = 'sp.method = ?'; $params[] = $method; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT DISTINCT s.id, s.invoice_no, s.sale_date, COALESCE(pa.name, s.walkin_name, 'Walk-in') AS customer_name,
               p.name AS product_name, si.qty, si.sell_price, si.line_total, si.serial_no
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        LEFT JOIN parties pa ON pa.id = s.party_id
        LEFT JOIN sale_payments sp ON sp.sale_id = s.id
        JOIN products p ON p.id = si.product_id
        $whereSql
        ORDER BY s.sale_date DESC, s.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    $csvRows = array_map(fn($r) => [$r['sale_date'], $r['invoice_no'], $r['customer_name'], $r['product_name'], $r['qty'], $r['sell_price'], $r['line_total'], $r['serial_no']], $rows);
    export_csv('sales_report.csv', ['Date', 'Invoice', 'Customer', 'Product', 'Qty', 'Sell Price', 'Line Total', 'Serial'], $csvRows);
}

$customers = $pdo->query("SELECT id, name FROM parties WHERE type = 'customer' ORDER BY name")->fetchAll();
$products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();
$total = array_sum(array_column($rows, 'line_total'));

$pageTitle = 'Sales Report';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Sales Report</div></div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
  <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
  <div class="col-auto">
    <select name="customer_id" class="form-select form-select-sm">
      <option value="">All customers</option>
      <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="product_id" class="form-select form-select-sm">
      <option value="">All products</option>
      <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="method" class="form-select form-select-sm">
      <option value="">All payment methods</option>
      <?php foreach (['cash', 'esewa', 'khalti', 'fonepay', 'bank', 'due'] as $m): ?><option value="<?= $m ?>" <?= $method === $m ? 'selected' : '' ?>><?= ucfirst($m) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
  <div class="col-auto"><a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">Export CSV</a></div>
</form>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Product</th><th>Qty</th><th class="text-end">Price</th><th class="text-end">Line Total</th><th>Serial</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['sale_date']) ?></td>
        <td><a href="/sales/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['invoice_no']) ?></a></td>
        <td><?= e($r['customer_name']) ?></td>
        <td><?= e($r['product_name']) ?></td>
        <td><?= (int)$r['qty'] ?></td>
        <td class="text-end"><?= format_currency($r['sell_price']) ?></td>
        <td class="text-end"><?= format_currency($r['line_total']) ?></td>
        <td class="small"><?= e($r['serial_no']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No results.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows): ?><tfoot><tr><td colspan="6" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold"><?= format_currency($total) ?></td><td></td></tr></tfoot><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
