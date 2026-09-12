<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/parties/index.php');
}
verify_csrf();

$partyId = (int)($_POST['party_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM parties WHERE id = ?');
$stmt->execute([$partyId]);
$party = $stmt->fetch();
if (!$party) {
    flash_set('error', 'Party not found.');
    redirect('/parties/index.php');
}

$amount = (float)($_POST['amount'] ?? 0);
$direction = $party['type'] === 'customer' ? 'received_from_customer' : 'paid_to_supplier';
$method = trim($_POST['method'] ?? '') ?: null;
$date = $_POST['payment_date'] ?? date('Y-m-d');
$note = trim($_POST['note'] ?? '') ?: null;

if ($amount <= 0) {
    flash_set('error', 'Payment amount must be greater than zero.');
    redirect('/parties/view.php?id=' . $partyId);
}

try {
    $receiptPath = save_receipt_upload('receipt');
} catch (InvalidArgumentException $e) {
    flash_set('error', $e->getMessage());
    redirect('/parties/view.php?id=' . $partyId);
}

$stmt = $pdo->prepare(
    'INSERT INTO party_payments (party_id, direction, amount, method, payment_date, note, receipt_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$partyId, $direction, $amount, $method, $date, $note, $receiptPath, current_user()['id']]);

flash_set('success', 'Payment recorded.');
redirect('/parties/view.php?id=' . $partyId);
