<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

redirect(!empty($_SESSION['user']) ? '/dashboard/index.php' : '/login.php');
