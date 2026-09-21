<?php
// orders/place.php
// Atomic Order Processor with Transaction Boundaries & Rollback Safety
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_SESSION['cart'])) {

    if (!verify_csrf_token()) {
        http_response_code(403);
        die("Security validation failed. Please refresh checkout.");
    }

    $name = trim($_POST['customer_name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name) || empty($address)) {
        header("Location: checkout.php?error=missing_fields");
        exit;
    }

    // 1. Calculate Total (With Parameterized Prepared Statement & Discount Logic)
    $cartKeys = array_map('intval', array_keys($_SESSION['cart']));
    if (empty($cartKeys)) {
        header("Location: ../cart/");
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($cartKeys), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($cartKeys);
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
        // ATOMIC TRANSACTION BOUNDARY
        $pdo->beginTransaction();

        // 2. Insert into ORDERS table
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

        $sql = "INSERT INTO orders (customer_name, address, total_amount, status, user_id) VALUES (?, ?, ?, 'Pending', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $address, $finalTotal, $userId]);

        $orderId = $pdo->lastInsertId();

        // 3. Insert into ORDER_ITEMS table (with trigger-independent stock decrement)
        $sqlItem = "INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)";
        $stmtItem = $pdo->prepare($sqlItem);

        static $hasTrigger = null;
        if ($hasTrigger === null) {
            $trgCheck = $pdo->query("SHOW TRIGGERS LIKE 'order_items'")->fetchAll();
            $hasTrigger = !empty($trgCheck);
        }
        $stmtStock = !$hasTrigger ? $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?") : null;

        foreach ($products as $p) {
            $qty = $_SESSION['cart'][$p['id']];
            if ($stmtStock) {
                $stmtStock->execute([$qty, $p['id'], $qty]);
                if ($stmtStock->rowCount() === 0) {
                    throw new Exception("Product '{$p['name']}' has insufficient stock.");
                }
            }
            $stmtItem->execute([$orderId, $p['id'], $qty]);
        }

        // Commit transaction
        $pdo->commit();

        // 4. Clear Session Cart & Coupon & Record Last Order ID for Guest View
        unset($_SESSION['cart']);
        unset($_SESSION['discount']);
        $_SESSION['last_order_id'] = $orderId;

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $pos = strpos($scriptName, '/grocery_app');
        if ($pos !== false) {
            $rootPath = substr($scriptName, 0, $pos + strlen('/grocery_app')) . '/';
        } else {
            $rootPath = '/';
        }

        header("Location: " . $rootPath . "orders/success.php?orderid=$orderId");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Order Placement Error: " . $e->getMessage());
        $safeMsg = str_contains($e->getMessage(), 'stock') ? $e->getMessage() : "An error occurred while processing your order.";
        header("Location: checkout.php?error=" . urlencode($safeMsg));
        exit;
    }
} else {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($scriptName, '/grocery_app');
    $rootPath = ($pos !== false) ? substr($scriptName, 0, $pos + strlen('/grocery_app')) . '/' : '/';
    header("Location: " . $rootPath);
    exit;
}
?>
