<?php
/**
 * Handles receipt photo uploads (Payments, Expenses). Files are validated by
 * actual content (via finfo), not by client-supplied filename/type, and
 * always saved under a random name with an extension WE choose — an
 * uploaded file can never end up served as .php.
 */

const RECEIPT_UPLOAD_DIR = __DIR__ . '/../uploads/receipts';
const RECEIPT_PUBLIC_DIR = 'uploads/receipts';
const RECEIPT_MAX_BYTES = 5 * 1024 * 1024; // 5MB

const RECEIPT_ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

const LOGO_UPLOAD_DIR = __DIR__ . '/../uploads/logo';
const LOGO_PUBLIC_DIR = 'uploads/logo';
const LOGO_MAX_BYTES = 2 * 1024 * 1024; // 2MB

const PHOTO_UPLOAD_DIR = __DIR__ . '/../uploads/products';
const PHOTO_PUBLIC_DIR = 'uploads/products';
const PHOTO_MAX_BYTES = 5 * 1024 * 1024; // 5MB

const IMAGE_ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

/**
 * Saves an uploaded receipt file from $_FILES[$field] and returns its
 * public relative path (e.g. "uploads/receipts/abc123.jpg"), or null if no
 * file was chosen (receipts are always optional). Throws
 * InvalidArgumentException on an invalid/oversized file.
 */
function save_receipt_upload($field) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('The receipt file failed to upload. Please try again.');
    }
    if ($file['size'] > RECEIPT_MAX_BYTES) {
        throw new InvalidArgumentException('Receipt file is too large (max 5MB).');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(RECEIPT_ALLOWED_MIME[$mime])) {
        throw new InvalidArgumentException('Receipt must be a JPG, PNG, WEBP, or PDF file.');
    }

    if (!is_dir(RECEIPT_UPLOAD_DIR)) {
        mkdir(RECEIPT_UPLOAD_DIR, 0755, true);
    }
    ensure_upload_dir_protected();

    $filename = bin2hex(random_bytes(16)) . '.' . RECEIPT_ALLOWED_MIME[$mime];
    $destination = RECEIPT_UPLOAD_DIR . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new InvalidArgumentException('Could not save the uploaded receipt.');
    }

    return RECEIPT_PUBLIC_DIR . '/' . $filename;
}

/** Deletes a previously stored uploaded file (receipt, logo, or product photo), if it exists. Safe to call with null. */
function delete_receipt_file($publicPath) {
    if (!$publicPath) {
        return;
    }
    $full = __DIR__ . '/../' . $publicPath;
    if (is_file($full)) {
        @unlink($full);
    }
}

/** Writes an .htaccess into an upload directory blocking script execution and directory listing. */
function ensure_upload_dir_protected($dir = RECEIPT_UPLOAD_DIR) {
    $htaccess = $dir . '/.htaccess';
    if (file_exists($htaccess)) {
        return;
    }
    $contents = "Options -Indexes -ExecCGI\n"
        . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
        . "<FilesMatch \"\\.(php|phtml|php\\d?|pl|py|cgi|asp|sh)$\">\n"
        . "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n"
        . "    <IfModule !mod_authz_core.c>\n        Deny from all\n    </IfModule>\n"
        . "</FilesMatch>\n";
    @file_put_contents($htaccess, $contents);
}

/**
 * Saves an uploaded shop logo image and returns its public relative path, or
 * null if no file was chosen. Throws InvalidArgumentException on an
 * invalid/oversized file. Image only (no PDF) — this is displayed inline.
 */
function save_logo_upload($field) {
    return save_image_upload($field, LOGO_UPLOAD_DIR, LOGO_PUBLIC_DIR, LOGO_MAX_BYTES, 'Logo');
}

/**
 * Saves an uploaded product photo and returns its public relative path, or
 * null if no file was chosen. Throws InvalidArgumentException on an
 * invalid/oversized file.
 */
function save_product_photo_upload($field) {
    return save_image_upload($field, PHOTO_UPLOAD_DIR, PHOTO_PUBLIC_DIR, PHOTO_MAX_BYTES, 'Photo');
}

/** Shared image-upload logic behind save_logo_upload() and save_product_photo_upload(). */
function save_image_upload($field, $uploadDir, $publicDir, $maxBytes, $label) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException("The {$label} file failed to upload. Please try again.");
    }
    if ($file['size'] > $maxBytes) {
        throw new InvalidArgumentException("{$label} file is too large (max " . round($maxBytes / 1024 / 1024) . "MB).");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(IMAGE_ALLOWED_MIME[$mime])) {
        throw new InvalidArgumentException("{$label} must be a JPG, PNG, or WEBP image.");
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    ensure_upload_dir_protected($uploadDir);

    $filename = bin2hex(random_bytes(16)) . '.' . IMAGE_ALLOWED_MIME[$mime];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new InvalidArgumentException('Could not save the uploaded ' . strtolower($label) . '.');
    }

    return $publicDir . '/' . $filename;
}
