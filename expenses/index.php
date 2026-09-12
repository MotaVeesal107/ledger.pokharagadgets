<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$category = $_GET['category'] ?? '';

$where = [];
$params = [];
if ($from) { $where[] = 'expense_date >= ?'; $params[] = $from; }
if ($to) { $where[] = 'expense_date <= ?'; $params[] = $to; }
if ($category) { $where[] = 'category = ?'; $params[] = $category; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM expenses $whereSql ORDER BY expense_date DESC, id DESC");
$stmt->execute($params);
$expenses = $stmt->fetchAll();
$total = array_sum(array_column($expenses, 'amount'));

$pageTitle = 'Expenses';
$active = 'expenses';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Expenses</div>
    <div class="page-subtitle">Feeds the profit estimate on the dashboard.</div>
  </div>
  <a href="/expenses/add.php" class="btn btn-accent">+ Add expense</a>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
  <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
  <div class="col-auto">
    <select name="category" class="form-select form-select-sm">
      <option value="">All categories</option>
      <?php foreach (['rent', 'salary', 'electricity', 'other'] as $c): ?>
      <option value="<?= $c ?>" <?= $category === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
</form>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Category</th><th>Note</th><th class="text-end">Amount</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($expenses as $exp): ?>
      <tr>
        <td><?= e($exp['expense_date']) ?></td>
        <td><span class="badge text-bg-light border"><?= e(ucfirst($exp['category'])) ?></span></td>
        <td>
          <?= e($exp['note']) ?>
          <?php if (!empty($exp['receipt_path'])): ?>
            <a href="/<?= e($exp['receipt_path']) ?>" target="_blank" class="small ms-1">[Receipt]</a>
          <?php endif; ?>
        </td>
        <td class="text-end"><?= format_currency($exp['amount']) ?></td>
        <td class="text-end">
          <a href="/expenses/edit.php?id=<?= (int)$exp['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
          <form method="post" action="/expenses/delete.php" class="d-inline" onsubmit="return confirm('Delete this expense?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$exp['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$expenses): ?>
      <tr><td colspan="5" class="text-center text-muted py-4">No expenses recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
    <?php if ($expenses): ?>
    <tfoot><tr><td colspan="3" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold"><?= format_currency($total) ?></td><td></td></tr></tfoot>
    <?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
