<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q = trim($_GET['q'] ?? '');

$stmt = $pdo->query(
    "SELECT device_model, COUNT(*) AS product_count,
            (SELECT photo_path FROM products p2
             WHERE p2.device_model = p1.device_model AND p2.photo_path IS NOT NULL
             ORDER BY p2.id LIMIT 1) AS photo_path
     FROM products p1
     WHERE device_model IS NOT NULL AND device_model <> ''
     GROUP BY device_model
     ORDER BY device_model"
);
$models = $stmt->fetchAll();

if ($q !== '') {
    $models = array_values(array_filter($models, fn($m) => stripos($m['device_model'], $q) !== false));
}

$pageTitle = 'Models';
$active = 'models';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Models</div>
    <div class="page-subtitle">Browse covers and accessories by phone model — pick a model to see its colors and prices.</div>
  </div>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto" style="min-width:260px;">
    <input type="text" name="q" class="form-control" placeholder="Search models (e.g. iPhone 11)" value="<?= e($q) ?>">
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
</form>

<div class="row g-3">
  <?php foreach ($models as $m): ?>
  <div class="col-6 col-md-3 col-lg-2">
    <a href="/models/view.php?model=<?= urlencode($m['device_model']) ?>" class="card h-100 text-center p-3 text-decoration-none text-dark d-block">
      <?php if (!empty($m['photo_path'])): ?>
        <img src="/<?= e($m['photo_path']) ?>" alt="<?= e($m['device_model']) ?>" style="width:100%;aspect-ratio:1;object-fit:contain;">
      <?php else: ?>
        <div style="width:100%;aspect-ratio:1;background:#f2f2ee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:1.5rem;">📱</div>
      <?php endif; ?>
      <div class="fw-semibold mt-2 small"><?= e($m['device_model']) ?></div>
      <div class="text-muted small"><?= (int)$m['product_count'] ?> item<?= (int)$m['product_count'] === 1 ? '' : 's' ?></div>
    </a>
  </div>
  <?php endforeach; ?>
  <?php if (!$models): ?>
  <div class="col-12 text-center text-muted py-4">
    <?= $q !== '' ? 'No models match your search.' : 'No products have a Device model set yet. Add one when creating a product (Products → Add product → Device model).' ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
