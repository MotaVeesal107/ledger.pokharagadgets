<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] === 'admin' ? 'admin' : 'staff';

    if ($name === '' || $email === '' || strlen($password) < 6) {
        $error = 'Name, email and a password of at least 6 characters are required.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash_set('success', 'User created.');
            redirect('/users/index.php');
        } catch (PDOException $e) {
            $error = 'That email is already in use.';
        }
    }
}

$pageTitle = 'Add User';
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Add User</div></div>
<div class="card p-4" style="max-width:480px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required></div>
    <div class="mb-3"><label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required></div>
    <div class="mb-3"><label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required minlength="6"></div>
    <div class="mb-3"><label class="form-label">Role</label>
      <select name="role" class="form-select">
        <option value="staff">Staff</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <button class="btn btn-accent">Create user</button>
    <a href="/users/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
