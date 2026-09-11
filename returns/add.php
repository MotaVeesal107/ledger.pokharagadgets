<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/returns.php';
require_login();

$products = $pdo->query('SELECT id, name, sku, is_serialized FROM products ORDER BY name')->fetchAll();
$recentSales = $pdo->query(
    "SELECT s.id, s.invoice_no, COALESCE(pa.name, s.walkin_name, 'Walk-in') AS customer_name
     FROM sales s LEFT JOIN parties pa ON pa.id = s.party_id
     ORDER BY s.id DESC LIMIT 200"
)->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        create_return_adjustment($pdo, [
            'product_id' => (int)($_POST['product_id'] ?? 0),
            'type' => $_POST['type'] ?? '',
            'qty' => (int)($_POST['qty'] ?? 0),
            'serial_no' => trim($_POST['serial_no'] ?? ''),
            'related_sale_id' => (int)($_POST['related_sale_id'] ?? 0) ?: null,
            'restock' => isset($_POST['restock']),
            'adjustment_date' => $_POST['adjustment_date'] ?: date('Y-m-d'),
            'note' => trim($_POST['note'] ?? ''),
            'created_by' => current_user()['id'],
        ]);
        flash_set('success', 'Entry recorded and stock updated.');
        redirect('/returns/index.php');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'New Return / Adjustment';
$active = 'returns';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">New Return / Adjustment</div></div>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

<div class="card p-4" style="max-width:600px;">
  <form method="post" id="ra-form">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Type</label>
      <select name="type" id="type-select" class="form-select" onchange="onTypeChange()">
        <option value="customer_return">Customer return</option>
        <option value="damaged">Damaged (write-off, not from a sale)</option>
        <option value="lost">Lost (write-off, not from a sale)</option>
      </select>
    </div>

    <div class="mb-3" id="sale-wrap">
      <label class="form-label">Related sale</label>
      <select name="related_sale_id" class="form-select">
        <option value="">Select the original sale...</option>
        <?php foreach ($recentSales as $s): ?>
        <option value="<?= (int)$s['id'] ?>"><?= e($s['invoice_no']) ?> — <?= e($s['customer_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Product</label>
      <select name="product_id" id="product-select" class="form-select" onchange="onProductChange()" required>
        <option value="">Select product...</option>
        <?php foreach ($products as $p): ?>
        <option value="<?= (int)$p['id'] ?>" data-serialized="<?= $p['is_serialized'] ? 1 : 0 ?>"><?= e($p['name']) ?> <?= $p['sku'] ? '(' . e($p['sku']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Quantity</label>
        <input type="number" name="qty" id="qty-input" class="form-control" min="1" value="1" required>
      </div>
      <div class="col-md-6 mb-3" id="serial-wrap" style="display:none;">
        <label class="form-label">Serial / IMEI</label>
        <input type="text" name="serial_no" class="form-control">
      </div>
    </div>

    <div class="form-check mb-3" id="restock-wrap">
      <input type="checkbox" class="form-check-input" id="restock" name="restock" checked>
      <label class="form-check-label" for="restock">Restock (item is sellable again). Uncheck if it came back damaged.</label>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Date</label>
        <input type="date" name="adjustment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Note</label>
        <input type="text" name="note" class="form-control">
      </div>
    </div>

    <button class="btn btn-accent">Save</button>
    <a href="/returns/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<script>
function onTypeChange() {
  const type = document.getElementById('type-select').value;
  document.getElementById('sale-wrap').style.display = type === 'customer_return' ? '' : 'none';
  document.getElementById('restock-wrap').style.display = type === 'customer_return' ? '' : 'none';
}
function onProductChange() {
  const opt = document.getElementById('product-select').selectedOptions[0];
  const serialized = opt && opt.dataset.serialized === '1';
  document.getElementById('serial-wrap').style.display = serialized ? '' : 'none';
  if (serialized) {
    document.getElementById('qty-input').value = 1;
    document.getElementById('qty-input').readOnly = true;
  } else {
    document.getElementById('qty-input').readOnly = false;
  }
}
onTypeChange();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
