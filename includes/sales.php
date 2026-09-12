<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/invoice.php';

/**
 * Validates a sale's item rows: product exists, enough stock is available
 * (aggregated across lines of the same product), and for serialized products
 * every serial is currently in_stock and not selected twice.
 * Throws InvalidArgumentException with a user-facing message on failure.
 */
function validate_sale_items(PDO $pdo, array $items) {
    if (empty($items)) {
        throw new InvalidArgumentException('Add at least one item.');
    }

    $qtyByProduct = [];
    $usedSerials = [];

    foreach ($items as $item) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch();
        if (!$product) {
            throw new InvalidArgumentException('Unknown product in sale.');
        }
        $qty = (int)$item['qty'];
        if ($qty < 1) {
            throw new InvalidArgumentException("Quantity must be at least 1 for {$product['name']}.");
        }
        if ((float)$item['sell_price'] < 0) {
            throw new InvalidArgumentException("Sell price cannot be negative for {$product['name']}.");
        }
        $discount = (float)($item['discount_percent'] ?? 0);
        if ($discount < 0 || $discount > 100) {
            throw new InvalidArgumentException("Discount must be between 0 and 100% for {$product['name']}.");
        }

        if ($product['is_serialized']) {
            $serials = array_values(array_filter(array_map('trim', $item['serials'] ?? [])));
            if (count($serials) !== $qty) {
                throw new InvalidArgumentException("Select exactly {$qty} serial/IMEI number(s) for {$product['name']}.");
            }
            foreach ($serials as $serial) {
                $key = $product['id'] . '|' . $serial;
                if (isset($usedSerials[$key])) {
                    throw new InvalidArgumentException("Serial {$serial} for {$product['name']} was selected more than once.");
                }
                $usedSerials[$key] = true;
                $purchaseItem = find_purchase_item_by_serial($pdo, $product['id'], $serial, 'in_stock');
                if (!$purchaseItem) {
                    throw new InvalidArgumentException("Serial {$serial} for {$product['name']} is not currently available to sell.");
                }
            }
        } else {
            $qtyByProduct[$product['id']] = ($qtyByProduct[$product['id']] ?? 0) + $qty;
        }
    }

    foreach ($qtyByProduct as $productId => $qtyNeeded) {
        $available = get_stock($pdo, $productId);
        if ($qtyNeeded > $available) {
            $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            $name = $stmt->fetchColumn();
            throw new InvalidArgumentException("Not enough stock for {$name}: {$qtyNeeded} requested, {$available} available.");
        }
    }
}

/**
 * Validates that split payment rows sum exactly to $total (2dp tolerance),
 * and that a 'due' portion is only used when a party (not a walk-in) is set.
 */
function validate_sale_payments(array $payments, $total, $hasParty) {
    if (empty($payments)) {
        throw new InvalidArgumentException('Add at least one payment row.');
    }
    $sum = 0.0;
    $hasDue = false;
    foreach ($payments as $p) {
        $amount = (float)$p['amount'];
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amounts must be greater than zero.');
        }
        if ($p['method'] === 'due') {
            $hasDue = true;
        }
        $sum += $amount;
    }
    if ($hasDue && !$hasParty) {
        throw new InvalidArgumentException('Select a customer before recording a Due (credit) payment — walk-in sales must be paid in full.');
    }
    if (abs(round($sum, 2) - round($total, 2)) > 0.01) {
        throw new InvalidArgumentException('Payment amounts (' . number_format($sum, 2) . ') must add up to the invoice total (' . number_format($total, 2) . ').');
    }
}

/**
 * Creates a sale (stock-out) with its items, split payments, one
 * stock_movements "out" row per line (or per unit for serialized items),
 * and an atomically-allocated invoice number. Runs inside its own transaction.
 * Returns ['id' => sale id, 'invoice_no' => string].
 */
function create_sale(PDO $pdo, array $data) {
    validate_sale_items($pdo, $data['items']);

    $subtotal = 0.0;
    foreach ($data['items'] as $item) {
        $subtotal += apply_discount((int)$item['qty'] * (float)$item['sell_price'], $item['discount_percent'] ?? 0);
    }
    $vatEnabled = !empty($data['vat_enabled']);
    $vatAmount = $vatEnabled ? round($subtotal * ((float)$data['vat_rate'] / 100), 2) : 0.0;
    $total = round($subtotal + $vatAmount, 2);

    validate_sale_payments($data['payments'], $total, !empty($data['party_id']));

    $pdo->beginTransaction();
    try {
        $year = (int)date('Y', strtotime($data['sale_date']));
        $invoiceNo = next_invoice_no($pdo, $year);

        $stmt = $pdo->prepare(
            'INSERT INTO sales (invoice_no, party_id, walkin_name, sale_date, subtotal, vat_amount, total, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $invoiceNo,
            $data['party_id'] ?: null,
            $data['party_id'] ? null : ($data['walkin_name'] ?: null),
            $data['sale_date'],
            $subtotal,
            $vatAmount,
            $total,
            $data['note'] ?: null,
            $data['created_by'],
        ]);
        $saleId = (int)$pdo->lastInsertId();

        foreach ($data['items'] as $item) {
            $productId = (int)$item['product_id'];
            $sellPrice = (float)$item['sell_price'];
            $discount = (float)($item['discount_percent'] ?? 0);
            $serials = array_values(array_filter(array_map('trim', $item['serials'] ?? [])));

            if (!empty($serials)) {
                $unitTotal = apply_discount($sellPrice, $discount);
                foreach ($serials as $serial) {
                    $purchaseItem = find_purchase_item_by_serial($pdo, $productId, $serial, 'in_stock');
                    $stmt = $pdo->prepare(
                        'INSERT INTO sale_items (sale_id, product_id, qty, sell_price, discount_percent, serial_no, line_total)
                         VALUES (?, ?, 1, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$saleId, $productId, $sellPrice, $discount, $serial, $unitTotal]);
                    record_stock_movement($pdo, $productId, 'out', 1, 'sale', $saleId, $data['sale_date'], "Serial {$serial}");
                    set_purchase_item_status($pdo, $purchaseItem['id'], 'sold');
                }
            } else {
                $qty = (int)$item['qty'];
                $lineTotal = apply_discount($qty * $sellPrice, $discount);
                $stmt = $pdo->prepare(
                    'INSERT INTO sale_items (sale_id, product_id, qty, sell_price, discount_percent, serial_no, line_total)
                     VALUES (?, ?, ?, ?, ?, NULL, ?)'
                );
                $stmt->execute([$saleId, $productId, $qty, $sellPrice, $discount, $lineTotal]);
                record_stock_movement($pdo, $productId, 'out', $qty, 'sale', $saleId, $data['sale_date']);
            }
        }

        foreach ($data['payments'] as $payment) {
            $stmt = $pdo->prepare('INSERT INTO sale_payments (sale_id, method, amount) VALUES (?, ?, ?)');
            $stmt->execute([$saleId, $payment['method'], (float)$payment['amount']]);
        }

        $pdo->commit();
        return ['id' => $saleId, 'invoice_no' => $invoiceNo];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
