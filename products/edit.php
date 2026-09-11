<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stock.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    flash_set('error', 'Product not found.');
    redirect('/products/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Product name is required.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'UPDATE products SET name=?, sku=?, barcode=?, category=?, unit=?, low_stock_threshold=?, warranty_period=?, is_serialized=?, cost_price_ref=?, sell_price_ref=? WHERE id=?'
            );
            $stmt->execute([
                $name,
                trim($_POST['sku']) ?: null,
                trim($_POST['barcode']) ?: null,
                trim($_POST['category']) ?: null,
                trim($_POST['unit']) ?: 'pcs',
                (int)($_POST['low_stock_threshold'] ?? 0),
                trim($_POST['warranty_period']) ?: null,
                isset($_POST['is_serialized']) ? 1 : 0,
                can_view_cost($pdo) ? (float)($_POST['cost_price_ref'] ?? 0) : $product['cost_price_ref'],
                (float)($_POST['sell_price_ref'] ?? 0),
                $id,
            ]);
            flash_set('success', 'Product updated.');
            redirect('/products/index.php');
        } catch (PDOException $e) {
            $error = 'That SKU is already in use by another product.';
        }
    }
    $product = array_merge($product, $_POST);
}

$stock = get_stock($pdo, $id);
$showCost = can_view_cost($pdo);

$pageTitle = 'Edit Product';
$active = 'products';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Edit Product</div></div>
<div class="card p-4" style="max-width:560px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <div class="mb-3">
    <span class="stat-label">Current stock (calculated)</span>
    <div class="stat-value"><?= $stock ?> <?= e($product['unit']) ?></div>
    <div class="form-text">Stock changes only through Purchases, Sales, and Returns/Adjustments.</div>
  </div>
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Product name</label>
      <input type="text" name="name" class="form-control" value="<?= e($product['name']) ?>" required></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">SKU</label>
        <input type="text" name="sku" class="form-control" value="<?= e($product['sku']) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Barcode</label>
        <input type="text" name="barcode" class="form-control" value="<?= e($product['barcode']) ?>"></div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Category</label>
        <input type="text" name="category" class="form-control" value="<?= e($product['category']) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Unit</label>
        <input type="text" name="unit" class="form-control" value="<?= e($product['unit']) ?>"></div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Low stock threshold</label>
        <input type="number" name="low_stock_threshold" class="form-control" value="<?= e($product['low_stock_threshold']) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Warranty period</label>
        <input type="text" name="warranty_period" class="form-control" value="<?= e($product['warranty_period']) ?>"></div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Reference cost price</label>
        <input type="number" step="0.01" name="cost_price_ref" class="form-control" value="<?= e($product['cost_price_ref']) ?>" <?= $showCost ? '' : 'disabled' ?>>
        <?php if (!$showCost): ?><div class="form-text">Hidden from your role. Ask an admin to change this.</div><?php endif; ?>
      </div>
      <div class="col-md-6 mb-3"><label class="form-label">Default sell price</label>
        <input type="number" step="0.01" name="sell_price_ref" class="form-control" value="<?= e($product['sell_price_ref']) ?>"></div>
    </div>
    <div class="form-check mb-3">
      <input type="checkbox" class="form-check-input" id="is_serialized" name="is_serialized" <?= $product['is_serialized'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="is_serialized">Serialized item (track IMEI/serial per unit)</label>
    </div>
    <button class="btn btn-accent">Save changes</button>
    <a href="/products/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<?php if (is_admin()): ?>
<div class="card p-3 mt-3" style="max-width:560px;">
  <form method="post" action="/products/delete.php" onsubmit="return confirm('Delete this product? Only possible if it has no purchase/sale history.');">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
    <button class="btn btn-outline-danger btn-sm">Delete product</button>
  </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
