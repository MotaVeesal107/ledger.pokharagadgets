<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stock.php';
require_login();

$search = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.category LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$products = get_products_with_stock($pdo, $where, $params);
$showCost = can_view_cost($pdo);

$pageTitle = 'Products';
$active = 'products';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Products <span class="badge text-bg-light"><?= count($products) ?></span></div>
    <div class="page-subtitle">Stock is always calculated live from purchases, sales and adjustments.</div>
  </div>
  <a href="/products/add.php" class="btn btn-accent">+ Add product</a>
</div>

<form method="get" class="mb-3" style="max-width:320px;">
  <input type="text" name="q" class="form-control" placeholder="Search name, SKU, barcode, category" value="<?= e($search) ?>">
</form>

<div class="card">
  <table class="table mb-0">
    <thead>
      <tr>
        <th>Product</th><th>SKU</th><th>Category</th><th>Stock</th><th>Unit</th>
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
        <td><?= e($p['sku']) ?></td>
        <td><?= e($p['category']) ?></td>
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
      <tr><td colspan="9" class="text-center text-muted py-4">No products yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
