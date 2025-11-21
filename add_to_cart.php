<?php
session_start();

// Prepare the response array
$response = ['status' => 'error', 'message' => 'Invalid request'];

if (isset($_POST['product_id'])) {
    $productId = $_POST['product_id'];

    // Initialize cart if missing
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Add/Increment item
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]++;
    } else {
        $_SESSION['cart'][$productId] = 1;
    }

    // Calculate total items in cart
    $totalItems = array_sum($_SESSION['cart']);

    // Send Success Response
    $response = [
        'status' => 'success',
        'cart_count' => $totalItems,
        'message' => 'Item added to cart!'
    ];
}

// Return JSON data (The JavaScript will read this)
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>