<?php
session_start();

// Prepare default response
$response = ['status' => 'error', 'message' => 'Invalid request'];

// 1. SECURITY CHECK: Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'login_required', 'message' => 'Please login first.']);
    exit;
}

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

    // Calculate total items
    $totalItems = array_sum($_SESSION['cart']);

    // Send Success Response
    $response = [
        'status' => 'success',
        'cart_count' => $totalItems,
        'message' => 'Item added to cart!'
    ];
}

// Return JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>