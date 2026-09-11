<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$error = '';
$type = in_array($_GET['type'] ?? '', ['supplier', 'customer'], true) ? $_GET['type'] : 'customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] === 'supplier' ? 'supplier' : 'customer';
    if ($name === '') {
        $error = 'Name is required.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO parties (type, name, business_name, phone, address, pan_number, opening_balance) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $type,
            $name,
            trim($_POST['business_name']) ?: null,
            trim($_POST['phone']) ?: null,
            trim($_POST['address']) ?: null,
            trim($_POST['pan_number']) ?: null,
            (float)($_POST['opening_balance'] ?? 0),
        ]);
        flash_set('success', ucfirst($type) . ' added.');
        redirect('/parties/index.php');
    }
}

$pageTitle = 'Add Party';
$active = 'parties';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Add Party</div></div>
<div class="card p-4" style="max-width:560px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Type</label>
      <select name="type" class="form-select">
        <option value="customer" <?= $type === 'customer' ? 'selected' : '' ?>>Customer</option>
        <option value="supplier" <?= $type === 'supplier' ? 'selected' : '' ?>>Supplier</option>
      </select>
    </div>
    <div class="mb-3"><label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required></div>
    <div class="mb-3"><label class="form-label">Business name</label>
      <input type="text" name="business_name" class="form-control" value="<?= e($_POST['business_name'] ?? '') ?>"></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">PAN number</label>
        <input type="text" name="pan_number" class="form-control" value="<?= e($_POST['pan_number'] ?? '') ?>"></div>
    </div>
    <div class="mb-3"><label class="form-label">Address</label>
      <input type="text" name="address" class="form-control" value="<?= e($_POST['address'] ?? '') ?>"></div>
    <div class="mb-3">
      <label class="form-label">Opening balance</label>
      <input type="number" step="0.01" name="opening_balance" class="form-control" value="<?= e($_POST['opening_balance'] ?? 0) ?>">
      <div class="form-text">Positive = they owe the shop. Negative = the shop owes them.</div>
    </div>
    <button class="btn btn-accent">Add party</button>
    <a href="/parties/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
