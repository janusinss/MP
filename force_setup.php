<?php
// force_setup.php
include 'db.php';
try {
    echo "Attempting to add api_token to users...<br>";
    $pdo->exec("ALTER TABLE users ADD COLUMN api_token VARCHAR(64) DEFAULT NULL");
    echo "✅ Success.<br>";
} catch (PDOException $e) {
    echo "Info (might exist): " . $e->getMessage() . "<br>";
}

try {
    echo "Attempting to add api_cart table...<br>";
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
    echo "✅ Cart Table Success.<br>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>