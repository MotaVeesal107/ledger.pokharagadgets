<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$showCost = can_view_cost($pdo);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Product name is required.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO products (name, sku, barcode, category, unit, low_stock_threshold, warranty_period, is_serialized, cost_price_ref, sell_price_ref)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
                $showCost ? (float)($_POST['cost_price_ref'] ?? 0) : 0,
                (float)($_POST['sell_price_ref'] ?? 0),
            ]);
            flash_set('success', 'Product added. Record a purchase to bring in stock.');
            redirect('/products/index.php');
        } catch (PDOException $e) {
            $error = 'That SKU is already in use by another product.';
        }
    }
}

$pageTitle = 'Add Product';
$active = 'products';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Add Product</div></div>
<div class="card p-4" style="max-width:560px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <div class="alert alert-info py-2 small">New products start at 0 stock. Bring in stock through <strong>Purchases</strong> — quantities are never set directly on a product.</div>
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Product name</label>
      <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">SKU</label>
        <input type="text" name="sku" class="form-control" value="<?= e($_POST['sku'] ?? '') ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Barcode</label>
        <input type="text" name="barcode" class="form-control" value="<?= e($_POST['barcode'] ?? '') ?>"></div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Category</label>
        <input type="text" name="category" class="form-control" value="<?= e($_POST['category'] ?? '') ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Unit</label>
        <input type="text" name="unit" class="form-control" value="<?= e($_POST['unit'] ?? 'pcs') ?>"></div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Low stock threshold</label>
        <input type="number" name="low_stock_threshold" class="form-control" value="<?= e($_POST['low_stock_threshold'] ?? 0) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Warranty period</label>
        <input type="text" name="warranty_period" class="form-control" placeholder="e.g. 6 months" value="<?= e($_POST['warranty_period'] ?? '') ?>"></div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Reference cost price</label>
        <input type="number" step="0.01" name="cost_price_ref" class="form-control" value="<?= e($_POST['cost_price_ref'] ?? 0) ?>" <?= $showCost ? '' : 'disabled' ?>>
        <?php if (!$showCost): ?><div class="form-text">Hidden from your role.</div><?php endif; ?>
      </div>
      <div class="col-md-6 mb-3"><label class="form-label">Default sell price</label>
        <input type="number" step="0.01" name="sell_price_ref" class="form-control" value="<?= e($_POST['sell_price_ref'] ?? 0) ?>"></div>
    </div>
    <div class="form-check mb-3">
      <input type="checkbox" class="form-check-input" id="is_serialized" name="is_serialized" <?= isset($_POST['is_serialized']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="is_serialized">Serialized item (track IMEI/serial per unit — phones, laptops, etc.)</label>
    </div>
    <button class="btn btn-accent">Add product</button>
    <a href="/products/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
