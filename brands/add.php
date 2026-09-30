<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_admin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Brand name is required.';
    } else {
        try {
            $photoPath = save_brand_photo_upload('photo');
            $stmt = $pdo->prepare('INSERT INTO brands (name, photo_path) VALUES (?, ?)');
            $stmt->execute([$name, $photoPath]);
            flash_set('success', 'Brand added.');
            redirect('/brands/index.php');
        } catch (PDOException $e) {
            $error = 'A brand with that name already exists.';
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$brands = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Add Brand';
$active = 'brands';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Add Brand</div></div>
<div class="card p-4" style="max-width:480px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Brand name</label>
      <input type="text" name="name" class="form-control" list="existing-brands" value="<?= e($_POST['name'] ?? $_GET['name'] ?? '') ?>" required>
      <datalist id="existing-brands"><?php foreach ($brands as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist>
      <div class="form-text">Matches products by this exact name — pick an existing brand from the list if you're just adding its photo.</div>
    </div>
    <div class="mb-3">
      <label class="form-label">Photo / logo</label>
      <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="form-control">
      <div class="form-text">Optional. A square image works best here since it's shown in a small tile — around <strong>400×400px</strong> is plenty (anywhere from 300×300 to 800×800 is fine). JPG, PNG, or WEBP, up to 2MB. PNG with a transparent background looks cleanest.</div>
    </div>
    <button class="btn btn-accent">Add brand</button>
    <a href="/brands/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
