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
        $error = 'Category name is required.';
    } else {
        try {
            $photoPath = save_category_photo_upload('photo');
            $stmt = $pdo->prepare('INSERT INTO categories (name, photo_path) VALUES (?, ?)');
            $stmt->execute([$name, $photoPath]);
            flash_set('success', 'Category added.');
            redirect('/categories/index.php');
        } catch (PDOException $e) {
            $error = 'A category with that name already exists.';
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Add Category';
$active = 'categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Add Category</div></div>
<div class="card p-4" style="max-width:480px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Category name</label>
      <input type="text" name="name" class="form-control" list="existing-categories" value="<?= e($_POST['name'] ?? $_GET['name'] ?? '') ?>" placeholder="e.g. Silicone Case, Ultra Case, Kitty Case, Vibe Case" required>
      <datalist id="existing-categories"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
      <div class="form-text">Matches products by this exact name — pick an existing category from the list if you're just adding its photo.</div>
    </div>
    <div class="mb-3">
      <label class="form-label">Photo</label>
      <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="form-control">
      <div class="form-text">Optional. A square image works best here since it's shown in a small tile — around <strong>400×400px</strong> is plenty (anywhere from 300×300 to 800×800 is fine). JPG, PNG, or WEBP, up to 2MB.</div>
    </div>
    <button class="btn btn-accent">Add category</button>
    <a href="/categories/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
