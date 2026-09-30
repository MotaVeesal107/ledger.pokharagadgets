<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/categories/index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT photo_path FROM categories WHERE id = ?');
$stmt->execute([$id]);
$photoPath = $stmt->fetchColumn();

$pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
delete_receipt_file($photoPath);

flash_set('success', 'Category entry deleted. Products keep their category text.');
redirect('/categories/index.php');
