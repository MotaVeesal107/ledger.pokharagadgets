<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/brands/index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT photo_path FROM brands WHERE id = ?');
$stmt->execute([$id]);
$photoPath = $stmt->fetchColumn();

$pdo->prepare('DELETE FROM brands WHERE id = ?')->execute([$id]);
delete_receipt_file($photoPath);

flash_set('success', 'Brand entry deleted. Products keep their brand text.');
redirect('/brands/index.php');
