<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/expenses/index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$receiptPath = $pdo->prepare('SELECT receipt_path FROM expenses WHERE id = ?');
$receiptPath->execute([$id]);
$receipt = $receiptPath->fetchColumn();

$pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
delete_receipt_file($receipt ?: null);
flash_set('success', 'Expense deleted.');
redirect('/expenses/index.php');
