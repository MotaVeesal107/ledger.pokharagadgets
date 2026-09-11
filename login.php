<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user'])) {
    redirect('/dashboard/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (attempt_login($pdo, $email, $password)) {
        redirect('/dashboard/index.php');
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in · <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center" style="min-height:100vh;">
<div class="container" style="max-width: 380px;">
  <div class="text-center mb-4">
    <span style="display:inline-block;width:2rem;height:2rem;border-radius:8px;background:#1a5632;vertical-align:middle;"></span>
    <strong style="font-size:1.3rem;vertical-align:middle;"> <?= APP_NAME ?></strong>
  </div>
  <div class="card p-4">
    <h5 class="mb-1">Sign in</h5>
    <p class="text-muted small mb-3">Enter your credentials to access the inventory.</p>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-accent w-100">Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
