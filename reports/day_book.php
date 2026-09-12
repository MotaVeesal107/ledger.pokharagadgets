<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csv_export.php';
require_login();

$date = $_GET['date'] ?? date('Y-m-d');

$rows = [];

$stmt = $pdo->prepare(
    "SELECT s.id, s.created_at, s.invoice_no, s.total, COALESCE(pa.name, s.walkin_name, 'Walk-in') AS party_name
     FROM sales s LEFT JOIN parties pa ON pa.id = s.party_id WHERE s.sale_date = ?"
);
$stmt->execute([$date]);
foreach ($stmt->fetchAll() as $r) {
    $rows[] = ['created_at' => $r['created_at'], 'type' => 'Sale', 'description' => "Invoice {$r['invoice_no']} — {$r['party_name']}", 'in' => (float)$r['total'], 'out' => 0];
}

$stmt = $pdo->prepare(
    "SELECT pu.id, pu.created_at, pu.bill_ref, pu.total, pa.name AS party_name
     FROM purchases pu JOIN parties pa ON pa.id = pu.party_id WHERE pu.purchase_date = ?"
);
$stmt->execute([$date]);
foreach ($stmt->fetchAll() as $r) {
    $ref = $r['bill_ref'] ?: ('#' . $r['id']);
    $rows[] = ['created_at' => $r['created_at'], 'type' => 'Purchase', 'description' => "Bill {$ref} — {$r['party_name']}", 'in' => 0, 'out' => (float)$r['total']];
}

$stmt = $pdo->prepare(
    "SELECT pp.id, pp.created_at, pp.direction, pp.amount, pa.name AS party_name
     FROM party_payments pp JOIN parties pa ON pa.id = pp.party_id WHERE pp.payment_date = ?"
);
$stmt->execute([$date]);
foreach ($stmt->fetchAll() as $r) {
    $isReceived = $r['direction'] === 'received_from_customer';
    $rows[] = [
        'created_at' => $r['created_at'],
        'type' => $isReceived ? 'Payment In' : 'Payment Out',
        'description' => ($isReceived ? 'Received from ' : 'Paid to ') . $r['party_name'],
        'in' => $isReceived ? (float)$r['amount'] : 0,
        'out' => $isReceived ? 0 : (float)$r['amount'],
    ];
}

$stmt = $pdo->prepare('SELECT id, created_at, category, amount, note FROM expenses WHERE expense_date = ?');
$stmt->execute([$date]);
foreach ($stmt->fetchAll() as $r) {
    $rows[] = ['created_at' => $r['created_at'], 'type' => 'Expense', 'description' => ucfirst($r['category']) . ($r['note'] ? " — {$r['note']}" : ''), 'in' => 0, 'out' => (float)$r['amount']];
}

$stmt = $pdo->prepare('SELECT id, created_at, category, amount, note FROM other_income WHERE income_date = ?');
$stmt->execute([$date]);
foreach ($stmt->fetchAll() as $r) {
    $rows[] = ['created_at' => $r['created_at'], 'type' => 'Other Income', 'description' => ucfirst($r['category']) . ($r['note'] ? " — {$r['note']}" : ''), 'in' => (float)$r['amount'], 'out' => 0];
}

usort($rows, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));

$totalIn = array_sum(array_column($rows, 'in'));
$totalOut = array_sum(array_column($rows, 'out'));

if (($_GET['export'] ?? '') === 'csv') {
    $csvRows = array_map(fn($r) => [$r['created_at'], $r['type'], $r['description'], $r['in'], $r['out']], $rows);
    export_csv("day_book_{$date}.csv", ['Time', 'Type', 'Description', 'Money In', 'Money Out'], $csvRows);
}

$pageTitle = 'Day Book';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Day Book</div></div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>"></div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">View</button></div>
  <div class="col-auto"><a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">Export CSV</a></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card p-3 stat-card">
      <div class="stat-label">Total Money In</div>
      <div class="stat-value text-success"><?= format_currency($totalIn) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 stat-card">
      <div class="stat-label">Total Money Out</div>
      <div class="stat-value text-danger"><?= format_currency($totalOut) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 stat-card">
      <div class="stat-label">Net</div>
      <div class="stat-value <?= ($totalIn - $totalOut) < 0 ? 'text-danger' : '' ?>"><?= format_currency($totalIn - $totalOut) ?></div>
    </div>
  </div>
</div>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Time</th><th>Type</th><th>Description</th><th class="text-end">Money In</th><th class="text-end">Money Out</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(substr($r['created_at'], 11, 5)) ?></td>
        <td><span class="badge text-bg-light border"><?= e($r['type']) ?></span></td>
        <td><?= e($r['description']) ?></td>
        <td class="text-end text-success"><?= $r['in'] > 0 ? format_currency($r['in']) : '' ?></td>
        <td class="text-end text-danger"><?= $r['out'] > 0 ? format_currency($r['out']) : '' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
      <tr><td colspan="5" class="text-center text-muted py-4">No transactions on this date.</td></tr>
      <?php endif; ?>
    </tbody>
    <?php if ($rows): ?>
    <tfoot><tr><td colspan="3" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold text-success"><?= format_currency($totalIn) ?></td><td class="text-end fw-semibold text-danger"><?= format_currency($totalOut) ?></td></tr></tfoot>
    <?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
