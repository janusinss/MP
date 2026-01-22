<?php
// api/v1/orders/create.php
include '../../config/cors.php';
include '../../config/database.php';
include '../../utils/Response.php';
include '../../utils/AuthMiddleware.php';

$user = AuthMiddleware::authenticate($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error("Method Not Allowed", 405);
}

$data = json_decode(file_get_contents("php://input"));
$address = $data->address ?? '';

if (!$address) {
    Response::error("Shipping address is required.");
}

try {
    $pdo->beginTransaction();

    // 1. Get Cart
    $stmt = $pdo->prepare("
        SELECT c.*, p.price, p.stock_qty, p.name 
        FROM api_cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception("Cart is empty.");
    }

    $total = 0;
    foreach ($items as $item) {
        if ($item['quantity'] > $item['stock_qty']) {
            throw new Exception("Product '{$item['name']}' out of stock.");
        }
        $total += $item['price'] * $item['quantity'];
    }

    // 2. Create Order
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (user_id, customer_name, address, total_amount, status, created_at)
        VALUES (?, ?, ?, ?, 'Pending', NOW())
    ");
    $stmtOrder->execute([$user['id'], $user['full_name'], $address, $total]);
    $orderId = $pdo->lastInsertId();

    // 3. Create Order Items & Clear Cart
    $stmtItem = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity)
        VALUES (?, ?, ?)
    ");

    foreach ($items as $item) {
        $stmtItem->execute([$orderId, $item['product_id'], $item['quantity']]);
    }

    // 4. Clear API Cart
    $pdo->prepare("DELETE FROM api_cart WHERE user_id = ?")->execute([$user['id']]);

    // Commit
    $pdo->commit();

    Response::success(['order_id' => $orderId], "Order placed successfully!");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::error($e->getMessage(), 400);
}
?>