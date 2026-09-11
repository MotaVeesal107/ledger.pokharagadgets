<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csv_export.php';
require_login();

$headers = ['name', 'sku', 'barcode', 'category', 'brand', 'device_model', 'unit', 'low_stock_threshold', 'warranty_period', 'is_serialized', 'cost_price_ref', 'sell_price_ref'];
$examples = [
    ['Clear Silicone Case', '', '', 'Mobile Cover', '', 'iPhone 13', 'pcs', 5, '', 'no', 150, 500],
    ['WH-CH510 Headphone', '', '', 'Headphone', 'Sony', '', 'pcs', 3, '6 months', 'no', 1200, 1800],
];

export_csv('product_import_template.csv', $headers, $examples);
