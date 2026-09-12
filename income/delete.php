<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/income/index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT receipt_path FROM other_income WHERE id = ?');
$stmt->execute([$id]);
$receipt = $stmt->fetchColumn();

$pdo->prepare('DELETE FROM other_income WHERE id = ?')->execute([$id]);
delete_receipt_file($receipt ?: null);
flash_set('success', 'Income entry deleted.');
redirect('/income/index.php');
