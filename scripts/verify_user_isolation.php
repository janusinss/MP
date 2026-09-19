<?php
/**
 * Cross-User Connection & Data Isolation Verification Suite
 */

require_once __DIR__ . '/../config/db.php';

echo "====================================================\n";
echo "   CROSS-USER DATA & CONNECTION ISOLATION AUDIT     \n";
echo "   Standards: .agents/rules/backend.md              \n";
echo "====================================================\n\n";

$passes = 0;
$fails = 0;

function assertIsolation($name, $condition, $details = "") {
    global $passes, $fails;
    if ($condition) {
        $passes++;
        echo "  [PASS] $name\n";
    } else {
        $fails++;
        echo "  [FAIL] $name" . ($details ? " - Reason: $details" : "") . "\n";
    }
}

// 1. Users
$passHash = password_hash('password', PASSWORD_DEFAULT);
$stmtA = $pdo->prepare("SELECT * FROM users WHERE email = 'customer@example.com'");
$stmtA->execute();
$userA = $stmtA->fetch(PDO::FETCH_ASSOC);

$stmtB = $pdo->prepare("SELECT * FROM users WHERE email = 'customer2@example.com'");
$stmtB->execute();
$userB = $stmtB->fetch(PDO::FETCH_ASSOC);

if (!$userB) {
    $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES ('Customer Beta', 'customer2@example.com', ?, 'customer')")->execute([$passHash]);
    $userB = $pdo->query("SELECT * FROM users WHERE email = 'customer2@example.com'")->fetch(PDO::FETCH_ASSOC);
}

$idA = (int)$userA['id'];
$idB = (int)$userB['id'];

assertIsolation("User A and User B have distinct IDs", $idA !== $idB);

// 2. Order Isolation
$testProduct = $pdo->query("SELECT id FROM products WHERE stock_qty > 5 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$prodId = $testProduct['id'];

$pdo->beginTransaction();
$pdo->prepare("INSERT INTO orders (user_id, customer_name, address, total_amount, status) VALUES (?, 'Customer Alpha', 'Alpha St', 75.00, 'Pending')")->execute([$idA]);
$orderA_Id = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, 1)")->execute([$orderA_Id, $prodId]);
$pdo->commit();

$cookieFileB = __DIR__ . '/test_cookie_b.txt';
if (file_exists($cookieFileB)) unlink($cookieFileB);

$ch = curl_init("http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app/auth/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['email' => 'customer2@example.com', 'password' => 'password']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFileB);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFileB);
curl_exec($ch);
curl_close($ch);

$ch = curl_init("http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app/orders/details.php?order_id=$orderA_Id");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFileB);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$detailsCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assertIsolation("User B cannot access User A's order details (Redirected: HTTP $detailsCode)", $detailsCode === 302);

$ch = curl_init("http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app/orders/cancel.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['order_id' => $orderA_Id]);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFileB);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
curl_close($ch);

$orderA_Status = $pdo->query("SELECT status FROM orders WHERE id = $orderA_Id")->fetchColumn();
assertIsolation("User B cannot cancel User A's order (Order remains '$orderA_Status')", $orderA_Status === 'Pending');

// 3. Stored Routine Isolation
$spB = $pdo->prepare("CALL sp_get_user_order_history(?)");
$spB->execute([$idB]);
$ordersForB = $spB->fetchAll(PDO::FETCH_ASSOC);
$spB->closeCursor();

$foundOrderA = false;
foreach ($ordersForB as $ob) {
    if ((int)$ob['id'] === $orderA_Id) { $foundOrderA = true; break; }
}
assertIsolation("Stored Procedure `sp_get_user_order_history` strictly excludes other users' orders", !$foundOrderA);

// Cleanup
$pdo->exec("DELETE FROM order_items WHERE order_id = $orderA_Id");
$pdo->exec("DELETE FROM orders WHERE id = $orderA_Id");
if (file_exists($cookieFileB)) unlink($cookieFileB);

echo "\n====================================================\n";
echo "SUMMARY: Total Passed: $passes | Total Failed: $fails\n";
echo "====================================================\n";
