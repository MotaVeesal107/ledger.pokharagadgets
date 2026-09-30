<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/stock.php';

/**
 * Validates a purchase's item rows against product definitions before any
 * writes happen. $items: [['product_id','qty','cost_price','serials'=>[]]]
 * Throws InvalidArgumentException with a user-facing message on failure.
 */
function validate_purchase_items(PDO $pdo, array $items) {
    if (empty($items)) {
        throw new InvalidArgumentException('Add at least one item.');
    }
    foreach ($items as $item) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch();
        if (!$product) {
            throw new InvalidArgumentException('Unknown product in purchase.');
        }
        $qty = (int)$item['qty'];
        if ($qty < 1) {
            throw new InvalidArgumentException("Quantity must be at least 1 for {$product['name']}.");
        }
        if ((float)$item['cost_price'] < 0) {
            throw new InvalidArgumentException("Cost price cannot be negative for {$product['name']}.");
        }
        $discount = (float)($item['discount_percent'] ?? 0);
        if ($discount < 0 || $discount > 100) {
            throw new InvalidArgumentException("Discount must be between 0 and 100% for {$product['name']}.");
        }
        if ($product['is_serialized']) {
            $serials = array_values(array_filter(array_map('trim', $item['serials'] ?? [])));
            if (count($serials) !== $qty) {
                throw new InvalidArgumentException("Enter exactly {$qty} serial/IMEI number(s) for {$product['name']}.");
            }
            if (count($serials) !== count(array_unique($serials))) {
                throw new InvalidArgumentException("Duplicate serial numbers entered for {$product['name']}.");
            }
            foreach ($serials as $serial) {
                $existing = find_purchase_item_by_serial($pdo, $product['id'], $serial);
                if ($existing) {
                    throw new InvalidArgumentException("Serial {$serial} for {$product['name']} has already been purchased before.");
                }
            }
        }
    }
}

/**
 * Creates a purchase (stock-in) with its items, one stock_movements "in" row
 * per line (or per unit for serialized items), and updates the product's
 * reference cost price. Runs inside its own transaction.
 * Returns the new purchase id.
 */
function create_purchase(PDO $pdo, array $data) {
    validate_purchase_items($pdo, $data['items']);

    $pdo->beginTransaction();
    try {
        $subtotal = 0.0;
        foreach ($data['items'] as $item) {
            $subtotal += apply_discount((int)$item['qty'] * (float)$item['cost_price'], $item['discount_percent'] ?? 0);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO purchases (party_id, bill_ref, purchase_date, subtotal, total, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['party_id'],
            $data['bill_ref'] ?: null,
            $data['purchase_date'],
            $subtotal,
            $subtotal,
            $data['note'] ?: null,
            $data['created_by'],
        ]);
        $purchaseId = (int)$pdo->lastInsertId();

        foreach ($data['items'] as $item) {
            $productId = (int)$item['product_id'];
            $costPrice = (float)$item['cost_price'];
            $discount = (float)($item['discount_percent'] ?? 0);
            $serials = array_values(array_filter(array_map('trim', $item['serials'] ?? [])));

            if (!empty($serials)) {
                $unitTotal = apply_discount($costPrice, $discount);
                foreach ($serials as $serial) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO purchase_items (purchase_id, product_id, qty, cost_price, discount_percent, serial_no, line_total, status)
                         VALUES (?, ?, 1, ?, ?, ?, ?, 'in_stock')"
                    );
                    $stmt->execute([$purchaseId, $productId, $costPrice, $discount, $serial, $unitTotal]);
                    record_stock_movement($pdo, $productId, 'in', 1, 'purchase', $purchaseId, $data['purchase_date'], "Serial {$serial}");
                }
            } else {
                $qty = (int)$item['qty'];
                $lineTotal = apply_discount($qty * $costPrice, $discount);
                $stmt = $pdo->prepare(
                    'INSERT INTO purchase_items (purchase_id, product_id, qty, cost_price, discount_percent, serial_no, line_total)
                     VALUES (?, ?, ?, ?, ?, NULL, ?)'
                );
                $stmt->execute([$purchaseId, $productId, $qty, $costPrice, $discount, $lineTotal]);
                record_stock_movement($pdo, $productId, 'in', $qty, 'purchase', $purchaseId, $data['purchase_date']);
            }

            $pdo->prepare('UPDATE products SET cost_price_ref = ? WHERE id = ?')->execute([$costPrice, $productId]);
        }

        $pdo->commit();
        return $purchaseId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Returns a list of human-readable reasons this purchase cannot be edited or
 * deleted right now (empty array = safe to modify). A purchase is locked once
 * any of its stock has moved on: a serialized unit that's been sold, marked
 * damaged/lost, or a non-serialized quantity that's no longer fully in stock.
 */
