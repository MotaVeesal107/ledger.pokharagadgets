<?php
/**
 * Shared page chrome. Include after require_login() and after setting
 * $pageTitle (required) and optionally $pageSubtitle and $active (nav key).
 */
$active = $active ?? '';
$user = current_user();
$__shopSettings = get_settings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? APP_NAME) ?> · <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>" rel="stylesheet">
<script>
  function openMobileMenu() {
    document.querySelector('.sidebar').classList.add('open');
    document.querySelector('.sidebar-backdrop').classList.add('show');
    document.getElementById('mobile-topbar').style.visibility = 'hidden';
  }
  function closeMobileMenu() {
    document.querySelector('.sidebar').classList.remove('open');
    document.querySelector('.sidebar-backdrop').classList.remove('show');
    document.getElementById('mobile-topbar').style.visibility = '';
  }
</script>
</head>
<body>
<div class="mobile-topbar no-print" id="mobile-topbar">
  <button type="button" class="menu-toggle" onclick="openMobileMenu()" aria-label="Open menu">☰</button>
  <span class="mobile-brand">
    <?php if (!empty($__shopSettings['logo_path'])): ?><img src="/<?= e($__shopSettings['logo_path']) ?>" alt="" class="mobile-logo">
    <?php else: ?><span class="swatch"></span><?php endif; ?>
    <?= e(APP_NAME) ?>
  </span>
</div>
<div class="sidebar-backdrop no-print" onclick="closeMobileMenu()"></div>
<div class="app-shell">
  <nav class="sidebar no-print">
    <button type="button" class="sidebar-close no-print" onclick="closeMobileMenu()" aria-label="Close menu">&times;</button>
    <div class="brand">
      <?php if (!empty($__shopSettings['logo_path'])): ?>
        <img src="/<?= e($__shopSettings['logo_path']) ?>" alt="<?= e(APP_NAME) ?>" class="brand-logo">
      <?php else: ?>
        <span class="swatch"></span>
      <?php endif; ?>
      <?= e(APP_NAME) ?>
    </div>

    <div class="nav-section-label">Manage</div>
    <a class="nav-link<?= $active === 'dashboard' ? ' active' : '' ?>" href="/dashboard/index.php">Dashboard</a>
    <a class="nav-link<?= $active === 'products' ? ' active' : '' ?>" href="/products/index.php">Products</a>
    <a class="nav-link<?= $active === 'brands' ? ' active' : '' ?>" href="/brands/index.php">Brands</a>
    <a class="nav-link<?= $active === 'models' ? ' active' : '' ?>" href="/models/index.php">Models</a>
    <a class="nav-link<?= $active === 'parties' ? ' active' : '' ?>" href="/parties/index.php">Parties</a>
    <a class="nav-link<?= $active === 'purchases' ? ' active' : '' ?>" href="/purchases/index.php">Purchases</a>
    <a class="nav-link<?= $active === 'sales' ? ' active' : '' ?>" href="/sales/index.php">Sales</a>
    <a class="nav-link<?= $active === 'returns' ? ' active' : '' ?>" href="/returns/index.php">Returns</a>
    <a class="nav-link<?= $active === 'expenses' ? ' active' : '' ?>" href="/expenses/index.php">Expenses</a>
    <a class="nav-link<?= $active === 'income' ? ' active' : '' ?>" href="/income/index.php">Other Income</a>
    <a class="nav-link<?= $active === 'accounts' ? ' active' : '' ?>" href="/accounts/index.php">Accounts</a>
    <a class="nav-link<?= $active === 'reports' ? ' active' : '' ?>" href="/reports/index.php">Reports</a>

    <?php if (is_admin()): ?>
    <div class="nav-section-label">Admin</div>
    <a class="nav-link<?= $active === 'settings' ? ' active' : '' ?>" href="/settings/index.php">Shop Settings</a>
    <a class="nav-link<?= $active === 'users' ? ' active' : '' ?>" href="/users/index.php">Users</a>
    <?php endif; ?>

    <div class="nav-section-label">Account</div>
    <div class="px-3 small text-muted mb-1"><?= e($user['name']) ?> · <?= e(ucfirst($user['role'])) ?></div>
    <a class="nav-link" href="/logout.php">Sign out</a>
  </nav>

  <main class="main-content">
    <?php foreach (flash_get_all() as $flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : e($flash['type']) ?> no-print"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
