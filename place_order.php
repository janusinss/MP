<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_SESSION['cart'])) {
    
    $name = $_POST['customer_name'];
    $address = $_POST['address'];
    
    // 1. Calculate Total (With Discount Logic)
    $ids = implode(',', array_keys($_SESSION['cart']));
    $stmt = $pdo->query("SELECT * FROM products WHERE id IN ($ids)");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subTotal = 0;
    foreach ($products as $p) {
        $subTotal += $p['price'] * $_SESSION['cart'][$p['id']];
    }

    // Apply Discount if exists
    $finalTotal = $subTotal;
    if (isset($_SESSION['discount'])) {
        $discountAmount = ($subTotal * $_SESSION['discount']['percent']) / 100;
        $finalTotal = $subTotal - $discountAmount;
    }

    try {
        // START TRANSACTION
        $pdo->beginTransaction();

        // 2. Insert into ORDERS table
        // Check if user is logged in
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

        $sql = "INSERT INTO orders (customer_name, address, total_amount, status, user_id) VALUES (?, ?, ?, 'Pending', ?)";
        $stmt = $pdo->prepare($sql);
        
        // FIXED: We now use $finalTotal here instead of $total
        $stmt->execute([$name, $address, $finalTotal, $userId]);
        
        $orderId = $pdo->lastInsertId(); 

        // 3. Insert into ORDER_ITEMS table
        $sqlItem = "INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)";
        $stmtItem = $pdo->prepare($sqlItem);

        // 4. Update Stock
        $sqlUpdateStock = "UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?";
        $stmtStock = $pdo->prepare($sqlUpdateStock);

        foreach ($products as $p) {
            $qty = $_SESSION['cart'][$p['id']];

            // Save item
            $stmtItem->execute([$orderId, $p['id'], $qty]);
            
            // Decrease stock
            $stmtStock->execute([$qty, $p['id']]);
        }

        // Commit the transaction
        $pdo->commit();

        // 5. Clear Cart & Coupon
        unset($_SESSION['cart']);
        unset($_SESSION['discount']); // Clear the coupon so it doesn't apply to the next order automatically
        
        header("Location: success.php?orderid=$orderId");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Order failed: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}
?>