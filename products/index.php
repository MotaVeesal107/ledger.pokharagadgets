<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stock.php';
require_login();

$search = trim($_GET['q'] ?? '');
$deviceFilter = trim($_GET['device_model'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$brandFilter = trim($_GET['brand'] ?? '');
$colorFilter = trim($_GET['color'] ?? '');

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.category LIKE ? OR p.brand LIKE ? OR p.device_model LIKE ? OR p.color LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
if ($deviceFilter !== '') {
    $conditions[] = 'p.device_model = ?';
    $params[] = $deviceFilter;
}
if ($categoryFilter !== '') {
    $conditions[] = 'p.category = ?';
    $params[] = $categoryFilter;
}
if ($brandFilter !== '') {
    $conditions[] = 'p.brand = ?';
    $params[] = $brandFilter;
}
if ($colorFilter !== '') {
    $conditions[] = 'p.color = ?';
    $params[] = $colorFilter;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$products = get_products_with_stock($pdo, $where, $params);
$showCost = can_view_cost($pdo);
$deviceModels = $pdo->query(
    "SELECT DISTINCT device_model FROM products WHERE device_model IS NOT NULL AND device_model <> '' ORDER BY device_model"
)->fetchAll(PDO::FETCH_COLUMN);
$brands = $pdo->query(
    "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand"
)->fetchAll(PDO::FETCH_COLUMN);
$colors = $pdo->query(
    "SELECT DISTINCT color FROM products WHERE color IS NOT NULL AND color <> '' ORDER BY color"
)->fetchAll(PDO::FETCH_COLUMN);
$categories = $pdo->query(
    "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Products';
$active = 'products';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Products <span class="badge text-bg-light"><?= count($products) ?></span></div>
    <div class="page-subtitle">Stock is always calculated live from purchases, sales and adjustments.</div>
  </div>
  <div>
    <a href="/brands/index.php" class="btn btn-outline-secondary">Brands</a>
    <a href="/models/index.php" class="btn btn-outline-secondary">Models</a>
    <a href="/categories/index.php" class="btn btn-outline-secondary">Categories</a>
    <a href="/products/barcodes.php" class="btn btn-outline-secondary">Barcode Generator</a>
    <a href="/products/import.php" class="btn btn-outline-secondary">Import CSV</a>
    <a href="/products/add.php" class="btn btn-accent">+ Add product</a>
  </div>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto" style="min-width:260px;">
    <input type="text" name="q" class="form-control" placeholder="Search name, SKU, barcode, category, brand, device model" value="<?= e($search) ?>">
  </div>
  <div class="col-auto">
    <select name="category" class="form-select" onchange="this.form.submit()">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= e($c) ?>" <?= $categoryFilter === $c ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="brand" class="form-select" onchange="this.form.submit()">
      <option value="">All brands</option>
      <?php foreach ($brands as $b): ?>
      <option value="<?= e($b) ?>" <?= $brandFilter === $b ? 'selected' : '' ?>><?= e($b) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="device_model" class="form-select" onchange="this.form.submit()">
      <option value="">All device models</option>
      <?php foreach ($deviceModels as $dm): ?>
      <option value="<?= e($dm) ?>" <?= $deviceFilter === $dm ? 'selected' : '' ?>><?= e($dm) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="color" class="form-select" onchange="this.form.submit()">
      <option value="">All colors</option>
      <?php foreach ($colors as $c): ?>
      <option value="<?= e($c) ?>" <?= $colorFilter === $c ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
</form>

<div class="card">
  <table class="table mb-0">
    <thead>
      <tr>
        <th></th><th>Product</th><th>Category</th><th>Brand</th><th>Device Model</th><th>Color</th><th>SKU</th><th>Stock</th><th>Unit</th>
        <?php if ($showCost): ?><th>Cost</th><?php endif; ?>
        <th>Sell Price</th><th>Reorder</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): $stock = (int)$p['stock']; ?>
      <tr>
        <td>
          <?php if (!empty($p['photo_path'])): ?>
            <img src="/<?= e($p['photo_path']) ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #ddd;">
          <?php else: ?>
            <div class="text-muted small" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:#f2f2ee;border-radius:4px;">—</div>
          <?php endif; ?>
        </td>
        <td>
          <?= e($p['name']) ?>
          <?php if ($p['is_serialized']): ?><span class="badge text-bg-light border">serialized</span><?php endif; ?>
        </td>
        <td><?= e($p['category']) ?></td>
        <td><?= e($p['brand']) ?></td>
        <td><?= e($p['device_model']) ?></td>
        <td><?= e($p['color']) ?></td>
        <td><?= e($p['sku']) ?></td>
        <td><?= $stock ?> <?= e($p['unit']) ?></td>
        <td><?= e($p['unit']) ?></td>
        <?php if ($showCost): ?><td><?= format_currency($p['cost_price_ref']) ?></td><?php endif; ?>
        <td><?= format_currency($p['sell_price_ref']) ?></td>
        <td><?= (int)$p['low_stock_threshold'] ?></td>
        <td>
          <?php if ($stock <= 0): ?>
            <span class="badge badge-out">Out of stock</span>
          <?php elseif ($stock <= (int)$p['low_stock_threshold']): ?>
            <span class="badge badge-low">Low stock</span>
          <?php else: ?>
            <span class="badge badge-instock">In stock</span>
          <?php endif; ?>
        </td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-secondary" title="Share to WhatsApp / Instagram / TikTok"
                  onclick='shareProduct(<?= json_encode($p['name'], JSON_HEX_APOS) ?>, <?= json_encode(format_currency($p['sell_price_ref']), JSON_HEX_APOS) ?>, <?= json_encode($p['photo_path'] ? '/' . $p['photo_path'] : null, JSON_HEX_APOS) ?>)'>📤</button>
          <a href="/products/edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
      <tr><td colspan="14" class="text-center text-muted py-4">No products yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
async function shareProduct(name, price, photo) {
  const caption = `${name}\n${price}`;
  try {
    if (photo && window.isSecureContext && navigator.canShare) {
      const resp = await fetch(photo);
      const blob = await resp.blob();
      const ext = (blob.type.split('/')[1] || 'jpg').replace('jpeg', 'jpg');
      const file = new File([blob], `${name.replace(/[^a-z0-9]+/gi, '-')}.${ext}`, { type: blob.type });
      if (navigator.canShare({ files: [file] })) {
        await navigator.share({ files: [file], title: name, text: caption });
        return;
      }
    }
    if (navigator.share) {
      await navigator.share({ title: name, text: caption });
      return;
    }
  } catch (err) {
    if (err.name === 'AbortError') return; // user closed the share sheet
  }
  // No Web Share API here (most desktop browsers) — fall back to a WhatsApp
  // link with the caption ready, and let them attach the photo by hand.
  window.open(`https://wa.me/?text=${encodeURIComponent(caption)}`, '_blank');
  if (photo) {
    alert("This browser can't attach the photo automatically. WhatsApp opened with the caption ready — please attach the photo yourself, or open this page on your phone to share photo + caption together in one tap.");
  }
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
