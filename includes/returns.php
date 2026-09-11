<?php
require_once __DIR__ . '/stock.php';

/**
 * Records a customer return or a damaged/lost write-off. Both are the only
 * other events (besides purchases and sales) allowed to touch stock_movements,
 * and both are tracked in returns_adjustments so purchase/sale totals in
 * reports stay unaffected by them. Runs inside its own transaction.
 * Returns the new returns_adjustments id.
 */
function create_return_adjustment(PDO $pdo, array $data) {
    $type = $data['type'];
    $qty = (int)$data['qty'];
    $productId = (int)$data['product_id'];
    $serial = $data['serial_no'] ? trim($data['serial_no']) : null;

    if (!in_array($type, ['customer_return', 'damaged', 'lost'], true)) {
        throw new InvalidArgumentException('Invalid adjustment type.');
    }
    if ($qty < 1) {
        throw new InvalidArgumentException('Quantity must be at least 1.');
    }

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        throw new InvalidArgumentException('Unknown product.');
    }
    if ($product['is_serialized'] && !$serial) {
        throw new InvalidArgumentException("Serial/IMEI is required for {$product['name']}.");
    }
    if ($product['is_serialized'] && $qty !== 1) {
        throw new InvalidArgumentException('Serialized items are adjusted one unit at a time.');
    }

    $pdo->beginTransaction();
    try {
        if ($type === 'customer_return') {
            if (empty($data['related_sale_id'])) {
                throw new InvalidArgumentException('Select the original sale for a customer return.');
            }
            $purchaseItem = null;
            if ($serial) {
                $purchaseItem = find_purchase_item_by_serial($pdo, $productId, $serial, 'sold');
                if (!$purchaseItem) {
                    throw new InvalidArgumentException("Serial {$serial} is not currently marked as sold, so it cannot be returned.");
                }
            }
            $restock = !empty($data['restock']);
            if ($restock) {
                record_stock_movement($pdo, $productId, 'in', $qty, 'return', null, $data['adjustment_date'], 'Customer return - restocked');
                if ($purchaseItem) {
                    set_purchase_item_status($pdo, $purchaseItem['id'], 'in_stock');
                }
            } elseif ($purchaseItem) {
                set_purchase_item_status($pdo, $purchaseItem['id'], 'damaged');
            }
        } else {
            // damaged / lost write-off, independent of any sale.
            if (!empty($data['related_sale_id'])) {
                throw new InvalidArgumentException('Damaged/lost write-offs are not tied to a sale.');
            }
            $purchaseItem = null;
            if ($serial) {
                $purchaseItem = find_purchase_item_by_serial($pdo, $productId, $serial, 'in_stock');
                if (!$purchaseItem) {
                    throw new InvalidArgumentException("Serial {$serial} is not currently in stock.");
                }
            } else {
                $available = get_stock($pdo, $productId);
                if ($qty > $available) {
                    throw new InvalidArgumentException("Cannot write off {$qty} units of {$product['name']}: only {$available} in stock.");
                }
            }
            record_stock_movement($pdo, $productId, 'out', $qty, 'adjustment', null, $data['adjustment_date'], ucfirst($type) . ' write-off');
            if ($purchaseItem) {
                set_purchase_item_status($pdo, $purchaseItem['id'], $type);
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO returns_adjustments (product_id, type, qty, serial_no, related_sale_id, restock, adjustment_date, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $productId,
            $type,
            $qty,
            $serial,
            $data['related_sale_id'] ?: null,
            !empty($data['restock']) ? 1 : 0,
            $data['adjustment_date'],
            $data['note'] ?: null,
            $data['created_by'],
        ]);
        $id = (int)$pdo->lastInsertId();

        $pdo->commit();
        return $id;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
