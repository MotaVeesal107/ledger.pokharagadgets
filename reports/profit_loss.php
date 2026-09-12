<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csv_export.php';
require_login();

if (!can_view_cost($pdo)) {
    http_response_code(403);
    die('You do not have permission to view financial reports.');
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare('SELECT COALESCE(SUM(subtotal), 0) AS subtotal, COALESCE(SUM(vat_amount), 0) AS vat, COUNT(*) AS cnt FROM sales WHERE sale_date BETWEEN ? AND ?');
$stmt->execute([$from, $to]);
$sales = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(si.qty * p.cost_price_ref), 0) FROM sale_items si
     JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
     WHERE s.sale_date BETWEEN ? AND ?"
);
$stmt->execute([$from, $to]);
$cogs = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT category, COALESCE(SUM(amount), 0) AS amt FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY category');
$stmt->execute([$from, $to]);
$expensesByCategory = $stmt->fetchAll();
$totalExpenses = array_sum(array_column($expensesByCategory, 'amt'));

$stmt = $pdo->prepare('SELECT category, COALESCE(SUM(amount), 0) AS amt FROM other_income WHERE income_date BETWEEN ? AND ? GROUP BY category');
$stmt->execute([$from, $to]);
$incomeByCategory = $stmt->fetchAll();
$totalOtherIncome = array_sum(array_column($incomeByCategory, 'amt'));

$grossProfit = (float)$sales['subtotal'] - $cogs;
$netProfit = $grossProfit - $totalExpenses + $totalOtherIncome;

if (($_GET['export'] ?? '') === 'csv') {
    $csvRows = [
        ['Sales subtotal', $sales['subtotal']],
        ['Cost of goods sold (est.)', $cogs],
        ['Gross profit', $grossProfit],
        ['Total expenses', $totalExpenses],
        ['Other income', $totalOtherIncome],
        ['Net profit', $netProfit],
    ];
    export_csv('profit_loss_' . $from . '_to_' . $to . '.csv', ['Line', 'Amount'], $csvRows);
}

$pageTitle = 'Profit & Loss';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Profit &amp; Loss</div></div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
  <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Update</button></div>
  <div class="col-auto"><a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">Export CSV</a></div>
</form>

<div class="card p-4" style="max-width:520px;">
  <div class="small text-muted mb-3"><?= e($from) ?> to <?= e($to) ?> · <?= (int)$sales['cnt'] ?> sale(s)</div>
  <table class="table table-borderless mb-0">
    <tr><td>Sales (subtotal, excl. VAT)</td><td class="text-end"><?= format_currency($sales['subtotal']) ?></td></tr>
    <tr><td>Cost of goods sold (estimated)</td><td class="text-end">- <?= format_currency($cogs) ?></td></tr>
    <tr class="border-top"><td class="fw-semibold">Gross profit</td><td class="text-end fw-semibold"><?= format_currency($grossProfit) ?></td></tr>
    <?php foreach ($expensesByCategory as $ec): ?>
    <tr><td class="ps-3 text-muted small">Expense: <?= e(ucfirst($ec['category'])) ?></td><td class="text-end small text-muted">- <?= format_currency($ec['amt']) ?></td></tr>
    <?php endforeach; ?>
    <tr><td>Total expenses</td><td class="text-end">- <?= format_currency($totalExpenses) ?></td></tr>
    <?php foreach ($incomeByCategory as $ic): ?>
    <tr><td class="ps-3 text-muted small">Income: <?= e(ucfirst($ic['category'])) ?></td><td class="text-end small text-muted">+ <?= format_currency($ic['amt']) ?></td></tr>
    <?php endforeach; ?>
    <tr><td>Other income</td><td class="text-end">+ <?= format_currency($totalOtherIncome) ?></td></tr>
    <tr class="border-top"><td class="fw-semibold">Net profit</td><td class="text-end fw-semibold <?= $netProfit < 0 ? 'text-danger' : '' ?>"><?= format_currency($netProfit) ?></td></tr>
  </table>
  <div class="form-text mt-2">COGS is estimated using each product's current reference cost price, not FIFO lot costing.</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
