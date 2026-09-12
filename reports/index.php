<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Reports';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Reports</div></div>
<div class="row g-3">
  <div class="col-md-4"><a href="/reports/purchases.php" class="card p-3 text-decoration-none text-dark d-block h-100">
    <div class="fw-semibold">Purchases</div><div class="small text-muted">Filter by date, supplier, product. CSV export.</div></a></div>
  <div class="col-md-4"><a href="/reports/sales.php" class="card p-3 text-decoration-none text-dark d-block h-100">
    <div class="fw-semibold">Sales</div><div class="small text-muted">Filter by date, customer, product, payment method. CSV export.</div></a></div>
  <div class="col-md-4"><a href="/reports/stock_ledger.php" class="card p-3 text-decoration-none text-dark d-block h-100">
    <div class="fw-semibold">Stock Ledger</div><div class="small text-muted">Full in/out history per product. CSV export.</div></a></div>
  <div class="col-md-4"><a href="/reports/serial_lookup.php" class="card p-3 text-decoration-none text-dark d-block h-100">
    <div class="fw-semibold">IMEI / Serial Lookup</div><div class="small text-muted">Purchase date, sale date, customer, warranty status.</div></a></div>
  <div class="col-md-4"><a href="/reports/day_book.php" class="card p-3 text-decoration-none text-dark d-block h-100">
    <div class="fw-semibold">Day Book</div><div class="small text-muted">Every transaction on a chosen day — sales, purchases, payments, expenses, income. CSV export.</div></a></div>
  <div class="col-md-4"><a href="/reports/discounts.php" class="card p-3 text-decoration-none text-dark d-block h-100">
    <div class="fw-semibold">Discount Report</div><div class="small text-muted">Total discount given, by date range and party. CSV export.</div></a></div>
  <?php if (can_view_cost($pdo)): ?>
  <div class="col-md-4"><a href="/reports/profit_loss.php" class="card p-3 text-decoration-none text-dark d-block h-100">
    <div class="fw-semibold">Profit &amp; Loss</div><div class="small text-muted">Sales − COGS − expenses, by date range. CSV export.</div></a></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
