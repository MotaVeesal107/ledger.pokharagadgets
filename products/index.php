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

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.category LIKE ? OR p.brand LIKE ? OR p.device_model LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
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
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$products = get_products_with_stock($pdo, $where, $params);
$showCost = can_view_cost($pdo);
$deviceModels = $pdo->query(
    "SELECT DISTINCT device_model FROM products WHERE device_model IS NOT NULL AND device_model <> '' ORDER BY device_model"
)->fetchAll(PDO::FETCH_COLUMN);
$brands = $pdo->query(
    "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand"
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
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
</form>

<div class="card">
  <table class="table mb-0">
    <thead>
      <tr>
        <th>Product</th><th>Category</th><th>Brand</th><th>Device Model</th><th>SKU</th><th>Stock</th><th>Unit</th>
        <?php if ($showCost): ?><th>Cost</th><?php endif; ?>
        <th>Sell Price</th><th>Reorder</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): $stock = (int)$p['stock']; ?>
      <tr>
        <td>
          <?= e($p['name']) ?>
          <?php if ($p['is_serialized']): ?><span class="badge text-bg-light border">serialized</span><?php endif; ?>
        </td>
        <td><?= e($p['category']) ?></td>
        <td><?= e($p['brand']) ?></td>
        <td><?= e($p['device_model']) ?></td>
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
          <a href="/products/edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
      <tr><td colspan="12" class="text-center text-muted py-4">No products yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
