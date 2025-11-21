<?php
include 'db.php';
session_start();

// 1. Security Check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];

    try {
        $pdo->beginTransaction();

        // 2. Get current status
        // (We removed the user_id check here so you can cancel your old orders too)
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order && $order['status'] != 'Cancelled') {
            
            // 3. RESTOCK ITEMS
            $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$order_id]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $stmtRestock = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
            foreach ($items as $item) {
                $stmtRestock->execute([$item['quantity'], $item['product_id']]);
            }

            // 4. Update Status
            $stmtUpdate = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
            $stmtUpdate->execute([$order_id]);

            $pdo->commit();
            
            // Redirect back to My Orders
            header("Location: my_orders.php?msg=cancelled");
            exit;

        } else {
            // Already cancelled or invalid
            $pdo->rollBack();
            header("Location: my_orders.php");
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: my_orders.php");
    exit;
}
?>