<?php
// cart/remove.php
// Removes an item from the session cart
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['product_id'])) {
    $id = (int)$_POST['product_id'];

    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
    }
}

header("Location: ./");
exit;
?>
