<?php
/**
 * Comprehensive Backend Standards & Functionality Test Suite
 * Validates database layer, schemas, constraints, ADS routines, transactions,
 * API endpoints, authentication, and cart/order lifecycle against backend.md.
 */

require_once __DIR__ . '/../config/db.php';

echo "====================================================\n";
echo "   FRESHCART BACKEND & PERSISTENCE VERIFICATION     \n";
echo "   Standards: .agents/rules/backend.md              \n";
echo "====================================================\n\n";

$passes = 0;
$fails = 0;

function assertTest($name, $condition, $details = "") {
    global $passes, $fails;
    if ($condition) {
        $passes++;
        echo "  [PASS] $name\n";
    } else {
        $fails++;
        echo "  [FAIL] $name" . ($details ? " - Reason: $details" : "") . "\n";
    }
}

// ----------------------------------------------------
// 1. Connection & PDO Configuration
// ----------------------------------------------------
echo "1. CONNECTION & PDO HYGIENE:\n";
assertTest("PDO instance connected successfully", $pdo instanceof PDO);
assertTest("PDO ERRMODE set to ERRMODE_EXCEPTION", $pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION);
assertTest("PDO DEFAULT_FETCH_MODE set to FETCH_ASSOC", $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE) === PDO::FETCH_ASSOC);
assertTest("PDO EMULATE_PREPARES disabled (native prepares)", empty($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)));

// ----------------------------------------------------
// 2. Schema, Tables & Relational Integrity
// ----------------------------------------------------
echo "\n2. SCHEMA INTEGRITY & RELATIONAL DESIGN:\n";
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$requiredTables = ['users', 'products', 'orders', 'order_items', 'reviews', 'coupons', 'api_cart'];
foreach ($requiredTables as $tbl) {
    assertTest("Table `$tbl` exists", in_array($tbl, $tables));
}

// Check Foreign Keys
$fks = $pdo->query("
    SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME, ' -> ', REFERENCED_TABLE_NAME, '.', REFERENCED_COLUMN_NAME) as rel
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetchAll(PDO::FETCH_COLUMN);

$requiredFks = [
    'orders.user_id -> users.id',
    'order_items.order_id -> orders.id',
    'order_items.product_id -> products.id',
    'reviews.product_id -> products.id',
    'reviews.user_id -> users.id',
    'api_cart.user_id -> users.id',
    'api_cart.product_id -> products.id'
];
foreach ($requiredFks as $rfk) {
    assertTest("Foreign Key `$rfk` active", in_array($rfk, $fks));
}

// Check Target Indexes
$orderIndexes = $pdo->query("SHOW INDEX FROM orders")->fetchAll(PDO::FETCH_COLUMN, 2);
assertTest("Index on `orders.user_id` active", in_array('idx_orders_user_id', $orderIndexes) || in_array('user_id', $orderIndexes));
assertTest("Index on `orders.status` active", in_array('idx_orders_status', $orderIndexes) || in_array('status', $orderIndexes));

$productIndexes = $pdo->query("SHOW INDEX FROM products")->fetchAll(PDO::FETCH_COLUMN, 2);
assertTest("Index on `products.category` active", in_array('idx_products_category', $productIndexes) || in_array('category', $productIndexes));

// Get active test user ID
$user = $pdo->query("SELECT * FROM users WHERE email = 'customer@example.com'")->fetch();
if (!$user || !password_verify('password', $user['password'])) {
    if (!$user) {
        $pdo->prepare("INSERT INTO users (full_name, email, password, address, role) VALUES ('Test Customer', 'customer@example.com', ?, '123 Test St', 'customer')")
            ->execute([password_hash('password', PASSWORD_DEFAULT)]);
    } else {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([password_hash('password', PASSWORD_DEFAULT), $user['id']]);
    }
    $user = $pdo->query("SELECT * FROM users WHERE email = 'customer@example.com'")->fetch();
}
$validUserId = (int)$user['id'];

// ----------------------------------------------------
// 3. ADS Database Routines (Views, Functions, Procedures, Triggers)
// ----------------------------------------------------
echo "\n3. ADVANCED DATABASE SYSTEMS (ADS) ROUTINES:\n";

