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
if ($from) { $where[] = 'income_date >= ?'; $params[] = $from; }
if ($to) { $where[] = 'income_date <= ?'; $params[] = $to; }
if ($category) { $where[] = 'category = ?'; $params[] = $category; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM other_income $whereSql ORDER BY income_date DESC, id DESC");
$stmt->execute($params);
$incomes = $stmt->fetchAll();
$total = array_sum(array_column($incomes, 'amount'));

$categories = $pdo->query(
    "SELECT DISTINCT category FROM other_income WHERE category IS NOT NULL AND category <> '' ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Other Income';
$active = 'income';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Other Income</div>
    <div class="page-subtitle">Money coming in that isn't a sale — commission, rent, refunds, etc.</div>
  </div>
  <a href="/income/add.php" class="btn btn-accent">+ Add income</a>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
  <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
  <div class="col-auto">
    <select name="category" class="form-select form-select-sm">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e(ucfirst($c)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
</form>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Category</th><th>Note</th><th class="text-end">Amount</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($incomes as $inc): ?>
      <tr>
        <td><?= e($inc['income_date']) ?></td>
        <td><span class="badge text-bg-light border"><?= e(ucfirst($inc['category'])) ?></span></td>
        <td>
          <?= e($inc['note']) ?>
          <?php if (!empty($inc['receipt_path'])): ?>
            <a href="/<?= e($inc['receipt_path']) ?>" target="_blank" class="small ms-1">[Receipt]</a>
          <?php endif; ?>
        </td>
        <td class="text-end"><?= format_currency($inc['amount']) ?></td>
        <td class="text-end">
          <a href="/income/edit.php?id=<?= (int)$inc['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
          <form method="post" action="/income/delete.php" class="d-inline" onsubmit="return confirm('Delete this income entry?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$inc['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$incomes): ?>
      <tr><td colspan="5" class="text-center text-muted py-4">No income entries recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
    <?php if ($incomes): ?>
    <tfoot><tr><td colspan="3" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold"><?= format_currency($total) ?></td><td></td></tr></tfoot>
    <?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
