<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sales.php';
require_once __DIR__ . '/../includes/stock.php';
require_login();

$customers = $pdo->query("SELECT id, name FROM parties WHERE type = 'customer' ORDER BY name")->fetchAll();
$products = $pdo->query('SELECT id, name, sku, is_serialized, sell_price_ref FROM products ORDER BY name')->fetchAll();
$settings = get_settings($pdo);

foreach ($products as &$p) {
    $p['available_stock'] = get_stock($pdo, $p['id']);
    $p['serials'] = $p['is_serialized'] ? array_column(get_available_serials($pdo, $p['id']), 'serial_no') : [];
}
unset($p);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $items = [];
        foreach ($_POST['items'] ?? [] as $row) {
            if (empty($row['product_id']) || $row['qty'] === '') {
                continue;
            }
            $items[] = [
                'product_id' => (int)$row['product_id'],
                'qty' => (int)$row['qty'],
                'sell_price' => (float)$row['sell_price'],
                'discount_percent' => (float)($row['discount_percent'] ?? 0),
                'serials' => array_values(array_filter($row['serials'] ?? [], fn($s) => trim($s) !== '')),
            ];
        }
        $payments = [];
        foreach ($_POST['payments'] ?? [] as $row) {
            if ($row['amount'] === '' || (float)$row['amount'] <= 0) {
                continue;
            }
            $payments[] = ['method' => $row['method'], 'amount' => (float)$row['amount']];
        }

        $sale = create_sale($pdo, [
            'party_id' => (int)($_POST['party_id'] ?? 0) ?: null,
            'walkin_name' => trim($_POST['walkin_name'] ?? ''),
            'sale_date' => $_POST['sale_date'] ?: date('Y-m-d'),
            'note' => trim($_POST['note'] ?? ''),
            'created_by' => current_user()['id'],
            'items' => $items,
            'payments' => $payments,
            'vat_enabled' => (bool)$settings['vat_enabled'],
            'vat_rate' => (float)$settings['vat_rate'],
        ]);

        flash_set('success', "Sale {$sale['invoice_no']} recorded.");
        redirect('/sales/view.php?id=' . $sale['id']);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'New Sale';
$active = 'sales';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">New Sale</div></div>

