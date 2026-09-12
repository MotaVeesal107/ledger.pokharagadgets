<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login();

$accounts = $pdo->query('SELECT id, name FROM accounts ORDER BY name')->fetchAll();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = ?');
$stmt->execute([$id]);
$expense = $stmt->fetch();
if (!$expense) {
    flash_set('error', 'Expense not found.');
    redirect('/expenses/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = (float)($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } else {
        try {
            $newReceipt = save_receipt_upload('receipt');
            $receiptPath = $newReceipt ?: $expense['receipt_path'];
            $stmt = $pdo->prepare('UPDATE expenses SET expense_date=?, category=?, amount=?, note=?, receipt_path=?, account_id=? WHERE id=?');
            $stmt->execute([
                $_POST['expense_date'] ?: date('Y-m-d'),
                in_array($_POST['category'], ['rent', 'salary', 'electricity', 'other'], true) ? $_POST['category'] : 'other',
                $amount,
                trim($_POST['note']) ?: null,
                $receiptPath,
                (int)($_POST['account_id'] ?? 0) ?: null,
                $id,
            ]);
            if ($newReceipt && $expense['receipt_path']) {
                delete_receipt_file($expense['receipt_path']);
            }
            flash_set('success', 'Expense updated.');
            redirect('/expenses/index.php');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
    $expense = array_merge($expense, $_POST);
}

$pageTitle = 'Edit Expense';
$active = 'expenses';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Edit Expense</div></div>
<div class="card p-4" style="max-width:480px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Date</label>
      <input type="date" name="expense_date" class="form-control" value="<?= e($expense['expense_date']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Category</label>
      <select name="category" class="form-select">
        <?php foreach (['rent', 'salary', 'electricity', 'other'] as $c): ?>
        <option value="<?= $c ?>" <?= $expense['category'] === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3"><label class="form-label">Amount</label>
      <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= e($expense['amount']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Note</label>
      <input type="text" name="note" class="form-control" value="<?= e($expense['note']) ?>"></div>
    <div class="mb-3"><label class="form-label">Paid from account (optional)</label>
      <select name="account_id" class="form-select">
        <option value="">Not tracked</option>
        <?php foreach ($accounts as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= (int)$expense['account_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3"><label class="form-label">Receipt photo (optional)</label>
      <?php if (!empty($expense['receipt_path'])): ?>
        <div class="form-text mb-1"><a href="/<?= e($expense['receipt_path']) ?>" target="_blank">View current receipt</a> — choosing a new file below replaces it.</div>
      <?php endif; ?>
      <input type="file" name="receipt" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
    <button class="btn btn-accent">Save changes</button>
    <a href="/expenses/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