function can_modify_purchase(PDO $pdo, $purchaseId) {
    $stmt = $pdo->prepare('SELECT * FROM purchase_items WHERE purchase_id = ?');
    $stmt->execute([$purchaseId]);
    $items = $stmt->fetchAll();

    $blockers = [];
    $qtyByProduct = [];

    foreach ($items as $item) {
        if ($item['serial_no'] !== null) {
            if ($item['status'] !== 'in_stock') {
                $blockers[] = "Serial {$item['serial_no']} from this purchase has already been {$item['status']} and can no longer be edited.";
            }
        } else {
            $qtyByProduct[$item['product_id']] = ($qtyByProduct[$item['product_id']] ?? 0) + (int)$item['qty'];
        }
    }

    foreach ($qtyByProduct as $productId => $qty) {
        $currentStock = get_stock($pdo, $productId);
        if ($currentStock - $qty < 0) {
            $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            $name = $stmt->fetchColumn();
            $blockers[] = "Some of the \"{$name}\" stock from this purchase has already been sold (only {$currentStock} left, but this purchase brought in {$qty}), so it can no longer be edited.";
        }
    }

    return $blockers;
}

/**
 * Replaces a purchase's items entirely with new ones, reversing the old
 * stock movements first. Blocked (throws InvalidArgumentException) if any
 * existing item has already moved on — see can_modify_purchase().
 */
function update_purchase(PDO $pdo, $purchaseId, array $data) {
    $blockers = can_modify_purchase($pdo, $purchaseId);
    if ($blockers) {
        throw new InvalidArgumentException(implode(' ', $blockers));
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM stock_movements WHERE ref_type = 'purchase' AND ref_id = ?")->execute([$purchaseId]);
        $pdo->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$purchaseId]);

        validate_purchase_items($pdo, $data['items']);

        $subtotal = 0.0;
        foreach ($data['items'] as $item) {
            $subtotal += apply_discount((int)$item['qty'] * (float)$item['cost_price'], $item['discount_percent'] ?? 0);
        }

        $stmt = $pdo->prepare(
            'UPDATE purchases SET party_id = ?, bill_ref = ?, purchase_date = ?, subtotal = ?, total = ?, note = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['party_id'],
            $data['bill_ref'] ?: null,
            $data['purchase_date'],
            $subtotal,
            $subtotal,
            $data['note'] ?: null,
            $purchaseId,
        ]);

        foreach ($data['items'] as $item) {
            $productId = (int)$item['product_id'];
            $costPrice = (float)$item['cost_price'];
            $discount = (float)($item['discount_percent'] ?? 0);
            $serials = array_values(array_filter(array_map('trim', $item['serials'] ?? [])));

            if (!empty($serials)) {
                $unitTotal = apply_discount($costPrice, $discount);
                foreach ($serials as $serial) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO purchase_items (purchase_id, product_id, qty, cost_price, discount_percent, serial_no, line_total, status)
                         VALUES (?, ?, 1, ?, ?, ?, ?, 'in_stock')"
                    );
                    $stmt->execute([$purchaseId, $productId, $costPrice, $discount, $serial, $unitTotal]);
                    record_stock_movement($pdo, $productId, 'in', 1, 'purchase', $purchaseId, $data['purchase_date'], "Serial {$serial}");
                }
            } else {
                $qty = (int)$item['qty'];
                $lineTotal = apply_discount($qty * $costPrice, $discount);
                $stmt = $pdo->prepare(
                    'INSERT INTO purchase_items (purchase_id, product_id, qty, cost_price, discount_percent, serial_no, line_total)
                     VALUES (?, ?, ?, ?, ?, NULL, ?)'
                );
                $stmt->execute([$purchaseId, $productId, $qty, $costPrice, $discount, $lineTotal]);
                record_stock_movement($pdo, $productId, 'in', $qty, 'purchase', $purchaseId, $data['purchase_date']);
            }

            $pdo->prepare('UPDATE products SET cost_price_ref = ? WHERE id = ?')->execute([$costPrice, $productId]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Deletes a purchase entirely, reversing its stock movements. Blocked
 * (throws InvalidArgumentException) if any item has already moved on — see
 * can_modify_purchase().
 */
function delete_purchase(PDO $pdo, $purchaseId) {
    $blockers = can_modify_purchase($pdo, $purchaseId);
    if ($blockers) {
        throw new InvalidArgumentException(implode(' ', $blockers));
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM stock_movements WHERE ref_type = 'purchase' AND ref_id = ?")->execute([$purchaseId]);
        $pdo->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$purchaseId]);
        $pdo->prepare('DELETE FROM purchases WHERE id = ?')->execute([$purchaseId]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
