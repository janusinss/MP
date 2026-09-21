<?php
// api/v1/cart/update.php
include '../../config/cors.php';
include '../../config/database.php';
include '../../utils/Response.php';
include '../../utils/AuthMiddleware.php';

$user = AuthMiddleware::authenticate($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error("Method Not Allowed", 405);
}

$data = json_decode(file_get_contents("php://input"));
$pid = isset($data->product_id) ? (int) $data->product_id : 0;
$action = $data->action ?? ''; // 'increase', 'decrease', 'remove'
$hasQuantity = isset($data->quantity);

if (!$pid || (!$action && !$hasQuantity)) {
    Response::error("Product ID and either Action or Quantity required.");
}

try {
    if ($action === 'remove' || ($hasQuantity && (int)$data->quantity <= 0)) {
        $stmt = $pdo->prepare("DELETE FROM api_cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user['id'], $pid]);
        Response::success([], "Item removed.");
    }

    // Get Current Qty
    $stmt = $pdo->prepare("SELECT quantity FROM api_cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user['id'], $pid]);
    $current = $stmt->fetch();

    if (!$current) {
        Response::error("Item not in cart.", 404);
    }

    if ($hasQuantity) {
        $newQty = (int) $data->quantity;
    } else {
        $newQty = $current['quantity'];
        if ($action === 'increase') {
            $newQty++;
        } elseif ($action === 'decrease') {
            $newQty--;
        }
    }

    if ($newQty < 1) {
        // Remove if 0
        $stmt = $pdo->prepare("DELETE FROM api_cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user['id'], $pid]);
        Response::success([], "Item removed (qty 0).");
    } else {
        // Validate against available stock
        $stmtStock = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
        $stmtStock->execute([$pid]);
        $prod = $stmtStock->fetch(PDO::FETCH_ASSOC);
        if ($prod && $newQty > $prod['stock_qty']) {
            Response::error("Quantity exceeds available stock ({$prod['stock_qty']}).", 400);
        }

        // Update
        $stmt = $pdo->prepare("UPDATE api_cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$newQty, $user['id'], $pid]);
        Response::success(['new_quantity' => $newQty], "Cart updated.");
    }

} catch (Exception $e) {
    error_log("API Cart Update Error: " . $e->getMessage());
    Response::error("An internal error occurred while updating cart.", 500);
}
?>