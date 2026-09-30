<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/purchases.php';
require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM purchases WHERE id = ?');
$stmt->execute([$id]);
$purchase = $stmt->fetch();
if (!$purchase) {
    flash_set('error', 'Purchase not found.');
    redirect('/purchases/index.php');
}

$blockers = can_modify_purchase($pdo, $id);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blockers) {
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

        update_purchase($pdo, $id, [
            'party_id' => (int)$_POST['party_id'],
            'bill_ref' => trim($_POST['bill_ref'] ?? ''),
            'purchase_date' => $_POST['purchase_date'] ?: date('Y-m-d'),
            'note' => trim($_POST['note'] ?? ''),
            'items' => $items,
        ]);

        flash_set('success', 'Purchase updated.');
        redirect('/purchases/view.php?id=' . $id);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$suppliers = $pdo->query("SELECT id, name FROM parties WHERE type = 'supplier' ORDER BY name")->fetchAll();
$products = $pdo->query('SELECT id, name, sku, barcode, photo_path, is_serialized, cost_price_ref FROM products ORDER BY name')->fetchAll();

// Group this purchase's item rows by product into one form row each (matching
// how they're entered: one row per product, serials listed together).
$stmt = $pdo->prepare('SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY id');
$stmt->execute([$id]);
$existingRows = [];
foreach ($stmt->fetchAll() as $it) {
    $pid = (int)$it['product_id'];
    if (!isset($existingRows[$pid])) {
        $existingRows[$pid] = [
            'product_id' => $pid, 'qty' => 0, 'cost_price' => (float)$it['cost_price'],
            'discount_percent' => (float)$it['discount_percent'], 'serials' => [],
        ];
    }
    $existingRows[$pid]['qty'] += (int)$it['qty'];
    if ($it['serial_no']) {
        $existingRows[$pid]['serials'][] = $it['serial_no'];
    }
}
$existingRows = array_values($existingRows);

$pageTitle = 'Edit Purchase';
$active = 'purchases';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Edit Purchase #<?= $id ?></div></div>

<?php if ($blockers): ?>
<div class="alert alert-warning">
  <div class="fw-semibold mb-1">This purchase can no longer be edited or deleted.</div>
  <ul class="mb-0">
    <?php foreach ($blockers as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
  </ul>
</div>
<a href="/purchases/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Back to purchase</a>
<?php else: ?>

<?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="purchase-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="card p-3 mb-3">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Supplier</label>
        <select name="party_id" class="form-select" required>
          <option value="">Select supplier...</option>
          <?php foreach ($suppliers as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === (int)$purchase['party_id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Bill / Reference No.</label>
        <input type="text" name="bill_ref" class="form-control" value="<?= e($purchase['bill_ref']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="purchase_date" class="form-control" value="<?= e($purchase['purchase_date']) ?>" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Note</label>
        <input type="text" name="note" class="form-control" value="<?= e($purchase['note']) ?>">
      </div>
    </div>
  </div>

  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="fw-semibold">Items</div>
      <div>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openScanner()">📷 Scan barcode</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addItemRow()">+ Add item</button>
      </div>
    </div>
    <table class="table" id="items-table">
      <thead><tr><th style="width:22%">Product</th><th style="width:8%">Qty</th><th style="width:13%">Cost price</th><th style="width:9%">Disc. %</th><th>Serials / IMEIs (one per line, only for serialized items)</th><th style="width:12%" class="text-end">Line total</th><th></th></tr></thead>
      <tbody id="items-body"></tbody>
      <tfoot><tr><td colspan="5" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold" id="grand-total">Rs. 0.00</td><td></td></tr></tfoot>
    </table>
  </div>

  <button type="submit" class="btn btn-accent">Save changes</button>
  <a href="/purchases/view.php?id=<?= $id ?>" class="btn btn-link">Cancel</a>
</form>

<style>
  .scan-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:2000; display:flex; align-items:center; justify-content:center; padding:16px; }
  .scan-box{ background:#fff; border-radius:8px; padding:16px; max-width:420px; width:100%; }
  #scan-reader{ width:100%; }
  #scan-reader video{ width:100%; border-radius:4px; }

  .product-picker{ position:relative; }
  .product-search-results{ display:none; position:absolute; z-index:50; top:100%; left:0; right:0; background:#fff; border:1px solid #ddd; border-radius:4px; max-height:240px; overflow-y:auto; box-shadow:0 4px 10px rgba(0,0,0,.1); }
  .product-search-results.show{ display:block; }
  .product-result{ display:flex; align-items:center; gap:.5rem; padding:.4rem .6rem; cursor:pointer; font-size:.85rem; }
  .product-result:hover, .product-result.active{ background:#f2f2ee; }
  .product-result img, .product-result .ph{ width:28px; height:28px; object-fit:cover; border-radius:3px; border:1px solid #ddd; flex-shrink:0; background:#f2f2ee; }
</style>
<div class="scan-overlay" id="scan-overlay" style="display:none;">
  <div class="scan-box">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="fw-semibold">Scan a barcode</div>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeScanner()">Done</button>
    </div>
    <div id="scan-reader"></div>
    <div id="scan-status" class="small text-muted mt-2">Point the camera at a barcode.</div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
const PRODUCTS = <?= json_encode(array_map(fn($p) => [
    'id' => (int)$p['id'], 'name' => $p['name'], 'sku' => $p['sku'], 'barcode' => $p['barcode'], 'photo' => $p['photo_path'],
    'serialized' => (bool)$p['is_serialized'], 'cost' => (float)$p['cost_price_ref'],
], $products)) ?>;
const EXISTING_ROWS = <?= json_encode($existingRows) ?>;

let rowIndex = 0;

function addItemRow(prefill) {
  const i = rowIndex++;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <div class="product-picker">
        <input type="text" class="form-control form-control-sm product-search-input" placeholder="Search product..." autocomplete="off"
               oninput="onProductSearchInput(this)" onfocus="onProductSearchInput(this)"
               onblur="setTimeout(() => hideProductResults(this), 150)" onkeydown="onProductSearchKeydown(event, this)">
        <input type="hidden" class="product-select" name="items[${i}][product_id]" onchange="onProductChange(this)">
        <div class="product-search-results"></div>
      </div>
    </td>
    <td><input type="number" min="1" value="${prefill ? prefill.qty : 1}" name="items[${i}][qty]" class="form-control form-control-sm qty-input" oninput="recalc(this)"></td>
    <td><input type="number" step="0.01" min="0" value="${prefill ? prefill.cost_price : 0}" name="items[${i}][cost_price]" class="form-control form-control-sm cost-input" oninput="recalc(this)"></td>
    <td><input type="number" step="0.01" min="0" max="100" value="${prefill ? prefill.discount_percent : 0}" name="items[${i}][discount_percent]" class="form-control form-control-sm discount-input" oninput="recalc(this)"></td>
    <td><textarea name="items[${i}][serials]" class="form-control form-control-sm serials-input" rows="1" placeholder="Only for serialized items" disabled>${prefill ? prefill.serials.join('\n') : ''}</textarea></td>
    <td class="text-end line-total align-middle">Rs. 0.00</td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalcTotal();">&times;</button></td>
  `;
  document.getElementById('items-body').appendChild(tr);

  if (prefill) {
    // Wire up the picked product without letting onProductChange overwrite
    // the recorded cost price with the product's current reference cost.
    const hidden = tr.querySelector('.product-select');
    const product = PRODUCTS.find(p => p.id === prefill.product_id);
    hidden.value = prefill.product_id;
    tr.querySelector('.product-search-input').value = product ? `${product.name}${product.sku ? ' (' + product.sku + ')' : ''}` : '';
    const serialsField = tr.querySelector('.serials-input');
    serialsField.disabled = !(product && product.serialized);
    serialsField.placeholder = (product && product.serialized) ? 'One serial/IMEI per line, must match quantity' : 'Only for serialized items';
    recalc(hidden);
  }
}

function productSearchMatches(query) {
  const q = query.trim().toLowerCase();
  if (q === '') return PRODUCTS.slice(0, 8);
  return PRODUCTS.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.sku && p.sku.toLowerCase().includes(q)) ||
    (p.barcode && p.barcode.toLowerCase().includes(q))
  ).slice(0, 8);
}

function onProductSearchInput(input) {
  const box = input.parentElement.querySelector('.product-search-results');
  const matches = productSearchMatches(input.value);
  if (matches.length === 0) {
    box.innerHTML = '<div class="product-result text-muted">No matching product</div>';
  } else {
    box.innerHTML = matches.map(p => `
      <div class="product-result" data-id="${p.id}">
        ${p.photo ? `<img src="/${p.photo}">` : '<div class="ph"></div>'}
        <div>${p.name}${p.sku ? ' <span class="text-muted small">(' + p.sku + ')</span>' : ''}</div>
      </div>
    `).join('');
    box.querySelectorAll('.product-result[data-id]').forEach(el => {
      el.addEventListener('mousedown', e => { e.preventDefault(); selectProduct(input, parseInt(el.dataset.id)); });
    });
  }
  box.classList.add('show');
}

function hideProductResults(input) {
  input.parentElement.querySelector('.product-search-results').classList.remove('show');
}

function onProductSearchKeydown(e, input) {
  const box = input.parentElement.querySelector('.product-search-results');
  const items = Array.from(box.querySelectorAll('.product-result[data-id]'));
  if (!items.length) return;
  let idx = items.findIndex(el => el.classList.contains('active'));
  if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(idx + 1, items.length - 1); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(idx - 1, 0); }
  else if (e.key === 'Enter') { e.preventDefault(); if (idx >= 0) selectProduct(input, parseInt(items[idx].dataset.id)); return; }
  else return;
  items.forEach(el => el.classList.remove('active'));
  items[idx].classList.add('active');
  items[idx].scrollIntoView({ block: 'nearest' });
}

function selectProduct(searchInput, productId) {
  const hidden = searchInput.closest('.product-picker').querySelector('.product-select');
  hidden.value = productId;
  hideProductResults(searchInput);
  onProductChange(hidden);
}

function onProductChange(select) {
  const tr = select.closest('tr');
  const product = PRODUCTS.find(p => p.id === parseInt(select.value));
  const searchInput = select.closest('.product-picker').querySelector('.product-search-input');
  searchInput.value = product ? `${product.name}${product.sku ? ' (' + product.sku + ')' : ''}` : '';
  const serialsField = tr.querySelector('.serials-input');
  const serialized = product ? product.serialized : false;
  serialsField.disabled = !serialized;
  serialsField.placeholder = serialized ? 'One serial/IMEI per line, must match quantity' : 'Only for serialized items';
  if (product) {
    tr.querySelector('.cost-input').value = product.cost;
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

let html5QrCode = null;
let lastScan = { code: null, time: 0 };

function openScanner() {
  document.getElementById('scan-overlay').style.display = 'flex';
  const status = document.getElementById('scan-status');
  status.textContent = 'Starting camera...';
  status.className = 'small text-muted mt-2';
  html5QrCode = new Html5Qrcode('scan-reader');
  html5QrCode.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 250, height: 150 } },
    onScanSuccess,
    () => {}
  ).catch(err => {
    status.textContent = 'Could not start the camera: ' + err;
    status.className = 'small text-danger mt-2';
  });
}

function closeScanner() {
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
    html5QrCode = null;
  }
  document.getElementById('scan-overlay').style.display = 'none';
}

function onScanSuccess(decodedText) {
  const now = Date.now();
  if (decodedText === lastScan.code && now - lastScan.time < 2000) return;
  lastScan = { code: decodedText, time: now };

  const status = document.getElementById('scan-status');
  const product = PRODUCTS.find(p => (p.barcode && p.barcode === decodedText) || (p.sku && p.sku === decodedText));
  if (!product) {
    status.textContent = `No product matches "${decodedText}". Try again.`;
    status.className = 'small text-danger mt-2';
    return;
  }

  let matched = false;
  document.querySelectorAll('#items-body tr').forEach(tr => {
    const select = tr.querySelector('.product-select');
    if (matched || parseInt(select.value) !== product.id) return;
    const qtyInput = tr.querySelector('.qty-input');
    qtyInput.value = (parseInt(qtyInput.value) || 0) + 1;
    recalc(qtyInput);
    matched = true;
  });

  if (!matched) {
    addItemRow();
    const rows = document.querySelectorAll('#items-body tr');
    const tr = rows[rows.length - 1];
    const select = tr.querySelector('.product-select');
    select.value = product.id;
    onProductChange(select);
  }

  status.textContent = `Added: ${product.name}`;
  status.className = 'small text-success mt-2';
}

if (EXISTING_ROWS.length > 0) {
  EXISTING_ROWS.forEach(row => addItemRow(row));
} else {
  addItemRow();
}
recalcTotal();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
