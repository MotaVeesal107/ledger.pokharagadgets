<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$supplierId = (int)($_GET['supplier_id'] ?? 0);

$where = [];
$params = [];
if ($from) { $where[] = 'pu.purchase_date >= ?'; $params[] = $from; }
if ($to) { $where[] = 'pu.purchase_date <= ?'; $params[] = $to; }
if ($supplierId) { $where[] = 'pu.party_id = ?'; $params[] = $supplierId; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT pu.*, pa.name AS supplier_name FROM purchases pu
     JOIN parties pa ON pa.id = pu.party_id
     $whereSql ORDER BY pu.purchase_date DESC, pu.id DESC"
);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

$suppliers = $pdo->query("SELECT id, name FROM parties WHERE type = 'supplier' ORDER BY name")->fetchAll();

$pageTitle = 'Purchases';
$active = 'purchases';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Purchases</div>
    <div class="page-subtitle">Stock-in from suppliers.</div>
  </div>
  <a href="/purchases/add.php" class="btn btn-accent">+ New purchase</a>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
  <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
  <div class="col-auto">
    <select name="supplier_id" class="form-select form-select-sm">
      <option value="">All suppliers</option>
      <?php foreach ($suppliers as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
</form>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Supplier</th><th>Bill Ref</th><th class="text-end">Total</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($purchases as $p): ?>
      <tr>
        <td><?= e($p['purchase_date']) ?></td>
        <td><?= e($p['supplier_name']) ?></td>
        <td><?= e($p['bill_ref']) ?></td>
        <td class="text-end"><?= format_currency($p['total']) ?></td>
        <td class="text-end"><a href="/purchases/view.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$purchases): ?>
      <tr><td colspan="5" class="text-center text-muted py-4">No purchases recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
