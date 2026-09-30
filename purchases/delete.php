<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/purchases.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/purchases/index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
try {
    delete_purchase($pdo, $id);
    flash_set('success', 'Purchase deleted and its stock reversed.');
    redirect('/purchases/index.php');
} catch (InvalidArgumentException $e) {
    flash_set('error', $e->getMessage());
    redirect('/purchases/view.php?id=' . $id);
}
