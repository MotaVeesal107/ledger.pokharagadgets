<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM accounts WHERE id = ?');
$stmt->execute([$id]);
$account = $stmt->fetch();
if (!$account) {
    flash_set('error', 'Account not found.');
    redirect('/accounts/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Name is required.';
    } else {
        $stmt = $pdo->prepare('UPDATE accounts SET name=?, type=?, opening_balance=? WHERE id=?');
        $stmt->execute([
            $name,
            in_array($_POST['type'], ['cash', 'bank', 'wallet'], true) ? $_POST['type'] : 'cash',
            (float)($_POST['opening_balance'] ?? 0),
            $id,
        ]);
        flash_set('success', 'Account updated.');
        redirect('/accounts/index.php');
    }
    $account = array_merge($account, $_POST);
}

$pageTitle = 'Edit Account';
$active = 'accounts';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Edit Account</div></div>
<div class="card p-4" style="max-width:480px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" value="<?= e($account['name']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Type</label>
      <select name="type" class="form-select">
        <option value="cash" <?= $account['type'] === 'cash' ? 'selected' : '' ?>>Cash</option>
        <option value="bank" <?= $account['type'] === 'bank' ? 'selected' : '' ?>>Bank</option>
        <option value="wallet" <?= $account['type'] === 'wallet' ? 'selected' : '' ?>>Wallet (eSewa, Khalti, etc.)</option>
      </select>
    </div>
    <div class="mb-3"><label class="form-label">Opening balance</label>
      <input type="number" step="0.01" name="opening_balance" class="form-control" value="<?= e($account['opening_balance']) ?>"></div>
    <button class="btn btn-accent">Save changes</button>
    <a href="/accounts/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
