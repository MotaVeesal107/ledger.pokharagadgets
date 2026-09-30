<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
$stmt->execute([$id]);
$category = $stmt->fetch();
if (!$category) {
    flash_set('error', 'Category not found.');
    redirect('/categories/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        try {
            $photoPath = $category['photo_path'];
            if (!empty($_POST['remove_photo'])) {
                delete_receipt_file($photoPath);
                $photoPath = null;
            } else {
                $newPhoto = save_category_photo_upload('photo');
                if ($newPhoto) {
                    delete_receipt_file($photoPath);
                    $photoPath = $newPhoto;
                }
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE categories SET name = ?, photo_path = ? WHERE id = ?');
            $stmt->execute([$name, $photoPath, $id]);
            if ($name !== $category['name']) {
                $pdo->prepare('UPDATE products SET category = ? WHERE category = ?')->execute([$name, $category['name']]);
            }
            $pdo->commit();

            flash_set('success', 'Category updated.');
            redirect('/categories/index.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'A category with that name already exists.';
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
    $category = array_merge($category, ['name' => $name]);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category = ?');
$stmt->execute([$category['name']]);
$productCount = (int)$stmt->fetchColumn();

$pageTitle = 'Edit Category';
$active = 'categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Edit Category</div></div>
<div class="card p-4" style="max-width:480px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Category name</label>
      <input type="text" name="name" class="form-control" value="<?= e($category['name']) ?>" required>
      <div class="form-text">Renaming updates all <?= $productCount ?> product(s) currently set to this category.</div>
    </div>
    <div class="mb-3">
      <label class="form-label">Photo</label>
      <?php if (!empty($category['photo_path'])): ?>
        <div class="mb-2 d-flex align-items-center gap-2">
          <img src="/<?= e($category['photo_path']) ?>" alt="" style="height:64px;border-radius:4px;border:1px solid #ddd;">
          <label class="small text-danger"><input type="checkbox" name="remove_photo" value="1"> Remove photo</label>
        </div>
      <?php endif; ?>
      <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="form-control">
      <div class="form-text">A square image works best — around <strong>400×400px</strong> is plenty (300×300 to 800×800 is fine). JPG, PNG, or WEBP, up to 2MB.</div>
    </div>
    <button class="btn btn-accent">Save changes</button>
    <a href="/categories/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<div class="card p-3 mt-3" style="max-width:480px;">
  <form method="post" action="/categories/delete.php" onsubmit="return confirm('Delete this category entry? Products keep their category text — only the dedicated photo/listing is removed.');">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
    <button class="btn btn-outline-danger btn-sm">Delete category entry</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
