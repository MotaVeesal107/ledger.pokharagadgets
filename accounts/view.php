<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/accounts.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM accounts WHERE id = ?');
$stmt->execute([$id]);
$account = $stmt->fetch();
if (!$account) {
    flash_set('error', 'Account not found.');
    redirect('/accounts/index.php');
}

$balance = get_account_balance($pdo, $id);
$transactions = get_account_transactions($pdo, $id);

$pageTitle = $account['name'];
$active = 'accounts';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4"><?= e($account['name']) ?> <span class="badge text-bg-light border"><?= e(ucfirst($account['type'])) ?></span></div>
  </div>
  <a href="/accounts/edit.php?id=<?= $id ?>" class="btn btn-outline-secondary">Edit</a>
</div>

<div class="card p-3 mb-3 stat-card" style="max-width:300px;">
  <div class="stat-label">Current balance</div>
  <div class="stat-value"><?= format_currency($balance) ?></div>
</div>

<div class="card">
  <div class="p-3 border-bottom fw-semibold">Ledger</div>
  <table class="table mb-0">
    <thead><tr><th>Date</th><th>Description</th><th class="text-end">Effect</th><th class="text-end">Balance</th></tr></thead>
    <tbody>
      <?php foreach ($transactions as $t): ?>
      <tr>
        <td><?= e($t['date']) ?></td>
        <td><?= e($t['description']) ?></td>
        <td class="text-end"><?= $t['effect'] >= 0 ? '+' : '' ?><?= format_currency($t['effect']) ?></td>
        <td class="text-end fw-semibold"><?= format_currency($t['balance_after']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$transactions): ?>
      <tr><td colspan="4" class="text-center text-muted py-4">No transactions tagged to this account yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