// A. View: view_daily_sales
try {
    $viewStmt = $pdo->query("SELECT * FROM view_daily_sales LIMIT 3");
    $viewRows = $viewStmt->fetchAll();
    assertTest("SQL View `view_daily_sales` queries successfully", is_array($viewRows));
} catch (Exception $e) {
    assertTest("SQL View `view_daily_sales` queries successfully", false, $e->getMessage());
}

// B. Stored Function: fn_get_total_spent
try {
    $fnStmt = $pdo->prepare("SELECT fn_get_total_spent(?) as total");
    $fnStmt->execute([$validUserId]);
    $totalSpent = $fnStmt->fetchColumn();
    assertTest("Stored Function `fn_get_total_spent` returns numeric result ($totalSpent)", is_numeric($totalSpent));
} catch (Exception $e) {
    assertTest("Stored Function `fn_get_total_spent` executes successfully", false, $e->getMessage());
}

// C. Stored Procedure: sp_get_user_order_history
try {
    $spStmt = $pdo->prepare("CALL sp_get_user_order_history(?)");
    $spStmt->execute([$validUserId]);
    $history = $spStmt->fetchAll();
    $spStmt->closeCursor(); // Free connection cursor for subsequent queries
    assertTest("Stored Procedure `sp_get_user_order_history` executes cleanly", is_array($history));
} catch (Exception $e) {
    assertTest("Stored Procedure `sp_get_user_order_history` executes cleanly", false, $e->getMessage());
}

// ----------------------------------------------------
// 4. Atomic Mutation, Stock Trigger & Cancellation Rollback
// ----------------------------------------------------
echo "\n4. ATOMIC MUTATION, TRIGGER & INVENTORY RESTOCK:\n";

// Pick a test product
$testProduct = $pdo->query("SELECT id, stock_qty FROM products WHERE stock_qty >= 5 LIMIT 1")->fetch();
if (!$testProduct) {
    $pdo->exec("UPDATE products SET stock_qty = 100 WHERE id = 1");
    $testProduct = $pdo->query("SELECT id, stock_qty FROM products WHERE id = 1")->fetch();
}

$prodId = $testProduct['id'];
$initialStock = (int)$testProduct['stock_qty'];
$purchaseQty = 2;
$newOrderId = null;

// Execute transactional order insertion
$pdo->beginTransaction();
try {
    $insOrder = $pdo->prepare("INSERT INTO orders (customer_name, address, total_amount, status, user_id) VALUES (?, ?, ?, 'Pending', ?)");
    $insOrder->execute(['ACID Test Customer', '456 Test Blvd', 50.00, $validUserId]);
    $newOrderId = $pdo->lastInsertId();

    $insItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
    $insItem->execute([$newOrderId, $prodId, $purchaseQty]);

    $pdo->commit();
    assertTest("Transaction commit on multi-table order placement", true);
} catch (Exception $e) {
    $pdo->rollBack();
    assertTest("Transaction commit on multi-table order placement", false, $e->getMessage());
}

// Verify Trigger: trg_reduce_stock_after_order
$stockAfterOrder = (int)$pdo->query("SELECT stock_qty FROM products WHERE id = $prodId")->fetchColumn();
assertTest("Trigger `trg_reduce_stock_after_order` decremented stock ($initialStock -> $stockAfterOrder)", $stockAfterOrder === ($initialStock - $purchaseQty));

// Verify Atomic Cancellation & Inventory Restock
if ($newOrderId) {
    $pdo->beginTransaction();
    try {
        // 1. Get items to restock
        $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$newOrderId]);
        $orderItems = $itemsStmt->fetchAll();

        foreach ($orderItems as $item) {
            $restockStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
            $restockStmt->execute([$item['quantity'], $item['product_id']]);
        }

        // 2. Mark Cancelled
        $cancelStmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
        $cancelStmt->execute([$newOrderId]);

        $pdo->commit();
        assertTest("Order cancellation transaction committed", true);
    } catch (Exception $e) {
        $pdo->rollBack();
        assertTest("Order cancellation transaction committed", false, $e->getMessage());
    }

    // Verify stock was fully restored
    $stockAfterCancel = (int)$pdo->query("SELECT stock_qty FROM products WHERE id = $prodId")->fetchColumn();
    assertTest("Product inventory accurately restored after cancellation ($stockAfterCancel == $initialStock)", $stockAfterCancel === $initialStock);

    // Clean up test order
    $pdo->exec("DELETE FROM order_items WHERE order_id = $newOrderId");
    $pdo->exec("DELETE FROM orders WHERE id = $newOrderId");
}

