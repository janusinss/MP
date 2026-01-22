<?php
// api/v1/cart/add.php
include '../../config/cors.php';
include '../../config/database.php';
include '../../utils/Response.php';
include '../../utils/AuthMiddleware.php';

$user = AuthMiddleware::authenticate($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error("Method Not Allowed", 405);
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->product_id) || !isset($data->quantity)) {
    Response::error("Product ID and Quantity are required.");
}

$pid = (int) $data->product_id;
$qty = (int) $data->quantity;

if ($qty < 1) {
    Response::error("Quantity must be at least 1.");
}

try {
    // Check Stock
    $stmtStock = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
    $stmtStock->execute([$pid]);
    $product = $stmtStock->fetch();

    if (!$product) {
        Response::error("Product not found.", 404);
    }

    if ($product['stock_qty'] < $qty) {
        Response::error("Insufficient stock. Available: " . $product['stock_qty'], 400);
    }

    // Upsert into Cart
    $stmt = $pdo->prepare("
        INSERT INTO api_cart (user_id, product_id, quantity) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ");
    $stmt->execute([$user['id'], $pid, $qty]);

    Response::success([], "Item added to cart.");

} catch (Exception $e) {
    Response::error("Database Error: " . $e->getMessage(), 500);
}
?>