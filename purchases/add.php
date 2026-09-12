<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/purchases.php';
require_login();

$suppliers = $pdo->query("SELECT id, name FROM parties WHERE type = 'supplier' ORDER BY name")->fetchAll();
$products = $pdo->query('SELECT id, name, sku, is_serialized, cost_price_ref FROM products ORDER BY name')->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (empty($_POST['party_id'])) {
            throw new InvalidArgumentException('Select a supplier.');
        }
        $items = [];
        foreach ($_POST['items'] ?? [] as $row) {
            if (empty($row['product_id']) || $row['qty'] === '') {
                continue;
            }
            $serials = array_filter(array_map('trim', explode("\n", $row['serials'] ?? '')), fn($s) => $s !== '');
            $items[] = [
                'product_id' => (int)$row['product_id'],
                'qty' => (int)$row['qty'],
                'cost_price' => (float)$row['cost_price'],
                'discount_percent' => (float)($row['discount_percent'] ?? 0),
                'serials' => array_values($serials),
            ];
        }

        $purchaseId = create_purchase($pdo, [
            'party_id' => (int)$_POST['party_id'],
            'bill_ref' => trim($_POST['bill_ref'] ?? ''),
            'purchase_date' => $_POST['purchase_date'] ?: date('Y-m-d'),
            'note' => trim($_POST['note'] ?? ''),
            'created_by' => current_user()['id'],
            'items' => $items,
        ]);

        flash_set('success', 'Purchase recorded and stock updated.');
        redirect('/purchases/view.php?id=' . $purchaseId);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'New Purchase';
$active = 'purchases';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">New Purchase</div></div>

<?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="purchase-form">
  <?= csrf_field() ?>
  <div class="card p-3 mb-3">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Supplier</label>
        <select name="party_id" class="form-select" required>
          <option value="">Select supplier...</option>
          <?php foreach ($suppliers as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!$suppliers): ?><div class="form-text text-danger"><a href="/parties/add.php?type=supplier">Add a supplier</a> first.</div><?php endif; ?>
      </div>
      <div class="col-md-3">
        <label class="form-label">Bill / Reference No.</label>
        <input type="text" name="bill_ref" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Note</label>
        <input type="text" name="note" class="form-control">
      </div>
    </div>
  </div>

  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="fw-semibold">Items</div>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addItemRow()">+ Add item</button>
    </div>
    <table class="table" id="items-table">
      <thead><tr><th style="width:22%">Product</th><th style="width:8%">Qty</th><th style="width:13%">Cost price</th><th style="width:9%">Disc. %</th><th>Serials / IMEIs (one per line, only for serialized items)</th><th style="width:12%" class="text-end">Line total</th><th></th></tr></thead>
      <tbody id="items-body"></tbody>
      <tfoot><tr><td colspan="5" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold" id="grand-total">Rs. 0.00</td><td></td></tr></tfoot>
    </table>
  </div>

  <button type="submit" class="btn btn-accent">Save purchase</button>
  <a href="/purchases/index.php" class="btn btn-link">Cancel</a>
</form>

<script>
const PRODUCTS = <?= json_encode(array_map(fn($p) => [
    'id' => (int)$p['id'], 'name' => $p['name'], 'sku' => $p['sku'],
    'serialized' => (bool)$p['is_serialized'], 'cost' => (float)$p['cost_price_ref'],
], $products)) ?>;

let rowIndex = 0;

function productOptions(selectedId) {
  let html = '<option value="">Select product...</option>';
  for (const p of PRODUCTS) {
    const label = p.sku ? `${p.name} (${p.sku})` : p.name;
    html += `<option value="${p.id}" data-serialized="${p.serialized ? 1 : 0}" data-cost="${p.cost}" ${p.id === selectedId ? 'selected' : ''}>${label}</option>`;
  }
  return html;
}

function addItemRow() {
  const i = rowIndex++;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="items[${i}][product_id]" class="form-select form-select-sm product-select" onchange="onProductChange(this)">${productOptions(null)}</select></td>
    <td><input type="number" min="1" value="1" name="items[${i}][qty]" class="form-control form-control-sm qty-input" oninput="recalc(this)"></td>
    <td><input type="number" step="0.01" min="0" value="0" name="items[${i}][cost_price]" class="form-control form-control-sm cost-input" oninput="recalc(this)"></td>
    <td><input type="number" step="0.01" min="0" max="100" value="0" name="items[${i}][discount_percent]" class="form-control form-control-sm discount-input" oninput="recalc(this)"></td>
    <td><textarea name="items[${i}][serials]" class="form-control form-control-sm serials-input" rows="1" placeholder="Only for serialized items" disabled></textarea></td>
    <td class="text-end line-total align-middle">Rs. 0.00</td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalcTotal();">&times;</button></td>
  `;
  document.getElementById('items-body').appendChild(tr);
}

function onProductChange(select) {
  const opt = select.options[select.selectedIndex];
  const tr = select.closest('tr');
  const serialized = opt.dataset.serialized === '1';
  const serialsField = tr.querySelector('.serials-input');
  serialsField.disabled = !serialized;
  serialsField.placeholder = serialized ? 'One serial/IMEI per line, must match quantity' : 'Only for serialized items';
  if (opt.dataset.cost) {
    tr.querySelector('.cost-input').value = opt.dataset.cost;
  }
  recalc(select);
}

function recalc(el) {
  const tr = el.closest('tr');
  const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
  const cost = parseFloat(tr.querySelector('.cost-input').value) || 0;
  const discount = parseFloat(tr.querySelector('.discount-input').value) || 0;
  const lineTotal = qty * cost * (1 - discount / 100);
  tr.querySelector('.line-total').textContent = 'Rs. ' + lineTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  recalcTotal();
}

function recalcTotal() {
  let total = 0;
  document.querySelectorAll('#items-body tr').forEach(tr => {
    const qty = parseFloat(tr.querySelector('.qty-input')?.value) || 0;
    const cost = parseFloat(tr.querySelector('.cost-input')?.value) || 0;
    const discount = parseFloat(tr.querySelector('.discount-input')?.value) || 0;
    total += qty * cost * (1 - discount / 100);
  });
  document.getElementById('grand-total').textContent = 'Rs. ' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

addItemRow();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
