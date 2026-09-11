<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$targetUser = $stmt->fetch();
if (!$targetUser) {
    flash_set('error', 'User not found.');
    redirect('/users/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] === 'admin' ? 'admin' : 'staff';
    $password = $_POST['password'] ?? '';

    if ((int)$targetUser['id'] === (int)current_user()['id'] && $role !== 'admin') {
        $error = "You can't remove your own admin role.";
    } elseif ($name === '' || $email === '') {
        $error = 'Name and email are required.';
    } else {
        try {
            if ($password !== '') {
                if (strlen($password) < 6) {
                    throw new InvalidArgumentException('Password must be at least 6 characters.');
                }
                $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?');
                $stmt->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, role=? WHERE id=?');
                $stmt->execute([$name, $email, $role, $id]);
            }
            flash_set('success', 'User updated.');
            redirect('/users/index.php');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $error = 'That email is already in use.';
        }
    }
}

$pageTitle = 'Edit User';
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar"><div class="page-title h4">Edit User</div></div>
<div class="card p-4" style="max-width:480px;">
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? $targetUser['name']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $targetUser['email']) ?>" required></div>
    <div class="mb-3"><label class="form-label">New password</label>
      <input type="password" name="password" class="form-control" minlength="6" placeholder="Leave blank to keep current password"></div>
    <div class="mb-3"><label class="form-label">Role</label>
      <select name="role" class="form-select" <?= (int)$targetUser['id'] === (int)current_user()['id'] ? 'disabled' : '' ?>>
        <option value="staff" <?= $targetUser['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
        <option value="admin" <?= $targetUser['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>
      <?php if ((int)$targetUser['id'] === (int)current_user()['id']): ?>
      <input type="hidden" name="role" value="admin">
      <div class="form-text">You can't change your own role.</div>
      <?php endif; ?>
    </div>
    <button class="btn btn-accent">Save changes</button>
    <a href="/users/index.php" class="btn btn-link">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
