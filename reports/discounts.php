<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csv_export.php';
require_login();

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$partyId = (int)($_GET['party_id'] ?? 0);

$saleWhere = ['s.sale_date BETWEEN ? AND ?', 'si.discount_percent > 0'];
$saleParams = [$from, $to];
if ($partyId) { $saleWhere[] = 's.party_id = ?'; $saleParams[] = $partyId; }

$stmt = $pdo->prepare(
    "SELECT s.sale_date, s.invoice_no, COALESCE(pa.name, s.walkin_name, 'Walk-in') AS party_name,
            p.name AS product_name, si.qty, si.sell_price, si.discount_percent,
            (si.qty * si.sell_price) - si.line_total AS discount_amount
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     LEFT JOIN parties pa ON pa.id = s.party_id
     JOIN products p ON p.id = si.product_id
     WHERE " . implode(' AND ', $saleWhere) . "
     ORDER BY s.sale_date DESC, s.id DESC"
);
$stmt->execute($saleParams);
$saleDiscounts = $stmt->fetchAll();
$totalSaleDiscount = array_sum(array_column($saleDiscounts, 'discount_amount'));

$purchaseWhere = ['pu.purchase_date BETWEEN ? AND ?', 'pi.discount_percent > 0'];
$purchaseParams = [$from, $to];
if ($partyId) { $purchaseWhere[] = 'pu.party_id = ?'; $purchaseParams[] = $partyId; }

$stmt = $pdo->prepare(
    "SELECT pu.purchase_date, pu.bill_ref, pa.name AS party_name,
            p.name AS product_name, pi.qty, pi.cost_price, pi.discount_percent,
            (pi.qty * pi.cost_price) - pi.line_total AS discount_amount
     FROM purchase_items pi
     JOIN purchases pu ON pu.id = pi.purchase_id
     JOIN parties pa ON pa.id = pu.party_id
     JOIN products p ON p.id = pi.product_id
     WHERE " . implode(' AND ', $purchaseWhere) . "
     ORDER BY pu.purchase_date DESC, pu.id DESC"
);
$stmt->execute($purchaseParams);
$purchaseDiscounts = $stmt->fetchAll();
$totalPurchaseDiscount = array_sum(array_column($purchaseDiscounts, 'discount_amount'));

$parties = $pdo->query('SELECT id, name, type FROM parties ORDER BY name')->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    $csvRows = [];
    foreach ($saleDiscounts as $r) {
        $csvRows[] = ['Sale', $r['sale_date'], $r['invoice_no'], $r['party_name'], $r['product_name'], $r['qty'], $r['discount_percent'], $r['discount_amount']];
    }
    foreach ($purchaseDiscounts as $r) {
        $csvRows[] = ['Purchase', $r['purchase_date'], $r['bill_ref'], $r['party_name'], $r['product_name'], $r['qty'], $r['discount_percent'], $r['discount_amount']];
    }
    export_csv("discount_report_{$from}_to_{$to}.csv", ['Type', 'Date', 'Ref', 'Party', 'Product', 'Qty', 'Discount %', 'Discount Amount'], $csvRows);
}

$pageTitle = 'Discount Report';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Discount Report</div></div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
  <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
  <div class="col-auto">
    <select name="party_id" class="form-select form-select-sm">
      <option value="">All parties</option>
      <?php foreach ($parties as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= $partyId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e(ucfirst($p['type'])) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
  <div class="col-auto"><a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">Export CSV</a></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card p-3 stat-card">
      <div class="stat-label">Discount given to customers</div>
      <div class="stat-value"><?= format_currency($totalSaleDiscount) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 stat-card">
      <div class="stat-label">Discount received from suppliers</div>
      <div class="stat-value"><?= format_currency($totalPurchaseDiscount) ?></div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="p-3 border-bottom fw-semibold">Sales Discounts</div>
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Product</th><th>Qty</th><th class="text-end">Disc. %</th><th class="text-end">Discount Amount</th></tr></thead>
    <tbody>
      <?php foreach ($saleDiscounts as $r): ?>
      <tr>
        <td><?= e($r['sale_date']) ?></td>
        <td><?= e($r['invoice_no']) ?></td>
        <td><?= e($r['party_name']) ?></td>
        <td><?= e($r['product_name']) ?></td>
        <td><?= (int)$r['qty'] ?></td>
        <td class="text-end"><?= e($r['discount_percent']) ?>%</td>
        <td class="text-end"><?= format_currency($r['discount_amount']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$saleDiscounts): ?><tr><td colspan="7" class="text-center text-muted py-4">No discounted sales in this range.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($saleDiscounts): ?><tfoot><tr><td colspan="6" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold"><?= format_currency($totalSaleDiscount) ?></td></tr></tfoot><?php endif; ?>
  </table>
</div>

<div class="card">
  <div class="p-3 border-bottom fw-semibold">Purchase Discounts</div>
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Bill</th><th>Supplier</th><th>Product</th><th>Qty</th><th class="text-end">Disc. %</th><th class="text-end">Discount Amount</th></tr></thead>
    <tbody>
      <?php foreach ($purchaseDiscounts as $r): ?>
      <tr>
        <td><?= e($r['purchase_date']) ?></td>
        <td><?= e($r['bill_ref']) ?></td>
        <td><?= e($r['party_name']) ?></td>
        <td><?= e($r['product_name']) ?></td>
        <td><?= (int)$r['qty'] ?></td>
        <td class="text-end"><?= e($r['discount_percent']) ?>%</td>
        <td class="text-end"><?= format_currency($r['discount_amount']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$purchaseDiscounts): ?><tr><td colspan="7" class="text-center text-muted py-4">No discounted purchases in this range.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($purchaseDiscounts): ?><tfoot><tr><td colspan="6" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold"><?= format_currency($totalPurchaseDiscount) ?></td></tr></tfoot><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
