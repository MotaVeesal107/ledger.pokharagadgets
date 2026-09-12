<?php
/** Shared helpers used across every page. */

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_currency($amount) {
    return CURRENCY_PREFIX . number_format((float)$amount, 2);
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function flash_set($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

/** Parses free-text warranty periods like "6 months", "1 year", "30 days". */
function warranty_expiry_date($warranty_period, $from_date) {
    if (!$warranty_period || !$from_date) {
        return null;
    }
    if (!preg_match('/(\d+)\s*(day|month|year)/i', $warranty_period, $m)) {
        return null;
    }
    $expiry = strtotime("+{$m[1]} {$m[2]}", strtotime($from_date));
    return $expiry ? date('Y-m-d', $expiry) : null;
}

/** Applies a percentage discount to an amount, rounded to 2dp. */
function apply_discount($amount, $discountPercent) {
    return round((float)$amount * (1 - (float)$discountPercent / 100), 2);
}

/**
 * Builds an auto-generated SKU once a product's id is known, e.g.
 * "Mobile Cover" + id 123 -> "MOB-00123". Deterministic and unique by
 * construction (the id alone is unique), so no separate counter is needed.
 */
function generate_sku($category, $id) {
    $letters = preg_replace('/[^A-Za-z]/', '', (string)$category);
    $prefix = $letters !== '' ? strtoupper(substr($letters, 0, 3)) : 'GEN';
    return $prefix . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

function get_settings(PDO $pdo) {
    static $settings = null;
    if ($settings === null) {
        $stmt = $pdo->query('SELECT * FROM settings WHERE id = 1');
        $settings = $stmt->fetch();
    }
    return $settings;
}
