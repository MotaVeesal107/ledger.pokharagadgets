<?php
require_once __DIR__ . '/functions.php';

/**
 * An account's balance = opening_balance
 *   + money received into it (sale_payments not method='due', party_payments received_from_customer)
 *   - money paid out of it (party_payments paid_to_supplier, expenses)
 * Only rows that were actually tagged with this account_id count — anything
 * left unassigned (account_id NULL) never affects any account's balance.
 */
function get_account_balance(PDO $pdo, $accountId) {
    $stmt = $pdo->prepare('SELECT opening_balance FROM accounts WHERE id = ?');
    $stmt->execute([$accountId]);
    $balance = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM sale_payments WHERE account_id = ? AND method != 'due'");
    $stmt->execute([$accountId]);
    $balance += (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM party_payments WHERE account_id = ? AND direction = 'received_from_customer'");
    $stmt->execute([$accountId]);
    $balance += (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM party_payments WHERE account_id = ? AND direction = 'paid_to_supplier'");
    $stmt->execute([$accountId]);
    $balance -= (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE account_id = ?');
    $stmt->execute([$accountId]);
    $balance -= (float)$stmt->fetchColumn();

    return round($balance, 2);
}

/** All accounts with their computed balance, for the list view and dashboard total. */
function get_accounts_with_balance(PDO $pdo) {
    $accounts = $pdo->query('SELECT * FROM accounts ORDER BY name')->fetchAll();
    foreach ($accounts as &$account) {
        $account['balance'] = get_account_balance($pdo, $account['id']);
    }
    unset($account);
    return $accounts;
}

function get_total_cash_bank_balance(PDO $pdo) {
    $total = 0.0;
    foreach (get_accounts_with_balance($pdo) as $account) {
        $total += $account['balance'];
    }
    return round($total, 2);
}

/** Chronological ledger rows for one account with a running balance. */
function get_account_transactions(PDO $pdo, $accountId) {
    $rows = [];

    $stmt = $pdo->prepare(
        "SELECT sp.id, s.sale_date AS txn_date, s.invoice_no, sp.method, sp.amount
         FROM sale_payments sp JOIN sales s ON s.id = sp.sale_id
         WHERE sp.account_id = ? AND sp.method != 'due'"
    );
    $stmt->execute([$accountId]);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'date' => $r['txn_date'],
            'description' => "Sale {$r['invoice_no']} ({$r['method']})",
            'effect' => (float)$r['amount'],
            'sort_id' => 's' . $r['id'],
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT pp.id, pp.payment_date AS txn_date, pp.direction, pp.amount, pa.name AS party_name
         FROM party_payments pp JOIN parties pa ON pa.id = pp.party_id
         WHERE pp.account_id = ?"
    );
    $stmt->execute([$accountId]);
    foreach ($stmt->fetchAll() as $r) {
        $isReceived = $r['direction'] === 'received_from_customer';
        $rows[] = [
            'date' => $r['txn_date'],
            'description' => ($isReceived ? 'Received from ' : 'Paid to ') . $r['party_name'],
            'effect' => $isReceived ? (float)$r['amount'] : -1 * (float)$r['amount'],
            'sort_id' => 'y' . $r['id'],
        ];
    }

    $stmt = $pdo->prepare('SELECT id, expense_date AS txn_date, category, amount FROM expenses WHERE account_id = ?');
    $stmt->execute([$accountId]);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'date' => $r['txn_date'],
            'description' => 'Expense: ' . ucfirst($r['category']),
            'effect' => -1 * (float)$r['amount'],
            'sort_id' => 'e' . $r['id'],
        ];
    }

    usort($rows, function ($a, $b) {
        return $a['date'] === $b['date'] ? $a['sort_id'] <=> $b['sort_id'] : strcmp($a['date'], $b['date']);
    });

    $stmt = $pdo->prepare('SELECT opening_balance FROM accounts WHERE id = ?');
    $stmt->execute([$accountId]);
    $running = (float)$stmt->fetchColumn();

    foreach ($rows as &$row) {
        $running += $row['effect'];
        $row['balance_after'] = round($running, 2);
    }
    unset($row);

    return $rows;
}
