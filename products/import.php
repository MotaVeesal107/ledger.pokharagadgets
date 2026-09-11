<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/product_import.php';
require_login();

$error = '';
$preview = null;
$step = $_POST['step'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'preview') {
    verify_csrf();
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a CSV file to upload.';
    } else {
        try {
            $rows = parse_product_import_csv($_FILES['file']['tmp_name']);
            if (!$rows) {
                $error = 'No data rows found in the file.';
            } else {
                $rows = validate_product_import_rows($pdo, $rows);
                $_SESSION['product_import'] = $rows;
                $preview = $rows;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'confirm') {
    verify_csrf();
    $rows = $_SESSION['product_import'] ?? [];
    $validRows = array_filter($rows, fn($r) => empty($r['errors']));

    if (!$validRows) {
        flash_set('error', 'Nothing to import — upload a file first.');
        redirect('/products/import.php');
    }

    try {
        $count = commit_product_import($pdo, $validRows, can_view_cost($pdo));
        unset($_SESSION['product_import']);
        flash_set('success', "Imported {$count} product(s).");
        redirect('/products/index.php');
    } catch (PDOException $e) {
        $error = 'Import failed: one of the rows conflicted with existing data (probably a duplicate SKU created since you previewed). Please re-upload the file.';
        unset($_SESSION['product_import']);
    }
}

$pageTitle = 'Import Products';
$active = 'products';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Import Products from CSV</div>
    <div class="page-subtitle">Bring in products from an Excel/Google Sheets file — save it as CSV first, then upload here.</div>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

<?php if (!$preview): ?>
<div class="card p-4" style="max-width:600px;">
  <div class="mb-3">
    <a href="/products/import_template.php" class="btn btn-outline-secondary btn-sm">Download CSV template</a>
    <div class="form-text">Open it in Excel/Google Sheets, fill in your products, then save/export as CSV before uploading.</div>
  </div>
  <ul class="small text-muted">
    <li><strong>name</strong> is the only required column.</li>
    <li>Leave <strong>sku</strong> blank to auto-generate one.</li>
    <li><strong>is_serialized</strong>: enter "yes" for phones/laptops that need IMEI tracking, otherwise leave blank.</li>
    <li><strong>cost_price_ref</strong> / <strong>sell_price_ref</strong>: plain numbers, no currency symbol.</li>
    <li>This only creates the products at 0 stock — bring in quantities afterward through <strong>Purchases</strong>.</li>
  </ul>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="preview">
    <div class="mb-3">
      <input type="file" name="file" accept=".csv" class="form-control" required>
    </div>
    <button class="btn btn-accent">Preview import</button>
    <a href="/products/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php else: ?>
  <?php
  $validCount = count(array_filter($preview, fn($r) => empty($r['errors'])));
  $errorCount = count($preview) - $validCount;
  ?>
  <div class="alert alert-<?= $errorCount ? 'warning' : 'success' ?> py-2">
    <?= $validCount ?> row(s) ready to import.
    <?php if ($errorCount): ?><?= $errorCount ?> row(s) have errors and will be skipped.<?php endif; ?>
  </div>

  <div class="card mb-3">
    <table class="table mb-0">
      <thead><tr><th>Line</th><th>Name</th><th>SKU</th><th>Category</th><th>Brand</th><th>Device Model</th><th class="text-end">Cost</th><th class="text-end">Sell</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($preview as $row): $n = $row['normalized']; ?>
        <tr class="<?= $row['errors'] ? 'table-danger' : '' ?>">
          <td><?= (int)$row['line'] ?></td>
          <td><?= e($n['name']) ?></td>
          <td><?= e($n['sku']) ?: '<span class="text-muted">auto</span>' ?></td>
          <td><?= e($n['category']) ?></td>
          <td><?= e($n['brand']) ?></td>
          <td><?= e($n['device_model']) ?></td>
          <td class="text-end"><?= format_currency($n['cost_price_ref']) ?></td>
          <td class="text-end"><?= format_currency($n['sell_price_ref']) ?></td>
          <td class="small">
            <?php if ($row['errors']): ?>
              <span class="text-danger"><?= e(implode(' ', $row['errors'])) ?></span>
            <?php else: ?>
              <span class="text-success">OK</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="confirm">
    <button class="btn btn-accent" <?= $validCount === 0 ? 'disabled' : '' ?>>Import <?= $validCount ?> product(s)</button>
    <a href="/products/import.php" class="btn btn-link">Start over</a>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
