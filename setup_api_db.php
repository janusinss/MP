<?php
include 'db.php';

try {
    echo "<h1>API Database Setup</h1>";

    // 1. API Token
    echo "Checking for api_token column...<br>";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'api_token'");
    $exists = $stmt->fetch();

    if (!$exists) {
        $pdo->exec("ALTER TABLE users ADD COLUMN api_token VARCHAR(64) DEFAULT NULL AFTER role");
        echo "✅ Column 'api_token' added successfully.<br>";
    } else {
        echo "ℹ️ Column 'api_token' already exists.<br>";
    }

    // 2. API Cart Table
    echo "Checking for api_cart table...<br>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_cart (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            UNIQUE KEY unique_cart_item (user_id, product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ Table 'api_cart' is ready.<br>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>