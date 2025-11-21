<?php
session_start();

if (isset($_POST['product_id'])) {
    $id = $_POST['product_id'];

    // Check if the item exists in the cart, then remove it
    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
    }
}

// Redirect back to the cart page immediately
header("Location: cart.php");
exit;
?>