<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Name is required.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO accounts (name, type, opening_balance) VALUES (?, ?, ?)');
        $stmt->execute([
            $name,
            in_array($_POST['type'], ['cash', 'bank', 'wallet'], true) ? $_POST['type'] : 'cash',
            (float)($_POST['opening_balance'] ?? 0),
        ]);
        flash_set('success', 'Account added.');
        redirect('/accounts/index.php');
    }
}

$pageTitle = 'Add Account';
$active = 'accounts';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Add Account</div></div>
<div class="card p-4" style="max-width:480px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" placeholder="e.g. Cash Drawer, NIC Asia Bank" value="<?= e($_POST['name'] ?? '') ?>" required></div>
    <div class="mb-3"><label class="form-label">Type</label>
      <select name="type" class="form-select">
        <option value="cash">Cash</option>
        <option value="bank">Bank</option>
        <option value="wallet">Wallet (eSewa, Khalti, etc.)</option>
      </select>
    </div>
    <div class="mb-3"><label class="form-label">Opening balance</label>
      <input type="number" step="0.01" name="opening_balance" class="form-control" value="0"></div>
    <button class="btn btn-accent">Add account</button>
    <a href="/accounts/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