// ----------------------------------------------------
// 4.1 Product Catalog Mutation & Persistence (backend.md Stage 3 & 6)
// ----------------------------------------------------
echo "\n4.1 PRODUCT MUTATION & PERSISTENCE LIFECYCLE:\n";

$pdo->beginTransaction();
try {
    // 1. Zero-trust prepared INSERT of product
    $stmtIns = $pdo->prepare("INSERT INTO products (name, category, price, stock_qty, image) VALUES (?, ?, ?, ?, ?)");
    $stmtIns->execute(['Backend Validation Kiwi', 'Fruits', 3.99, 50, 'prod_placeholder.png']);
    $testProdId = (int)$pdo->lastInsertId();
    assertTest("Zero-trust prepared INSERT of new product into catalog", $testProdId > 0);

    // 2. Read back state
    $stmtRead = $pdo->prepare("SELECT price, stock_qty, category FROM products WHERE id = ?");
    $stmtRead->execute([$testProdId]);
    $created = $stmtRead->fetch();
    assertTest("Persistence of newly created product verified in MySQL", (float)$created['price'] === 3.99 && (int)$created['stock_qty'] === 50 && $created['category'] === 'Fruits');

    // 3. Atomic UPDATE with parameter binding
    $stmtUpd = $pdo->prepare("UPDATE products SET price = ?, stock_qty = ?, category = ? WHERE id = ?");
    $stmtUpd->execute([7.49, 120, 'Snacks', $testProdId]);
    
    // 4. Read back mutated state
    $stmtRead->execute([$testProdId]);
    $updated = $stmtRead->fetch();
    assertTest("Atomic UPDATE of product price, stock, and aisle persisted correctly", (float)$updated['price'] === 7.49 && (int)$updated['stock_qty'] === 120 && $updated['category'] === 'Snacks');

    // 5. Clean teardown & verification
    $stmtDel = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmtDel->execute([$testProdId]);
    
    $stmtRead->execute([$testProdId]);
    $deleted = $stmtRead->fetch();
    assertTest("Zero-leakage cleanup & deletion of test product", !$deleted);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    assertTest("Product mutation lifecycle executed cleanly", false, $e->getMessage());
}

// ----------------------------------------------------
// 5. Customer Authentication Backend
// ----------------------------------------------------
echo "\n5. AUTHENTICATION & PASSWORD SECURITY:\n";
assertTest("Seed customer exists in database", (bool)$user);
assertTest("Password verified using native password_verify()", password_verify('password', $user['password']));

// ----------------------------------------------------
// 6. RESTful API Backend
// ----------------------------------------------------
echo "\n6. RESTful API ENDPOINT VERIFICATION:\n";

// Login API
$targetBase = 'http://localhost/YEAR%204/Skills/targets/grocery_app';
$loginPayload = json_encode(['email' => $user['email'], 'password' => 'password']);
$ch = curl_init("$targetBase/api/v1/auth/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = curl_exec($ch);
$loginData = json_decode($res, true);
curl_close($ch);

$token = $loginData['data']['token'] ?? '';
assertTest("API Auth returns Bearer token", !empty($token));

// Protected Orders API with Bearer token
$ch = curl_init("$targetBase/api/v1/orders/history.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
$ordersRes = curl_exec($ch);
$ordersData = json_decode($ordersRes, true);
curl_close($ch);

assertTest("Protected API route succeeds with valid Bearer token", ($ordersData['success'] ?? false) === true);

// Protected Orders API without Bearer token (Must return 401)
$ch = curl_init("$targetBase/api/v1/orders/history.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$unauthRes = curl_exec($ch);
$unauthCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assertTest("Protected API route rejects request without token (HTTP 401)", $unauthCode === 401);

// ----------------------------------------------------
// Summary
// ----------------------------------------------------
echo "\n====================================================\n";
echo "SUMMARY: Total Passed: $passes | Total Failed: $fails\n";
echo "====================================================\n";

if ($fails > 0) {
    exit(1);
} else {
    exit(0);
}
