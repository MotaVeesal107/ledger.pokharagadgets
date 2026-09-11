<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/parties.php';
require_login();

$type = in_array($_GET['type'] ?? '', ['supplier', 'customer'], true) ? $_GET['type'] : null;
$parties = get_parties_with_balance($pdo, $type);

usort($parties, function ($a, $b) {
    return abs($b['balance']) <=> abs($a['balance']);
});

$pageTitle = 'Parties';
$active = 'parties';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Parties</div>
    <div class="page-subtitle">Suppliers and customers, sorted by highest balance due.</div>
  </div>
  <a href="/parties/add.php" class="btn btn-accent">+ Add party</a>
</div>

<div class="btn-group mb-3">
  <a href="/parties/index.php" class="btn btn-sm <?= !$type ? 'btn-accent' : 'btn-outline-secondary' ?>">All</a>
  <a href="/parties/index.php?type=supplier" class="btn btn-sm <?= $type === 'supplier' ? 'btn-accent' : 'btn-outline-secondary' ?>">Suppliers</a>
  <a href="/parties/index.php?type=customer" class="btn btn-sm <?= $type === 'customer' ? 'btn-accent' : 'btn-outline-secondary' ?>">Customers</a>
</div>

<div class="card">
  <table class="table mb-0">
    <thead>
      <tr><th>Name</th><th>Type</th><th>Phone</th><th>Total Transacted</th><th>Balance</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($parties as $p): $balance = $p['balance']; ?>
      <tr>
        <td><a href="/parties/view.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a><?php if ($p['business_name']): ?><div class="small text-muted"><?= e($p['business_name']) ?></div><?php endif; ?></td>
        <td><span class="badge text-bg-light border"><?= e(ucfirst($p['type'])) ?></span></td>
        <td><?= e($p['phone']) ?></td>
        <td><?= format_currency($p['total_transacted']) ?></td>
        <td>
          <?php if (abs($balance) < 0.005): ?>
            <span class="text-muted">Settled</span>
          <?php elseif ($balance > 0): ?>
            <span class="text-danger fw-semibold"><?= format_currency($balance) ?> <span class="small">owes shop</span></span>
          <?php else: ?>
            <span class="fw-semibold" style="color:#9a6a00;"><?= format_currency(-$balance) ?> <span class="small">shop owes</span></span>
          <?php endif; ?>
        </td>
        <td class="text-end">
          <a href="/parties/view.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary">Ledger</a>
          <a href="/parties/edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$parties): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">No parties yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
