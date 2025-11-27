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
    $user_id = $_SESSION['user_id']; // Get the logged-in user ID

    try {
        $pdo->beginTransaction();

        // 2. Get current status AND verify ownership (The Fix)
        // We added "AND user_id = ?" to ensure you own this order
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user_id]);
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

            // 4. Update Status (Verify ownership again for safety)
            $stmtUpdate = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
            $stmtUpdate->execute([$order_id, $user_id]);

            $pdo->commit();
            
            header("Location: my_orders.php?msg=cancelled");
            exit;

        } else {
            // Order not found, not yours, or already cancelled
            $pdo->rollBack();
            header("Location: my_orders.php?error=invalid_request");
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