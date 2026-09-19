<?php
// orders/cancel.php
// Atomic Order Cancellation & Inventory Restocking
require_once __DIR__ . '/../config/db.php';
session_start();

// 1. Security Check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $user_id = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // 2. Verify ownership and non-cancelled status
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order && $order['status'] !== 'Cancelled' && $order['status'] === 'Pending') {
            
            // 3. RESTOCK INVENTORY
            $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$order_id]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $stmtRestock = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
            foreach ($items as $item) {
                $stmtRestock->execute([$item['quantity'], $item['product_id']]);
            }

            // 4. Update Status to Cancelled
            $stmtUpdate = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
            $stmtUpdate->execute([$order_id, $user_id]);

            $pdo->commit();
            header("Location: index.php?msg=cancelled");
            exit;

        } else {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: index.php?error=cannot_cancel");
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Cancellation failed: " . htmlspecialchars($e->getMessage()));
    }
} else {
    header("Location: index.php");
    exit;
}
?>
