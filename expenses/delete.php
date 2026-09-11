<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/expenses/index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
flash_set('success', 'Expense deleted.');
redirect('/expenses/index.php');
