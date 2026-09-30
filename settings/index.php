<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_admin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $settings = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
        $logoPath = $settings['logo_path'];
        if (!empty($_POST['remove_logo'])) {
            delete_receipt_file($logoPath);
            $logoPath = null;
        } else {
            $newLogo = save_logo_upload('logo');
            if ($newLogo) {
                delete_receipt_file($logoPath);
                $logoPath = $newLogo;
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE settings SET shop_name = ?, logo_path = ?, pan_number = ?, address = ?, phone = ?,
             vat_enabled = ?, vat_rate = ?, show_cost_to_staff = ? WHERE id = 1'
        );
        $stmt->execute([
            trim($_POST['shop_name']) ?: 'Pokhara Gadgets',
            $logoPath,
            trim($_POST['pan_number']) ?: null,
            trim($_POST['address']) ?: null,
            trim($_POST['phone']) ?: null,
            isset($_POST['vat_enabled']) ? 1 : 0,
            (float)$_POST['vat_rate'],
            isset($_POST['show_cost_to_staff']) ? 1 : 0,
        ]);
        flash_set('success', 'Settings saved.');
        redirect('/settings/index.php');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$settings = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();

$pageTitle = 'Shop Settings';
$active = 'settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Shop Settings</div>
    <div class="page-subtitle">Shop identity, PAN/VAT and staff permissions.</div>
  </div>
</div>

<div class="card p-4" style="max-width:640px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Shop name</label>
      <input type="text" name="shop_name" class="form-control" value="<?= e($settings['shop_name']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Shop logo</label>
      <?php if (!empty($settings['logo_path'])): ?>
        <div class="mb-2 d-flex align-items-center gap-2">
          <img src="/<?= e($settings['logo_path']) ?>" alt="Current logo" style="height:48px;border-radius:4px;border:1px solid #ddd;">
          <label class="small text-danger"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label>
        </div>
      <?php endif; ?>
      <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="form-control">
      <div class="form-text">Shown in the sidebar and on printed invoices. JPG, PNG, or WEBP, up to 2MB.</div>
    </div>
    <div class="mb-3">
      <label class="form-label">PAN number</label>
      <input type="text" name="pan_number" class="form-control" value="<?= e($settings['pan_number']) ?>" placeholder="Prints on every bill">
    </div>
    <div class="mb-3">
      <label class="form-label">Address</label>
      <input type="text" name="address" class="form-control" value="<?= e($settings['address']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Phone</label>
      <input type="text" name="phone" class="form-control" value="<?= e($settings['phone']) ?>">
    </div>

    <hr>
    <div class="form-check mb-2">
      <input type="checkbox" class="form-check-input" id="vat_enabled" name="vat_enabled" <?= $settings['vat_enabled'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="vat_enabled">VAT registered (enable VAT on sales)</label>
    </div>
    <div class="mb-3">
      <label class="form-label">VAT rate (%)</label>
      <input type="number" step="0.01" name="vat_rate" class="form-control" style="max-width:150px;" value="<?= e($settings['vat_rate']) ?>">
      <div class="form-text">Off by default since the shop is PAN-registered, not VAT-registered. Bills print "Bill No." until this is on, then switch to "Tax Invoice".</div>
    </div>

    <hr>
    <div class="form-check mb-3">
      <input type="checkbox" class="form-check-input" id="show_cost_to_staff" name="show_cost_to_staff" <?= $settings['show_cost_to_staff'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="show_cost_to_staff">Allow staff to view cost prices &amp; financial figures</label>
      <div class="form-text">Off by default. Admins can always see cost prices and financial reports.</div>
    </div>

    <button type="submit" class="btn btn-accent">Save settings</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
