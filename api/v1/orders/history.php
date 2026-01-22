<?php
// api/v1/orders/history.php
include '../../config/cors.php';
include '../../config/database.php';
include '../../utils/Response.php';
include '../../utils/AuthMiddleware.php';

$user = AuthMiddleware::authenticate($pdo);

try {
    // Note: This relies on the stored procedure `sp_get_user_order_history` existing
    // IF the procedure exists, we can use it:
    // $stmt = $pdo->prepare("CALL sp_get_user_order_history(?)");

    // Safer to just use direct SQL for simplicity ensuring it works:
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['orders' => $orders]);

} catch (Exception $e) {
    Response::error("Database Error: " . $e->getMessage(), 500);
}
?>