<?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="sale-form">
  <?= csrf_field() ?>
  <div class="card p-3 mb-3">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Customer</label>
        <select name="party_id" class="form-select" id="party-select">
          <option value="">Walk-in (no account)</option>
          <?php foreach ($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4" id="walkin-name-wrap">
        <label class="form-label">Walk-in name (optional)</label>
        <input type="text" name="walkin_name" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Date</label>
        <input type="date" name="sale_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
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
      <thead><tr><th style="width:22%">Product</th><th style="width:8%">Qty</th><th style="width:12%">Sell price</th><th style="width:9%">Disc. %</th><th>Serials (select exactly Qty)</th><th style="width:12%" class="text-end">Line total</th><th></th></tr></thead>
      <tbody id="items-body"></tbody>
      <tfoot>
        <tr><td colspan="5" class="text-end">Subtotal</td><td class="text-end" id="subtotal">Rs. 0.00</td><td></td></tr>
        <?php if ($settings['vat_enabled']): ?>
        <tr><td colspan="5" class="text-end">VAT (<?= e($settings['vat_rate']) ?>%)</td><td class="text-end" id="vat-amount">Rs. 0.00</td><td></td></tr>
        <?php endif; ?>
        <tr><td colspan="5" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold" id="grand-total">Rs. 0.00</td><td></td></tr>
      </tfoot>
    </table>
  </div>

  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="fw-semibold">Payment (split across methods if needed)</div>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addPaymentRow()">+ Add payment</button>
    </div>
    <table class="table mb-1" id="payments-table">
      <thead><tr><th style="width:30%">Method</th><th style="width:20%">Amount</th><th></th></tr></thead>
      <tbody id="payments-body"></tbody>
    </table>
    <div id="payment-check" class="small"></div>
  </div>

  <button type="submit" class="btn btn-accent">Save sale</button>
  <a href="/sales/index.php" class="btn btn-link">Cancel</a>
</form>

<script>
const PRODUCTS = <?= json_encode(array_map(fn($p) => [
    'id' => (int)$p['id'], 'name' => $p['name'], 'sku' => $p['sku'],
    'serialized' => (bool)$p['is_serialized'], 'price' => (float)$p['sell_price_ref'],
    'stock' => (int)$p['available_stock'], 'serials' => $p['serials'],
], $products)) ?>;
const VAT_ENABLED = <?= $settings['vat_enabled'] ? 'true' : 'false' ?>;
const VAT_RATE = <?= (float)$settings['vat_rate'] ?>;

let itemIndex = 0, paymentIndex = 0;

document.getElementById('party-select').addEventListener('change', function () {
  document.getElementById('walkin-name-wrap').style.display = this.value ? 'none' : '';
});

function productOptions(selectedId) {
  let html = '<option value="">Select product...</option>';
  for (const p of PRODUCTS) {
    const label = `${p.name}${p.sku ? ' (' + p.sku + ')' : ''} — ${p.stock} in stock`;
    html += `<option value="${p.id}" ${p.id === selectedId ? 'selected' : ''}>${label}</option>`;
  }
  return html;
}

function addItemRow() {
  const i = itemIndex++;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="items[${i}][product_id]" class="form-select form-select-sm product-select" onchange="onProductChange(this)">${productOptions(null)}</select></td>
    <td><input type="number" min="1" value="1" name="items[${i}][qty]" class="form-control form-control-sm qty-input" oninput="onQtyChange(this)"></td>
    <td><input type="number" step="0.01" min="0" value="0" name="items[${i}][sell_price]" class="form-control form-control-sm price-input" oninput="recalc()"></td>
    <td><input type="number" step="0.01" min="0" max="100" value="0" name="items[${i}][discount_percent]" class="form-control form-control-sm discount-input" oninput="recalc()"></td>
    <td class="serials-cell text-muted small">Select a product</td>
    <td class="text-end line-total align-middle">Rs. 0.00</td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalc();">&times;</button></td>
  `;
  document.getElementById('items-body').appendChild(tr);
}

function onProductChange(select) {
  const tr = select.closest('tr');
  const product = PRODUCTS.find(p => p.id === parseInt(select.value));
  tr.querySelector('.price-input').value = product ? product.price : 0;
  renderSerials(tr, product);
  recalc();
}

function onQtyChange(input) {
  const tr = input.closest('tr');
  const select = tr.querySelector('.product-select');
  const product = PRODUCTS.find(p => p.id === parseInt(select.value));
  renderSerials(tr, product);
  recalc();
}

function renderSerials(tr, product) {
  const cell = tr.querySelector('.serials-cell');
  const nameAttr = tr.querySelector('.product-select').getAttribute('name').replace('[product_id]', '[serials][]');
  if (!product) {
    cell.className = 'serials-cell text-muted small';
    cell.textContent = 'Select a product';
    return;
  }
  if (!product.serialized) {
    cell.className = 'serials-cell text-muted small';
    cell.textContent = '—';
    return;
  }
  const qty = parseInt(tr.querySelector('.qty-input').value) || 0;
  if (product.serials.length === 0) {
    cell.className = 'serials-cell text-danger small';
    cell.textContent = 'No serials currently in stock for this product.';
    return;
  }
  let html = `<div class="small mb-1">Select exactly ${qty} serial(s), ${product.serials.length} available</div><div style="max-height:100px;overflow:auto;">`;
  for (const s of product.serials) {
    html += `<label class="d-block"><input type="checkbox" name="${nameAttr}" value="${s}" onchange="recalc()"> ${s}</label>`;
  }
  html += '</div>';
  cell.className = 'serials-cell';
  cell.innerHTML = html;
}

function fmt(n) {
  return 'Rs. ' + n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function recalc() {
  let subtotal = 0;
  document.querySelectorAll('#items-body tr').forEach(tr => {
    const qty = parseFloat(tr.querySelector('.qty-input')?.value) || 0;
    const price = parseFloat(tr.querySelector('.price-input')?.value) || 0;
    const discount = parseFloat(tr.querySelector('.discount-input')?.value) || 0;
    const lineTotal = qty * price * (1 - discount / 100);
    tr.querySelector('.line-total').textContent = fmt(lineTotal);
    subtotal += lineTotal;
  });
  const vat = VAT_ENABLED ? subtotal * (VAT_RATE / 100) : 0;
  const total = subtotal + vat;
  document.getElementById('subtotal').textContent = fmt(subtotal);
  const vatEl = document.getElementById('vat-amount');
  if (vatEl) vatEl.textContent = fmt(vat);
  document.getElementById('grand-total').textContent = fmt(total);
  checkPayments(total);
}

function addPaymentRow() {
  const i = paymentIndex++;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select name="payments[${i}][method]" class="form-select form-select-sm">
        <option value="cash">Cash</option>
        <option value="esewa">eSewa QR</option>
        <option value="khalti">Khalti QR</option>
        <option value="fonepay">Fonepay</option>
        <option value="bank">Bank Transfer</option>
        <option value="due">Credit / Due</option>
      </select>
    </td>
    <td><input type="number" step="0.01" min="0" value="0" name="payments[${i}][amount]" class="form-control form-control-sm payment-amount" oninput="recalc()"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalc();">&times;</button></td>
  `;
  document.getElementById('payments-body').appendChild(tr);
}

function checkPayments(total) {
  let sum = 0;
  document.querySelectorAll('.payment-amount').forEach(el => sum += parseFloat(el.value) || 0);
  const el = document.getElementById('payment-check');
  const diff = Math.round((sum - total) * 100) / 100;
  if (diff === 0 && total > 0) {
    el.className = 'small text-success';
    el.textContent = `Payments match the total (${fmt(sum)}).`;
  } else {
    el.className = 'small text-danger';
    el.textContent = `Payments (${fmt(sum)}) must add up to the total (${fmt(total)}).`;
  }
}

addItemRow();
addPaymentRow();
recalc();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
