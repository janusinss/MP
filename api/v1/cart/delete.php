<?php
// api/v1/cart/delete.php
include '../../config/cors.php';
include '../../config/database.php';
include '../../utils/Response.php';
include '../../utils/AuthMiddleware.php';

$user = AuthMiddleware::authenticate($pdo);

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST' && $method !== 'DELETE') {
    Response::error("Method Not Allowed", 405);
}

// Support both JSON body and query parameter
$data = json_decode(file_get_contents("php://input"));
$pid = 0;

if (isset($data->product_id)) {
    $pid = (int) $data->product_id;
} elseif (isset($_GET['product_id'])) {
    $pid = (int) $_GET['product_id'];
}

if ($pid <= 0) {
    Response::error("A valid Product ID is required.");
}

try {
    // Check if item exists in active cart
    $stmtCheck = $pdo->prepare("SELECT quantity FROM api_cart WHERE user_id = ? AND product_id = ?");
    $stmtCheck->execute([$user['id'], $pid]);
    $existing = $stmtCheck->fetch();

    if (!$existing) {
        Response::error("Item not found in your cart.", 404);
    }

    // Atomic deletion
    $stmtDelete = $pdo->prepare("DELETE FROM api_cart WHERE user_id = ? AND product_id = ?");
    $stmtDelete->execute([$user['id'], $pid]);

    // Query remaining cart count
    $stmtCount = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM api_cart WHERE user_id = ?");
    $stmtCount->execute([$user['id']]);
    $remainingItems = (int) $stmtCount->fetchColumn();

    Response::success([
        'removed_product_id' => $pid,
        'remaining_cart_items' => $remainingItems
    ], "Item successfully removed from cart.");

} catch (Exception $e) {
    error_log("API Cart Delete Error: " . $e->getMessage());
    Response::error("An internal error occurred while removing item from cart.", 500);
}
