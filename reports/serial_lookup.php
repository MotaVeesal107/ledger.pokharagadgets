<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$serial = trim($_GET['serial'] ?? '');
$showCost = can_view_cost($pdo);
$purchaseRows = [];
$saleRows = [];

if ($serial !== '') {
    $stmt = $pdo->prepare(
        "SELECT pi.*, pu.purchase_date, pu.id AS purchase_id, pu.bill_ref, pa.name AS supplier_name, p.name AS product_name, p.warranty_period
         FROM purchase_items pi
         JOIN purchases pu ON pu.id = pi.purchase_id
         JOIN parties pa ON pa.id = pu.party_id
         JOIN products p ON p.id = pi.product_id
         WHERE pi.serial_no = ? ORDER BY pu.purchase_date DESC"
    );
    $stmt->execute([$serial]);
    $purchaseRows = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT si.*, s.sale_date, s.invoice_no, COALESCE(pa.name, s.walkin_name, 'Walk-in') AS customer_name
         FROM sale_items si JOIN sales s ON s.id = si.sale_id LEFT JOIN parties pa ON pa.id = s.party_id
         WHERE si.serial_no = ? ORDER BY s.sale_date DESC"
    );
    $stmt->execute([$serial]);
    $saleRows = $stmt->fetchAll();
}

$pageTitle = 'IMEI / Serial Lookup';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">IMEI / Serial Lookup</div></div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto"><input type="text" name="serial" class="form-control" placeholder="Enter serial / IMEI" value="<?= e($serial) ?>" style="min-width:260px;"></div>
  <div class="col-auto"><button class="btn btn-sm btn-accent">Search</button></div>
</form>

<?php if ($serial !== ''): ?>
  <?php if (!$purchaseRows): ?>
    <div class="alert alert-warning">No purchase record found for serial "<?= e($serial) ?>".</div>
  <?php else: ?>
    <?php foreach ($purchaseRows as $pr): ?>
    <div class="card p-3 mb-3">
      <div class="fw-semibold"><?= e($pr['product_name']) ?> — <?= e($serial) ?>
        <span class="badge <?= $pr['status'] === 'in_stock' ? 'badge-instock' : ($pr['status'] === 'sold' ? 'text-bg-secondary' : 'badge-out') ?>"><?= e(ucfirst($pr['status'])) ?></span>
      </div>
      <div class="row mt-2">
        <div class="col-md-6">
          <div class="small text-muted">Purchase</div>
          <div>Purchase #<?= (int)$pr['purchase_id'] ?> · <?= e($pr['purchase_date']) ?> · <?= e($pr['supplier_name']) ?><?= $pr['bill_ref'] ? ' · Bill ' . e($pr['bill_ref']) : '' ?></div>
          <?php if ($showCost): ?><div class="small text-muted">Cost: <?= format_currency($pr['cost_price']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <?php if ($saleRows): ?>
            <div class="small text-muted">Sale history</div>
            <?php foreach ($saleRows as $sr): ?>
            <div>
              <a href="/sales/view.php?id=<?= (int)$sr['sale_id'] ?>"><?= e($sr['invoice_no']) ?></a>
              · <?= e($sr['sale_date']) ?> · <?= e($sr['customer_name']) ?>
              <?php $expiry = warranty_expiry_date($pr['warranty_period'], $sr['sale_date']); ?>
              <?php if ($expiry): ?>
                <div class="small <?= $expiry >= date('Y-m-d') ? 'text-success' : 'text-danger' ?>">
                  Warranty <?= $expiry >= date('Y-m-d') ? 'valid until' : 'expired on' ?> <?= e($expiry) ?>
                </div>
              <?php elseif ($pr['warranty_period']): ?>
                <div class="small text-muted">Warranty: <?= e($pr['warranty_period']) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="small text-muted">Not sold yet — currently in stock.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
