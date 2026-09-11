<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$users = $pdo->query('SELECT * FROM users ORDER BY name')->fetchAll();

$pageTitle = 'Users';
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4">Users</div>
    <div class="page-subtitle">Staff and admin login accounts.</div>
  </div>
  <a href="/users/add.php" class="btn btn-accent">+ Add user</a>
</div>

<div class="card">
  <table class="table mb-0">
    <thead>
      <tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="badge <?= $u['role'] === 'admin' ? 'text-bg-dark' : 'text-bg-secondary' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
        <td class="text-end">
          <a href="/users/edit.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
          <?php if ((int)$u['id'] !== (int)current_user()['id']): ?>
          <form method="post" action="/users/delete.php" class="d-inline" onsubmit="return confirm('Delete this user?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
