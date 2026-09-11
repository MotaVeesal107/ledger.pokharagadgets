<?php
require_once __DIR__ . '/functions.php';

/** Session-based auth. Requires config.php (session + $pdo) to be loaded first. */

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (empty($_SESSION['user'])) {
        redirect('/login.php');
    }
}

function is_admin() {
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('You do not have permission to view this page. Ask an admin.');
    }
}

/** Whether the current user may see cost prices / COGS figures. */
function can_view_cost(PDO $pdo) {
    if (is_admin()) {
        return true;
    }
    $settings = get_settings($pdo);
    return $settings && (bool)$settings['show_cost_to_staff'];
}

function attempt_login(PDO $pdo, $email, $password) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        return true;
    }
    return false;
}

function logout_user() {
    $_SESSION = [];
    session_destroy();
}
