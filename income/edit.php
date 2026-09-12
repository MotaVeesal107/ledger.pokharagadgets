<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login();

$accounts = $pdo->query('SELECT id, name FROM accounts ORDER BY name')->fetchAll();
$categories = $pdo->query(
    "SELECT DISTINCT category FROM other_income WHERE category IS NOT NULL AND category <> '' ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM other_income WHERE id = ?');
$stmt->execute([$id]);
$income = $stmt->fetch();
if (!$income) {
    flash_set('error', 'Income entry not found.');
    redirect('/income/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = (float)($_POST['amount'] ?? 0);
    $category = trim($_POST['category'] ?? '') ?: 'Other';
    if ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } else {
        try {
            $newReceipt = save_receipt_upload('receipt');
            $receiptPath = $newReceipt ?: $income['receipt_path'];
            $stmt = $pdo->prepare('UPDATE other_income SET income_date=?, category=?, amount=?, note=?, receipt_path=?, account_id=? WHERE id=?');
            $stmt->execute([
                $_POST['income_date'] ?: date('Y-m-d'),
                $category,
                $amount,
                trim($_POST['note']) ?: null,
                $receiptPath,
                (int)($_POST['account_id'] ?? 0) ?: null,
                $id,
            ]);
            if ($newReceipt && $income['receipt_path']) {
                delete_receipt_file($income['receipt_path']);
            }
            flash_set('success', 'Income updated.');
            redirect('/income/index.php');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
    $income = array_merge($income, $_POST);
}

$pageTitle = 'Edit Income';
$active = 'income';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Edit Income</div></div>
<div class="card p-4" style="max-width:480px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Date</label>
      <input type="date" name="income_date" class="form-control" value="<?= e($income['income_date']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Category</label>
      <input type="text" name="category" class="form-control" list="income-categories" value="<?= e($income['category']) ?>">
      <datalist id="income-categories"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
    </div>
    <div class="mb-3"><label class="form-label">Amount</label>
      <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= e($income['amount']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Note</label>
      <input type="text" name="note" class="form-control" value="<?= e($income['note']) ?>"></div>
    <div class="mb-3"><label class="form-label">Received into account (optional)</label>
      <select name="account_id" class="form-select">
        <option value="">Not tracked</option>
        <?php foreach ($accounts as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= (int)$income['account_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3"><label class="form-label">Receipt photo (optional)</label>
      <?php if (!empty($income['receipt_path'])): ?>
        <div class="form-text mb-1"><a href="/<?= e($income['receipt_path']) ?>" target="_blank">View current receipt</a> — choosing a new file below replaces it.</div>
      <?php endif; ?>
      <input type="file" name="receipt" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
    <button class="btn btn-accent">Save changes</button>
    <a href="/income/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
