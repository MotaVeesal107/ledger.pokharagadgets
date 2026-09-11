<?php
/**
 * Stock is NEVER stored on products. It is always derived from
 * stock_movements ("in" minus "out"). Every function here reads, none writes
 * a quantity anywhere other than stock_movements itself.
 */

function get_stock(PDO $pdo, $product_id) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN qty ELSE -qty END), 0) AS stock
         FROM stock_movements WHERE product_id = ?"
    );
    $stmt->execute([$product_id]);
    return (int)$stmt->fetchColumn();
}

/** Returns [product_id => stock] for every product in one query. */
function get_all_stock(PDO $pdo) {
    $stmt = $pdo->query(
        "SELECT product_id, SUM(CASE WHEN type = 'in' THEN qty ELSE -qty END) AS stock
         FROM stock_movements GROUP BY product_id"
    );
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int)$row['product_id']] = (int)$row['stock'];
    }
    return $out;
}

/** Products joined with their live computed stock, one query. */
function get_products_with_stock(PDO $pdo, $extra_where = '', $params = []) {
    $sql = "SELECT p.*,
                   COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.qty WHEN sm.type = 'out' THEN -sm.qty ELSE 0 END), 0) AS stock
            FROM products p
            LEFT JOIN stock_movements sm ON sm.product_id = p.id
            $extra_where
            GROUP BY p.id
            ORDER BY p.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function record_stock_movement(PDO $pdo, $product_id, $type, $qty, $ref_type, $ref_id, $movement_date, $note = null) {
    $stmt = $pdo->prepare(
        'INSERT INTO stock_movements (product_id, type, qty, ref_type, ref_id, movement_date, note)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$product_id, $type, $qty, $ref_type, $ref_id, $movement_date, $note]);
}

/** Serial numbers of a product currently available to sell. */
function get_available_serials(PDO $pdo, $product_id) {
    $stmt = $pdo->prepare(
        "SELECT id, serial_no FROM purchase_items
         WHERE product_id = ? AND serial_no IS NOT NULL AND status = 'in_stock'
         ORDER BY serial_no"
    );
    $stmt->execute([$product_id]);
    return $stmt->fetchAll();
}

function find_purchase_item_by_serial(PDO $pdo, $product_id, $serial_no, $status = null) {
    $sql = 'SELECT * FROM purchase_items WHERE product_id = ? AND serial_no = ?';
    $params = [$product_id, $serial_no];
    if ($status !== null) {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function set_purchase_item_status(PDO $pdo, $purchase_item_id, $status) {
    $stmt = $pdo->prepare('UPDATE purchase_items SET status = ? WHERE id = ?');
    $stmt->execute([$status, $purchase_item_id]);
}
