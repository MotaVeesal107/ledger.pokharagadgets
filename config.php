<?php
/**
 * Database configuration.
 * Edit these four values with the MySQL database you created in cPanel.
 * (cPanel -> MySQL Databases -> create DB, create user, add user to DB.)
 */
$db_host = 'localhost';
$db_name = 'your_database_name';
$db_user = 'your_database_user';
$db_pass = 'your_database_password';

define('APP_NAME', 'Ledger');
define('CURRENCY_PREFIX', 'Rs. ');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Check the credentials in config.php. (' . $e->getMessage() . ')');
}
