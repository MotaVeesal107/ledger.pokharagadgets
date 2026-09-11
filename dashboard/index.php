<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stock.php';
require_once __DIR__ . '/../includes/parties.php';
require_login();

$today = date('Y-m-d');
$showFinancials = can_view_cost($pdo);

// Today's sales: total + count + breakdown by payment method.
$stmt = $pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS amt, COALESCE(SUM(subtotal), 0) AS subtotal FROM sales WHERE sale_date = ?');
$stmt->execute([$today]);
$todaySales = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT sp.method, SUM(sp.amount) AS amt FROM sale_payments sp
     JOIN sales s ON s.id = sp.sale_id WHERE s.sale_date = ? GROUP BY sp.method"
);
$stmt->execute([$today]);
$salesByMethod = $stmt->fetchAll();

// Today's purchases: total + count.
$stmt = $pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS amt FROM purchases WHERE purchase_date = ?');
$stmt->execute([$today]);
$todayPurchases = $stmt->fetch();

// Today's profit estimate: sales subtotal - COGS (using each product's reference cost, since
// no per-lot FIFO costing is tracked) - today's expenses.
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(si.qty * p.cost_price_ref), 0) FROM sale_items si
     JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id WHERE s.sale_date = ?"
);
$stmt->execute([$today]);
$todayCogs = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = ?');
$stmt->execute([$today]);
$todayExpenses = (float)$stmt->fetchColumn();

$todayProfit = (float)$todaySales['subtotal'] - $todayCogs - $todayExpenses;

// Low stock products.
$lowStock = $pdo->query(
    "SELECT p.*, COALESCE(SUM(CASE WHEN sm.type='in' THEN sm.qty WHEN sm.type='out' THEN -sm.qty ELSE 0 END), 0) AS stock
     FROM products p LEFT JOIN stock_movements sm ON sm.product_id = p.id
     GROUP BY p.id HAVING stock <= p.low_stock_threshold ORDER BY stock ASC"
)->fetchAll();

$totalCustomerDues = get_total_customer_dues($pdo);
$totalSupplierDues = get_total_supplier_dues($pdo);

// Recent 10 transactions (purchases + sales combined).
$recentSales = $pdo->query(
    "SELECT 'sale' AS kind, s.id, s.sale_date AS txn_date, s.total, s.invoice_no AS ref,
            COALESCE(pa.name, s.walkin_name, 'Walk-in') AS party_name, s.created_at
     FROM sales s LEFT JOIN parties pa ON pa.id = s.party_id ORDER BY s.created_at DESC LIMIT 10"
)->fetchAll();
$recentPurchases = $pdo->query(
    "SELECT 'purchase' AS kind, pu.id, pu.purchase_date AS txn_date, pu.total, pu.bill_ref AS ref,
            pa.name AS party_name, pu.created_at
     FROM purchases pu JOIN parties pa ON pa.id = pu.party_id ORDER BY pu.created_at DESC LIMIT 10"
)->fetchAll();
$recent = array_merge($recentSales, $recentPurchases);
usort($recent, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$recent = array_slice($recent, 0, 10);

$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Dashboard</div>
    <div class="page-subtitle">Today, <?= date('l, F j, Y') ?></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card p-3 stat-card">
      <div class="stat-label">Today's Sales</div>
      <div class="stat-value"><?= format_currency($todaySales['amt']) ?></div>
      <div class="small text-muted"><?= (int)$todaySales['cnt'] ?> sale(s)</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 stat-card">
      <div class="stat-label">Today's Purchases</div>
      <div class="stat-value"><?= format_currency($todayPurchases['amt']) ?></div>
      <div class="small text-muted"><?= (int)$todayPurchases['cnt'] ?> purchase(s)</div>
    </div>
  </div>
  <?php if ($showFinancials): ?>
  <div class="col-md-3">
    <div class="card p-3 stat-card">
      <div class="stat-label">Today's Profit (est.)</div>
      <div class="stat-value <?= $todayProfit < 0 ? 'text-danger' : '' ?>"><?= format_currency($todayProfit) ?></div>
      <div class="small text-muted">Sales − COGS − expenses</div>
    </div>
  </div>
  <?php endif; ?>
  <div class="col-md-3">
    <div class="card p-3 stat-card">
      <div class="stat-label">Low on Stock</div>
      <div class="stat-value"><?= count($lowStock) ?></div>
      <div class="small text-muted">of <?= $totalProducts ?> products</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card p-3">
      <div class="stat-label mb-2">Today's Sales by Method</div>
      <?php if (!$salesByMethod): ?><div class="text-muted small">No sales today yet.</div><?php endif; ?>
      <?php foreach ($salesByMethod as $m): ?>
      <div class="d-flex justify-content-between small mb-1">
        <span><?= e(ucfirst($m['method'])) ?></span><span><?= format_currency($m['amt']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3">
      <div class="stat-label mb-2">Customers owe the shop</div>
      <div class="stat-value text-danger"><?= format_currency($totalCustomerDues) ?></div>
      <a href="/parties/index.php?type=customer" class="small">View customers &raquo;</a>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3">
      <div class="stat-label mb-2">Shop owes suppliers</div>
      <div class="stat-value" style="color:#9a6a00;"><?= format_currency($totalSupplierDues) ?></div>
      <a href="/parties/index.php?type=supplier" class="small">View suppliers &raquo;</a>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card">
      <div class="p-3 border-bottom fw-semibold">Low Stock Products</div>
      <table class="table mb-0">
        <thead><tr><th>Product</th><th>Stock</th><th>Threshold</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($lowStock, 0, 10) as $p): ?>
          <tr>
            <td><?= e($p['name']) ?></td>
            <td><span class="badge <?= $p['stock'] <= 0 ? 'badge-out' : 'badge-low' ?>"><?= (int)$p['stock'] ?></span></td>
            <td><?= (int)$p['low_stock_threshold'] ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$lowStock): ?><tr><td colspan="3" class="text-center text-muted py-3">Nothing low on stock.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="p-3 border-bottom fw-semibold">Recent Activity</div>
      <table class="table mb-0">
        <thead><tr><th>Date</th><th>Type</th><th>Party</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= e($r['txn_date']) ?></td>
            <td><span class="badge text-bg-light border"><?= $r['kind'] === 'sale' ? 'Sale' : 'Purchase' ?></span></td>
            <td><?= e($r['party_name']) ?></td>
            <td class="text-end"><?= format_currency($r['total']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?><tr><td colspan="4" class="text-center text-muted py-3">No activity yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
