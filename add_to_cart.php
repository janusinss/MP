<?php
error_reporting(0); // Suppress warnings for clean JSON output
session_start();
include 'db.php';

$response = ['status' => 'error', 'message' => 'Invalid request'];

// 1. SECURITY CHECK: Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'login_required', 'message' => 'Please login first.']);
    exit;
}

if (isset($_POST['product_id'])) {
    $productId = $_POST['product_id'];
    $qtyRequested = 1; // Default add quantity

    try {
        // 2. STOCK CHECK (The Fix)
        // Fetch current stock from DB
        $stmt = $pdo->prepare("SELECT stock_qty, name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
            exit;
        }

        // Initialize cart if missing
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // Check how many we already have in the cart
        $currentCartQty = isset($_SESSION['cart'][$productId]) ? $_SESSION['cart'][$productId] : 0;
        $newTotalQty = $currentCartQty + $qtyRequested;

        // 3. VALIDATE QUANTITY
        if ($newTotalQty <= $product['stock_qty']) {
            // Add/Increment item
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]++;
            } else {
                $_SESSION['cart'][$productId] = 1;
            }

            // Calculate total items for badge
            $totalItems = array_sum($_SESSION['cart']);

            $response = [
                'status' => 'success',
                'cart_count' => $totalItems,
                'message' => 'Item added to cart!'
            ];
        } else {
            // Not enough stock
            $response = [
                'status' => 'error', 
                'message' => "Sorry, only {$product['stock_qty']} items left in stock!"
            ];
        }

    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => 'Database error.'];
    }
}

// Return JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>