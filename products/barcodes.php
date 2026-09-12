<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$products = $pdo->query('SELECT id, name, sku, barcode, sell_price_ref FROM products ORDER BY name')->fetchAll();
$settings = get_settings($pdo);

$pageTitle = 'Barcode Generator';
$active = 'products';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Barcode Generator</div>
    <div class="page-subtitle">Set a quantity for each product, then print labels for your existing sticker sheets.</div>
  </div>
</div>

<div class="card p-3 mb-3">
  <div class="fw-semibold mb-2">Label contents</div>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" id="show-shop" checked>
    <label class="form-check-label" for="show-shop">Shop name</label>
  </div>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" id="show-name" checked>
    <label class="form-check-label" for="show-name">Item name</label>
  </div>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" id="show-price" checked>
    <label class="form-check-label" for="show-price">Price</label>
  </div>
</div>

<div class="card mb-3">
  <table class="table mb-0">
    <thead><tr><th>Product</th><th>SKU</th><th>Barcode value</th><th style="width:120px;">Qty to print</th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): $code = $p['barcode'] ?: $p['sku']; ?>
      <tr>
        <td><?= e($p['name']) ?></td>
        <td><?= e($p['sku']) ?></td>
        <td><?= $code ? e($code) : '<span class="text-danger small">No SKU/barcode set</span>' ?></td>
        <td>
          <?php if ($code): ?>
          <input type="number" min="0" value="0" class="form-control form-control-sm qty-input"
                 data-name="<?= e($p['name']) ?>" data-code="<?= e($code) ?>" data-price="<?= (float)$p['sell_price_ref'] ?>">
          <?php else: ?>
          <input type="number" class="form-control form-control-sm" disabled placeholder="—">
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
      <tr><td colspan="4" class="text-center text-muted py-4">No products yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<button type="button" class="btn btn-accent" onclick="generateLabels()">Generate &amp; Print Labels</button>
<div id="label-count" class="small text-muted mt-2"></div>

<div id="print-area" style="display:none;"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/JsBarcode/3.11.5/JsBarcode.all.min.js"></script>
<style>
  @media print {
    body * { visibility: hidden; }
    #print-area, #print-area * { visibility: visible; }
    #print-area { display: block !important; position: absolute; top: 0; left: 0; }
  }
  .label-sheet { display: flex; flex-wrap: wrap; gap: 2mm; padding: 4mm; }
  .label { width: 50mm; height: 28mm; border: 1px dashed #ccc; padding: 1mm 2mm; box-sizing: border-box; text-align: center; overflow: hidden; font-family: Arial, sans-serif; }
  .label .shop-name { font-size: 8px; font-weight: bold; }
  .label .item-name { font-size: 8px; }
  .label .price { font-size: 9px; font-weight: bold; }
  .label svg { width: 100%; height: 14mm; }
</style>
<script>
const SHOP_NAME = <?= json_encode($settings['shop_name']) ?>;

document.querySelectorAll('.qty-input').forEach(el => {
  el.addEventListener('input', updateCount);
});

function updateCount() {
  let total = 0;
  document.querySelectorAll('.qty-input').forEach(el => total += parseInt(el.value) || 0);
  document.getElementById('label-count').textContent = total > 0 ? `${total} label(s) will be printed.` : '';
}

function generateLabels() {
  const showShop = document.getElementById('show-shop').checked;
  const showName = document.getElementById('show-name').checked;
  const showPrice = document.getElementById('show-price').checked;

  const area = document.getElementById('print-area');
  area.innerHTML = '';
  const sheet = document.createElement('div');
  sheet.className = 'label-sheet';

  let count = 0;
  let labelIndex = 0;
  document.querySelectorAll('.qty-input').forEach(el => {
    const qty = parseInt(el.value) || 0;
    if (qty <= 0) return;
    for (let i = 0; i < qty; i++) {
      const label = document.createElement('div');
      label.className = 'label';
      const svgId = `barcode-${labelIndex++}`;
      label.innerHTML = `
        ${showShop ? `<div class="shop-name">${SHOP_NAME}</div>` : ''}
        ${showName ? `<div class="item-name">${el.dataset.name}</div>` : ''}
        <svg id="${svgId}"></svg>
        ${showPrice ? `<div class="price">Rs. ${parseFloat(el.dataset.price).toFixed(2)}</div>` : ''}
      `;
      sheet.appendChild(label);
      count++;
    }
  });

  if (count === 0) {
    alert('Set a quantity greater than 0 for at least one product.');
    return;
  }

  area.appendChild(sheet);

  // Render barcodes after the elements are in the DOM.
  let idx = 0;
  document.querySelectorAll('.qty-input').forEach(el => {
    const qty = parseInt(el.value) || 0;
    for (let i = 0; i < qty; i++) {
      try {
        JsBarcode(`#barcode-${idx}`, el.dataset.code, { format: 'CODE128', displayValue: true, fontSize: 10, height: 30, margin: 0 });
      } catch (e) {
        document.getElementById(`barcode-${idx}`).outerHTML = `<div class="small text-danger">${el.dataset.code}</div>`;
      }
      idx++;
    }
  });

  setTimeout(() => window.print(), 200);
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
