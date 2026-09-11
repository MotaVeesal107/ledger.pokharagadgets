<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$customerId = (int)($_GET['customer_id'] ?? 0);

$where = [];
$params = [];
if ($from) { $where[] = 's.sale_date >= ?'; $params[] = $from; }
if ($to) { $where[] = 's.sale_date <= ?'; $params[] = $to; }
if ($customerId) { $where[] = 's.party_id = ?'; $params[] = $customerId; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT s.*, pa.name AS customer_name FROM sales s
     LEFT JOIN parties pa ON pa.id = s.party_id
     $whereSql ORDER BY s.sale_date DESC, s.id DESC"
);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$customers = $pdo->query("SELECT id, name FROM parties WHERE type = 'customer' ORDER BY name")->fetchAll();

$pageTitle = 'Sales';
$active = 'sales';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Sales</div>
    <div class="page-subtitle">Stock-out to customers, walk-ins welcome.</div>
  </div>
  <a href="/sales/add.php" class="btn btn-accent">+ New sale</a>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
  <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
  <div class="col-auto">
    <select name="customer_id" class="form-select form-select-sm">
      <option value="">All customers</option>
      <?php foreach ($customers as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
</form>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="text-end">Total</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($sales as $s): ?>
      <tr>
        <td><?= e($s['invoice_no']) ?></td>
        <td><?= e($s['sale_date']) ?></td>
        <td><?= e($s['customer_name'] ?: ($s['walkin_name'] ?: 'Walk-in')) ?></td>
        <td class="text-end"><?= format_currency($s['total']) ?></td>
        <td class="text-end">
          <a href="/sales/view.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
          <a href="/sales/print.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Print</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$sales): ?>
      <tr><td colspan="5" class="text-center text-muted py-4">No sales recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
