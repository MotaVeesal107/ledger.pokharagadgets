<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id === (int)current_user()['id']) {
    flash_set('error', "You can't delete your own account.");
    redirect('/users/index.php');
}

try {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    flash_set('success', 'User deleted.');
} catch (PDOException $e) {
    flash_set('error', 'Could not delete this user — they have created records in the system.');
}
redirect('/users/index.php');
