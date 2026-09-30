<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q = trim($_GET['q'] ?? '');

$stmt = $pdo->query(
    "SELECT c.id, c.name, c.photo_path,
            (SELECT COUNT(*) FROM products p WHERE p.category = c.name) AS product_count
     FROM categories c ORDER BY c.name"
);
$categories = $stmt->fetchAll();
$namedCategoryNames = array_column($categories, 'name');

// Categories used on products but with no dedicated (photo) entry yet.
$sql = "SELECT p.category AS name, COUNT(*) AS product_count FROM products p
        WHERE p.category IS NOT NULL AND p.category <> ''";
$params = [];
if ($namedCategoryNames) {
    $sql .= ' AND p.category NOT IN (' . implode(',', array_fill(0, count($namedCategoryNames), '?')) . ')';
    $params = $namedCategoryNames;
}
$sql .= ' GROUP BY p.category ORDER BY p.category';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
foreach ($stmt->fetchAll() as $row) {
    $categories[] = ['id' => null, 'name' => $row['name'], 'photo_path' => null, 'product_count' => $row['product_count']];
}
usort($categories, fn($a, $b) => strcasecmp($a['name'], $b['name']));

if ($q !== '') {
    $categories = array_values(array_filter($categories, fn($c) => stripos($c['name'], $q) !== false));
}

$pageTitle = 'Categories';
$active = 'categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Categories</div>
    <div class="page-subtitle">Case styles like Silicone Case, Ultra Case, Kitty Case, Vibe Case — click one to see only its products.</div>
  </div>
  <div>
    <a href="/categories/add.php" class="btn btn-accent">+ Add category</a>
  </div>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-auto" style="min-width:260px;">
    <input type="text" name="q" class="form-control" placeholder="Search categories" value="<?= e($q) ?>">
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
</form>

<div class="row g-3">
  <?php foreach ($categories as $c): ?>
  <div class="col-6 col-md-3 col-lg-2">
    <div class="card h-100 text-center p-3">
      <a href="/products/index.php?category=<?= urlencode($c['name']) ?>" class="text-decoration-none text-dark">
        <?php if (!empty($c['photo_path'])): ?>
          <img src="/<?= e($c['photo_path']) ?>" alt="<?= e($c['name']) ?>" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:6px;">
        <?php else: ?>
          <div style="width:100%;aspect-ratio:1;background:#f2f2ee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:1.5rem;"><?= e(strtoupper(substr($c['name'], 0, 1))) ?></div>
        <?php endif; ?>
        <div class="fw-semibold mt-2 small"><?= e($c['name']) ?></div>
        <div class="text-muted small"><?= (int)$c['product_count'] ?> product<?= (int)$c['product_count'] === 1 ? '' : 's' ?></div>
      </a>
      <?php if (is_admin()): ?>
        <?php if ($c['id']): ?>
        <a href="/categories/edit.php?id=<?= (int)$c['id'] ?>" class="small d-block mt-2">Edit</a>
        <?php else: ?>
        <a href="/categories/add.php?name=<?= urlencode($c['name']) ?>" class="small d-block mt-2">Add photo</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$categories): ?>
  <div class="col-12 text-center text-muted py-4">
    <?= $q !== '' ? 'No categories match your search.' : 'No categories yet. Add one, or set a Category on a product first.' ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
