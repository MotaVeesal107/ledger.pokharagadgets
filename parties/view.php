<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/parties.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM parties WHERE id = ?');
$stmt->execute([$id]);
$party = $stmt->fetch();
if (!$party) {
    flash_set('error', 'Party not found.');
    redirect('/parties/index.php');
}

$balance = get_party_balance($pdo, $id);
$transactions = get_party_transactions($pdo, $id);
$isCustomer = $party['type'] === 'customer';

$pageTitle = $party['name'];
$active = 'parties';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4"><?= e($party['name']) ?> <span class="badge text-bg-light border"><?= e(ucfirst($party['type'])) ?></span></div>
    <div class="page-subtitle"><?= e($party['phone']) ?> <?= $party['address'] ? '· ' . e($party['address']) : '' ?></div>
  </div>
  <div>
    <a href="/parties/edit.php?id=<?= $id ?>" class="btn btn-outline-secondary">Edit</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card p-3 stat-card">
      <div class="stat-label">Current balance</div>
      <?php if (abs($balance) < 0.005): ?>
        <div class="stat-value text-muted">Settled</div>
      <?php elseif ($balance > 0): ?>
        <div class="stat-value text-danger"><?= format_currency($balance) ?></div>
        <div class="small text-muted"><?= e($party['name']) ?> owes the shop</div>
      <?php else: ?>
        <div class="stat-value" style="color:#9a6a00;"><?= format_currency(-$balance) ?></div>
        <div class="small text-muted">Shop owes <?= e($party['name']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card p-3">
      <div class="stat-label mb-2">Record a standalone payment</div>
      <form method="post" action="/parties/payment.php" class="row g-2" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="party_id" value="<?= $id ?>">
        <div class="col-sm-2">
          <select name="direction" class="form-select form-select-sm">
            <?php if ($isCustomer): ?>
              <option value="received_from_customer">Received from customer</option>
            <?php else: ?>
              <option value="paid_to_supplier">Paid to supplier</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-sm-2"><input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" placeholder="Amount" required></div>
        <div class="col-sm-2">
          <select name="method" class="form-select form-select-sm">
            <option value="cash">Cash</option>
            <option value="esewa">eSewa</option>
            <option value="khalti">Khalti</option>
            <option value="fonepay">Fonepay</option>
            <option value="bank">Bank</option>
          </select>
        </div>
        <div class="col-sm-2"><input type="date" name="payment_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-sm-2"><input type="text" name="note" class="form-control form-control-sm" placeholder="Note (optional)"></div>
        <div class="col-sm-1"><input type="file" name="receipt" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.pdf" title="Receipt photo (optional)"></div>
        <div class="col-sm-1"><button class="btn btn-accent btn-sm w-100">Save</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="p-3 border-bottom fw-semibold">Ledger</div>
  <table class="table mb-0">
    <thead>
      <tr><th>Date</th><th>Type</th><th>Description</th><th class="text-end">Effect</th><th class="text-end">Balance</th></tr>
    </thead>
    <tbody>
      <?php foreach ($transactions as $t): ?>
      <tr>
        <td><?= e($t['date']) ?></td>
        <td><span class="badge text-bg-light border"><?= e(ucfirst($t['type'])) ?></span></td>
        <td>
          <?= e($t['description']) ?>
          <?php if (!empty($t['receipt_path'])): ?>
            <a href="/<?= e($t['receipt_path']) ?>" target="_blank" class="small ms-1">[Receipt]</a>
          <?php endif; ?>
        </td>
        <td class="text-end"><?= $t['effect'] >= 0 ? '+' : '' ?><?= format_currency($t['effect']) ?></td>
        <td class="text-end fw-semibold"><?= format_currency($t['balance_after']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$transactions): ?>
      <tr><td colspan="5" class="text-center text-muted py-4">No transactions yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
