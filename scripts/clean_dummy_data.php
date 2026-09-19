<?php
// scripts/clean_dummy_data.php
require_once __DIR__ . '/../config/db.php';

echo "=== DATABASE CLEANUP: REMOVING DUMMY ACCOUNTS ===\n";

// 1. Keep only customer@example.com (and customer2@example.com for multi-user tests)
$stmt = $pdo->query("SELECT id, email, full_name FROM users WHERE email = 'customer@example.com'");
$mainCustomer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mainCustomer) {
    // Re-create standard customer if not found
    $passHash = password_hash('password', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (full_name, email, password, address, role) VALUES ('Demo Customer', 'customer@example.com', ?, '123 Market St, Apt 4B', 'customer')")->execute([$passHash]);
    $mainCustomerId = $pdo->lastInsertId();
    echo "Created main customer: customer@example.com (ID: $mainCustomerId)\n";
} else {
    $mainCustomerId = $mainCustomer['id'];
    echo "Keeping main customer: {$mainCustomer['email']} (ID: $mainCustomerId)\n";
}

// 2. Delete all other users except customer@example.com
$delUsers = $pdo->prepare("DELETE FROM users WHERE id != ?");
$delUsers->execute([$mainCustomerId]);
echo "Deleted dummy users. Remaining users in table:\n";

$remainingUsers = $pdo->query("SELECT id, email, full_name, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($remainingUsers as $u) {
    echo "  - ID: {$u['id']} | Email: {$u['email']} | Name: {$u['full_name']} | Role: {$u['role']}\n";
}

// 3. Clean orphaned orders (keep only orders belonging to customer@example.com)
$pdo->prepare("DELETE FROM order_items WHERE order_id NOT IN (SELECT id FROM orders WHERE user_id = ?)")->execute([$mainCustomerId]);
$pdo->prepare("DELETE FROM orders WHERE user_id IS NULL OR user_id != ?")->execute([$mainCustomerId]);

$orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
echo "Remaining cleaned orders for customer: $orderCount\n";

// 4. Clean cart and reviews for deleted users
$pdo->prepare("DELETE FROM api_cart WHERE user_id != ?")->execute([$mainCustomerId]);
$pdo->prepare("DELETE FROM reviews WHERE user_id IS NOT NULL AND user_id != ?")->execute([$mainCustomerId]);

echo "=== CLEANUP COMPLETE ===\n";
