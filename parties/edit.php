<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM parties WHERE id = ?');
$stmt->execute([$id]);
$party = $stmt->fetch();
if (!$party) {
    flash_set('error', 'Party not found.');
    redirect('/parties/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Name is required.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE parties SET name=?, business_name=?, phone=?, address=?, pan_number=?, opening_balance=? WHERE id=?'
        );
        $stmt->execute([
            $name,
            trim($_POST['business_name']) ?: null,
            trim($_POST['phone']) ?: null,
            trim($_POST['address']) ?: null,
            trim($_POST['pan_number']) ?: null,
            (float)($_POST['opening_balance'] ?? 0),
            $id,
        ]);
        flash_set('success', 'Party updated.');
        redirect('/parties/view.php?id=' . $id);
    }
    $party = array_merge($party, $_POST);
}

$pageTitle = 'Edit Party';
$active = 'parties';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Edit <?= e(ucfirst($party['type'])) ?></div></div>
<div class="card p-4" style="max-width:560px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" value="<?= e($party['name']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Business name</label>
      <input type="text" name="business_name" class="form-control" value="<?= e($party['business_name']) ?>"></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= e($party['phone']) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">PAN number</label>
        <input type="text" name="pan_number" class="form-control" value="<?= e($party['pan_number']) ?>"></div>
    </div>
    <div class="mb-3"><label class="form-label">Address</label>
      <input type="text" name="address" class="form-control" value="<?= e($party['address']) ?>"></div>
    <div class="mb-3">
      <label class="form-label">Opening balance</label>
      <input type="number" step="0.01" name="opening_balance" class="form-control" value="<?= e($party['opening_balance']) ?>">
      <div class="form-text">Positive = they owe the shop. Negative = the shop owes them.</div>
    </div>
    <button class="btn btn-accent">Save changes</button>
    <a href="/parties/view.php?id=<?= (int)$party['id'] ?>" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
