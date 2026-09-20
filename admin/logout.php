<?php
// admin/logout.php
// Cleanly terminates administrator session and redirects directly to storefront landing
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['admin_logged_in']);
unset($_SESSION['user_name']);
unset($_SESSION['user_id']);
unset($_SESSION['role']);

header("Location: ../");
exit;
