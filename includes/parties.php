<?php
/**
 * Party balance convention (used everywhere):
 *   positive = the party owes the shop money (receivable)
 *   negative = the shop owes the party money (payable)
 *
 * A sale's "due" (unpaid, method='due') portion is what increases a
 * customer's balance — the cash/eSewa/Khalti/Fonepay/Bank portions were
 * already settled at the time of sale and do not touch the balance.
 * A purchase's full total increases what the shop owes a supplier.
 * Standalone party_payments settle balances directly.
 */

function get_party_balance(PDO $pdo, $party_id) {
    $stmt = $pdo->prepare('SELECT opening_balance FROM parties WHERE id = ?');
    $stmt->execute([$party_id]);
    $balance = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(sp.amount), 0) FROM sale_payments sp
         JOIN sales s ON s.id = sp.sale_id
         WHERE s.party_id = ? AND sp.method = 'due'"
    );
    $stmt->execute([$party_id]);
    $balance += (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(total), 0) FROM purchases WHERE party_id = ?');
    $stmt->execute([$party_id]);
    $balance -= (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM party_payments WHERE party_id = ? AND direction = 'received_from_customer'"
    );
    $stmt->execute([$party_id]);
    $balance -= (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM party_payments WHERE party_id = ? AND direction = 'paid_to_supplier'"
    );
    $stmt->execute([$party_id]);
    $balance += (float)$stmt->fetchColumn();

    return round($balance, 2);
}

/** All parties of a type with balance + total transacted amount, for the list view. */
function get_parties_with_balance(PDO $pdo, $type = null) {
    $where = $type ? 'WHERE type = ?' : '';
    $params = $type ? [$type] : [];
    $stmt = $pdo->prepare("SELECT * FROM parties $where ORDER BY name");
    $stmt->execute($params);
    $parties = $stmt->fetchAll();

    foreach ($parties as &$party) {
        $party['balance'] = get_party_balance($pdo, $party['id']);
        if ($party['type'] === 'customer') {
            $stmt2 = $pdo->prepare('SELECT COALESCE(SUM(total), 0) FROM sales WHERE party_id = ?');
        } else {
            $stmt2 = $pdo->prepare('SELECT COALESCE(SUM(total), 0) FROM purchases WHERE party_id = ?');
        }
        $stmt2->execute([$party['id']]);
        $party['total_transacted'] = (float)$stmt2->fetchColumn();
    }
    unset($party);

    return $parties;
}

/** Chronological ledger rows for a party with a running balance. */
function get_party_transactions(PDO $pdo, $party_id) {
    $rows = [];

    $stmt = $pdo->prepare(
        "SELECT s.id, s.sale_date AS txn_date, s.invoice_no, s.total,
                COALESCE((SELECT SUM(amount) FROM sale_payments WHERE sale_id = s.id AND method = 'due'), 0) AS due_amount
         FROM sales s WHERE s.party_id = ?"
    );
    $stmt->execute([$party_id]);
    foreach ($stmt->fetchAll() as $s) {
        $rows[] = [
            'date' => $s['txn_date'],
            'type' => 'sale',
            'ref' => $s['invoice_no'],
            'description' => "Sale {$s['invoice_no']} (Total " . format_currency($s['total']) . ', Due ' . format_currency($s['due_amount']) . ')',
            'effect' => (float)$s['due_amount'],
            'sort_id' => 's' . $s['id'],
        ];
    }

    $stmt = $pdo->prepare('SELECT id, purchase_date AS txn_date, bill_ref, total FROM purchases WHERE party_id = ?');
    $stmt->execute([$party_id]);
    foreach ($stmt->fetchAll() as $p) {
        $ref = $p['bill_ref'] ?: ('#' . $p['id']);
        $rows[] = [
            'date' => $p['txn_date'],
            'type' => 'purchase',
            'ref' => $ref,
            'description' => "Purchase {$ref} (Total " . format_currency($p['total']) . ')',
            'effect' => -1 * (float)$p['total'],
            'sort_id' => 'p' . $p['id'],
        ];
    }

    $stmt = $pdo->prepare('SELECT id, payment_date AS txn_date, direction, amount, method, note FROM party_payments WHERE party_id = ?');
    $stmt->execute([$party_id]);
    foreach ($stmt->fetchAll() as $pay) {
        $isReceived = $pay['direction'] === 'received_from_customer';
        $label = $isReceived ? 'Payment received' : 'Payment made';
        $method = $pay['method'] ? " via {$pay['method']}" : '';
        $rows[] = [
            'date' => $pay['txn_date'],
            'type' => 'payment',
            'ref' => '#' . $pay['id'],
            'description' => $label . $method . ($pay['note'] ? ' - ' . $pay['note'] : ''),
            'effect' => $isReceived ? -1 * (float)$pay['amount'] : (float)$pay['amount'],
            'sort_id' => 'y' . $pay['id'],
        ];
    }

    usort($rows, function ($a, $b) {
        return $a['date'] === $b['date']
            ? $a['sort_id'] <=> $b['sort_id']
            : strcmp($a['date'], $b['date']);
    });

    $stmt = $pdo->prepare('SELECT opening_balance FROM parties WHERE id = ?');
    $stmt->execute([$party_id]);
    $running = (float)$stmt->fetchColumn();

    foreach ($rows as &$row) {
        $running += $row['effect'];
        $row['balance_after'] = round($running, 2);
    }
    unset($row);

    return $rows;
}

/** Sum of positive customer balances (total money owed to the shop). */
function get_total_customer_dues(PDO $pdo) {
    $stmt = $pdo->query("SELECT id FROM parties WHERE type = 'customer'");
    $total = 0.0;
    foreach ($stmt->fetchAll() as $row) {
        $balance = get_party_balance($pdo, $row['id']);
        if ($balance > 0) {
            $total += $balance;
        }
    }
    return round($total, 2);
}

/** Sum of what the shop owes suppliers. */
function get_total_supplier_dues(PDO $pdo) {
    $stmt = $pdo->query("SELECT id FROM parties WHERE type = 'supplier'");
    $total = 0.0;
    foreach ($stmt->fetchAll() as $row) {
        $balance = get_party_balance($pdo, $row['id']);
        if ($balance < 0) {
            $total += -$balance;
        }
    }
    return round($total, 2);
}
