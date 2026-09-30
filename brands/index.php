<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q = trim($_GET['q'] ?? '');

$stmt = $pdo->query(
    "SELECT b.id, b.name, b.photo_path,
            (SELECT COUNT(*) FROM products p WHERE p.brand = b.name) AS product_count
     FROM brands b ORDER BY b.name"
);
$brands = $stmt->fetchAll();
$namedBrandNames = array_column($brands, 'name');

// Brands used on products but with no dedicated (photo) entry yet.
$sql = "SELECT p.brand AS name, COUNT(*) AS product_count FROM products p
        WHERE p.brand IS NOT NULL AND p.brand <> ''";
$params = [];
if ($namedBrandNames) {
    $sql .= ' AND p.brand NOT IN (' . implode(',', array_fill(0, count($namedBrandNames), '?')) . ')';
    $params = $namedBrandNames;
}
$sql .= ' GROUP BY p.brand ORDER BY p.brand';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
foreach ($stmt->fetchAll() as $row) {
    $brands[] = ['id' => null, 'name' => $row['name'], 'photo_path' => null, 'product_count' => $row['product_count']];
}
usort($brands, fn($a, $b) => strcasecmp($a['name'], $b['name']));

if ($q !== '') {
    $brands = array_values(array_filter($brands, fn($b) => stripos($b['name'], $q) !== false));
}

$pageTitle = 'Brands';
$active = 'brands';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Brands</div>
    <div class="page-subtitle">Click a brand to see only its products.</div>
  </div>
  <div>
    <a href="/brands/add.php" class="btn btn-accent">+ Add brand</a>
  </div>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto" style="min-width:260px;">
    <input type="text" name="q" class="form-control" placeholder="Search brands" value="<?= e($q) ?>">
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
</form>

<div class="row g-3">
  <?php foreach ($brands as $b): ?>
  <div class="col-6 col-md-3 col-lg-2">
    <div class="card h-100 text-center p-3">
      <a href="/products/index.php?brand=<?= urlencode($b['name']) ?>" class="text-decoration-none text-dark">
        <?php if (!empty($b['photo_path'])): ?>
          <img src="/<?= e($b['photo_path']) ?>" alt="<?= e($b['name']) ?>" style="width:100%;aspect-ratio:1;object-fit:contain;">
        <?php else: ?>
          <div style="width:100%;aspect-ratio:1;background:#f2f2ee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:1.5rem;"><?= e(strtoupper(substr($b['name'], 0, 1))) ?></div>
        <?php endif; ?>
        <div class="fw-semibold mt-2 small"><?= e($b['name']) ?></div>
        <div class="text-muted small"><?= (int)$b['product_count'] ?> product<?= (int)$b['product_count'] === 1 ? '' : 's' ?></div>
      </a>
      <?php if (is_admin()): ?>
        <?php if ($b['id']): ?>
        <a href="/brands/edit.php?id=<?= (int)$b['id'] ?>" class="small d-block mt-2">Edit</a>
        <?php else: ?>
        <a href="/brands/add.php?name=<?= urlencode($b['name']) ?>" class="small d-block mt-2">Add photo</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$brands): ?>
  <div class="col-12 text-center text-muted py-4">
    <?= $q !== '' ? 'No brands match your search.' : 'No brands yet. Add a brand, or set a Brand on a product first.' ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
