<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/products/index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
try {
    $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    flash_set('success', 'Product deleted.');
} catch (PDOException $e) {
    flash_set('error', 'This product has purchase, sale or stock history and cannot be deleted.');
}
redirect('/products/index.php');
