<?php
require_once __DIR__ . '/functions.php';

const PRODUCT_IMPORT_COLUMNS = ['name', 'sku', 'barcode', 'category', 'brand', 'device_model', 'unit', 'low_stock_threshold', 'warranty_period', 'is_serialized', 'cost_price_ref', 'sell_price_ref'];
const PRODUCT_IMPORT_MAX_ROWS = 2000;

/** Parses an uploaded product CSV into raw [line, data] rows, keyed by known column names. */
function parse_product_import_csv($tmpPath) {
    $handle = fopen($tmpPath, 'r');
    if (!$handle) {
        throw new RuntimeException('Could not read the uploaded file.');
    }

    $headerLine = fgetcsv($handle);
    if (!$headerLine) {
        fclose($handle);
        throw new RuntimeException('The file appears to be empty.');
    }
    // Strip a UTF-8 BOM Excel sometimes adds to the first cell.
    $headerLine[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine[0]);
    $headers = array_map(fn($h) => strtolower(trim($h)), $headerLine);

    if (!in_array('name', $headers, true)) {
        fclose($handle);
        throw new RuntimeException('The file must have a "name" column. Download the template to see the expected format.');
    }

    $rows = [];
    $lineNo = 1;
    while (($cells = fgetcsv($handle)) !== false) {
        $lineNo++;
        if (count(array_filter($cells, fn($c) => trim((string)$c) !== '')) === 0) {
            continue; // skip blank lines
        }
        if (count($rows) >= PRODUCT_IMPORT_MAX_ROWS) {
            fclose($handle);
            throw new RuntimeException('This file has more than ' . PRODUCT_IMPORT_MAX_ROWS . ' rows. Please split it into smaller files.');
        }
        $data = [];
        foreach ($headers as $i => $col) {
            if (in_array($col, PRODUCT_IMPORT_COLUMNS, true)) {
                $data[$col] = trim($cells[$i] ?? '');
            }
        }
        $rows[] = ['line' => $lineNo, 'data' => $data];
    }
    fclose($handle);
    return $rows;
}

/**
 * Validates and normalizes parsed rows against the database (existing SKUs)
 * and against each other (duplicate SKUs within the same file). Adds
 * 'normalized' (ready-to-insert values) and 'errors' (empty = valid) to
 * each row.
 */
function validate_product_import_rows(PDO $pdo, array $rows) {
    $existingSkus = array_flip(array_map('strtoupper', $pdo->query(
        "SELECT sku FROM products WHERE sku IS NOT NULL AND sku <> ''"
    )->fetchAll(PDO::FETCH_COLUMN)));
    $seenInBatch = [];

    foreach ($rows as &$row) {
        $errors = [];
        $data = $row['data'];
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        $sku = trim($data['sku'] ?? '');
        if ($sku !== '') {
            $skuKey = strtoupper($sku);
            if (isset($existingSkus[$skuKey])) {
                $errors[] = "SKU \"{$sku}\" already exists.";
            } elseif (isset($seenInBatch[$skuKey])) {
                $errors[] = "SKU \"{$sku}\" is duplicated elsewhere in this file (line {$seenInBatch[$skuKey]}).";
            } else {
                $seenInBatch[$skuKey] = $row['line'];
            }
        }

        $isSerialized = in_array(strtolower(trim($data['is_serialized'] ?? '')), ['yes', 'y', 'true', '1'], true);
        $costPrice = is_numeric($data['cost_price_ref'] ?? '') ? (float)$data['cost_price_ref'] : 0;
        $sellPrice = is_numeric($data['sell_price_ref'] ?? '') ? (float)$data['sell_price_ref'] : 0;
        $threshold = is_numeric($data['low_stock_threshold'] ?? '') ? (int)$data['low_stock_threshold'] : 0;

        $row['normalized'] = [
            'name' => $name,
            'sku' => $sku ?: null,
            'barcode' => trim($data['barcode'] ?? '') ?: null,
            'category' => trim($data['category'] ?? '') ?: null,
            'brand' => trim($data['brand'] ?? '') ?: null,
            'device_model' => trim($data['device_model'] ?? '') ?: null,
            'unit' => trim($data['unit'] ?? '') ?: 'pcs',
            'low_stock_threshold' => $threshold,
            'warranty_period' => trim($data['warranty_period'] ?? '') ?: null,
            'is_serialized' => $isSerialized ? 1 : 0,
            'cost_price_ref' => $costPrice,
            'sell_price_ref' => $sellPrice,
        ];
        $row['errors'] = $errors;
    }
    unset($row);

    return $rows;
}

/**
 * Inserts the given (already-validated) normalized rows as products inside
 * one transaction, auto-generating a SKU for any row that didn't have one.
 * Returns the number of products created.
 */
function commit_product_import(PDO $pdo, array $validRows, $includeCost) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO products (name, sku, barcode, category, brand, device_model, unit, low_stock_threshold, warranty_period, is_serialized, cost_price_ref, sell_price_ref)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $skuStmt = $pdo->prepare('UPDATE products SET sku = ? WHERE id = ?');
        $count = 0;
        foreach ($validRows as $row) {
            $n = $row['normalized'];
            $stmt->execute([
                $n['name'], $n['sku'], $n['barcode'], $n['category'], $n['brand'], $n['device_model'],
                $n['unit'], $n['low_stock_threshold'], $n['warranty_period'], $n['is_serialized'],
                $includeCost ? $n['cost_price_ref'] : 0, $n['sell_price_ref'],
            ]);
            if ($n['sku'] === null) {
                $newId = (int)$pdo->lastInsertId();
                $skuStmt->execute([generate_sku($n['category'], $newId), $newId]);
            }
            $count++;
        }
        $pdo->commit();
        return $count;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
