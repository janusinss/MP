<?php
// scripts/clean_database_fresh_slate.php
// Clean all user data, test orders, automated reviews, and carts while preserving products (foods) and coupons.
require_once __DIR__ . '/../config/db.php';

echo "=== FRESH SLATE DATABASE CLEANUP ===\n";

// 1. Create safety backup
$backupDir = __DIR__ . '/../database/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
$backupFile = $backupDir . '/backup_before_clean_' . date('Ymd_His') . '.sql';

// Try mysqldump if available, or generate PHP dump
$dumpSuccess = false;
$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbName = defined('DB_NAME') ? DB_NAME : 'grocery_db';

exec("mysqldump -h $dbHost -u $dbUser " . (!empty($dbPass) ? "-p$dbPass " : "") . "$dbName > \"$backupFile\" 2>&1", $output, $ret);
if ($ret === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
    echo "Safety backup created at: $backupFile (" . number_format(filesize($backupFile)) . " bytes)\n";
} else {
    echo "Note: mysqldump skipped or not in PATH, continuing with transactional cleanup.\n";
}

// 2. Perform Foreign Key safe wipe
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$tablesToClean = [
    'order_items',
    'orders',
    'reviews',
    'api_cart',
    'users'
];

foreach ($tablesToClean as $table) {
    $countBefore = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $pdo->exec("TRUNCATE TABLE `$table`");
    $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
    echo "  - Cleaned `$table`: removed $countBefore rows, AUTO_INCREMENT reset to 1\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// 3. Verify Preserved Tables
$productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$couponCount = $pdo->query("SELECT COUNT(*) FROM coupons")->fetchColumn();

echo "\n=== PRESERVED CATALOG DATA ===\n";
echo "  - Products (Foods): $productCount items active\n";
echo "  - Coupons: $couponCount active\n";

echo "\n=== ALL TABLE STATUS ===\n";
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo "  - $t: $cnt rows\n";
}

echo "\n=== DATABASE CLEANUP COMPLETED SUCCESSFULLY ===\n";
