<?php
// cart/remove.php
// Removes an item from the session cart
session_start();

if (isset($_POST['product_id'])) {
    $id = (int)$_POST['product_id'];

    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
    }
}

header("Location: ./");
exit;
?>
