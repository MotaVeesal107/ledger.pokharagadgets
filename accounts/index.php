<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/accounts.php';
require_login();

$accounts = get_accounts_with_balance($pdo);
$total = get_total_cash_bank_balance($pdo);

$pageTitle = 'Manage Accounts';
$active = 'accounts';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Manage Accounts</div>
    <div class="page-subtitle">Cash drawers, bank accounts and wallets. Tag payments/expenses to one to track its balance.</div>
  </div>
  <a href="/accounts/add.php" class="btn btn-accent">+ Add account</a>
</div>

<div class="card p-3 mb-3 stat-card" style="max-width:300px;">
  <div class="stat-label">Total Balance (Cash &amp; Bank)</div>
  <div class="stat-value"><?= format_currency($total) ?></div>
</div>

<div class="card">
  <table class="table mb-0">
    <thead><tr><th>Name</th><th>Type</th><th class="text-end">Balance</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($accounts as $a): ?>
      <tr>
        <td><a href="/accounts/view.php?id=<?= (int)$a['id'] ?>"><?= e($a['name']) ?></a></td>
        <td><span class="badge text-bg-light border"><?= e(ucfirst($a['type'])) ?></span></td>
        <td class="text-end fw-semibold"><?= format_currency($a['balance']) ?></td>
        <td class="text-end">
          <a href="/accounts/view.php?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-secondary">Ledger</a>
          <a href="/accounts/edit.php?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$accounts): ?>
      <tr><td colspan="4" class="text-center text-muted py-4">No accounts yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
