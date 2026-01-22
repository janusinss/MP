<?php
// api/v1/cart/index.php
include '../../config/cors.php';
include '../../config/database.php';
include '../../utils/Response.php';
include '../../utils/AuthMiddleware.php';

$user = AuthMiddleware::authenticate($pdo);

try {
    // Join with products to get details
    $stmt = $pdo->prepare("
        SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.price, p.image 
        FROM api_cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $cartItems = $stmt->fetchAll();

    $total = 0;
    foreach ($cartItems as &$item) {
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $item['image_url'] = 'assets/images/' . ($item['image'] ?: 'default.jpg');
        $total += $item['subtotal'];
    }

    Response::success([
        'items' => $cartItems,
        'cart_total' => $total,
        'item_count' => count($cartItems)
    ]);

} catch (Exception $e) {
    Response::error("Database Error: " . $e->getMessage(), 500);
}
?>