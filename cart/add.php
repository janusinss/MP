<?php
// cart/add.php
// AJAX Cart Addition Handler
error_reporting(0); // Suppress warnings for clean JSON output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

$response = ['status' => 'error', 'message' => 'Invalid request'];

// 1. SECURITY CHECK: Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'login_required', 'message' => 'Please login first.']);
    exit;
}

if (isset($_POST['product_id'])) {
    $productId = (int)$_POST['product_id'];
    $qtyRequested = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

    try {
        // 2. STOCK CHECK
        $stmt = $pdo->prepare("SELECT stock_qty, name, price FROM products WHERE id = ?");
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
            $_SESSION['cart'][$productId] = $newTotalQty;

            // Calculate total items for badge
            $totalItems = array_sum($_SESSION['cart']);

            // Calculate live subtotal
            $subtotal = 0;
            if (!empty($_SESSION['cart'])) {
                $cartKeys = array_map('intval', array_keys($_SESSION['cart']));
                $inList = implode(',', array_fill(0, count($cartKeys), '?'));
                $stmtPrices = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($inList)");
                $stmtPrices->execute($cartKeys);
                $prices = $stmtPrices->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach ($_SESSION['cart'] as $id => $qty) {
                    if (isset($prices[$id])) {
                        $subtotal += $prices[$id] * $qty;
                    }
                }
            }

            $response = [
                'status' => 'success',
                'cart_count' => $totalItems,
                'cart_subtotal' => number_format($subtotal, 2),
                'message' => htmlspecialchars($product['name']) . ' added to basket!'
            ];
        } else {
            $response = [
                'status' => 'error', 
                'message' => "Sorry, only {$product['stock_qty']} items left in stock!"
            ];
        }

    } catch (PDOException $e) {
        $response = ['status' => 'error', 'message' => 'Database error occurred.'];
    }
}

// Return JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
