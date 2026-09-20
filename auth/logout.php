<?php
// auth/logout.php
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['role']);
unset($_SESSION['admin_logged_in']);
unset($_SESSION['cart']);
unset($_SESSION['discount']);
header("Location: ../");
exit;
?>
