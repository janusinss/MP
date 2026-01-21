<?php
include 'db.php';

try {
    echo "<h1>Setting up Advanced SQL Features...</h1>";

    // 1. Create View: view_daily_sales
    // Drop if exists first
    $pdo->exec("DROP VIEW IF EXISTS view_daily_sales");

    $sqlView = "
    CREATE VIEW view_daily_sales AS
    SELECT 
        DATE(created_at) as order_date, 
        SUM(total_amount) as daily_total,
        COUNT(id) as order_count
    FROM orders 
    WHERE status != 'Cancelled' 
    GROUP BY DATE(created_at);
    ";
    $pdo->exec($sqlView);
    echo "<p class='text-success'>✅ View 'view_daily_sales' created.</p>";


    // 2. Create Stored Function: fn_get_total_spent
    // Note: DELIMITER syntax is not needed in PDO->exec
    $pdo->exec("DROP FUNCTION IF EXISTS fn_get_total_spent");

    $sqlFunc = "
    CREATE FUNCTION fn_get_total_spent(uid INT) 
    RETURNS DECIMAL(10,2)
    DETERMINISTIC
    READS SQL DATA
    BEGIN
        DECLARE total DECIMAL(10,2);
        SELECT SUM(total_amount) INTO total FROM orders WHERE user_id = uid AND status != 'Cancelled';
        RETURN IFNULL(total, 0.00);
    END;
    ";
    $pdo->exec($sqlFunc);
    echo "<p class='text-success'>✅ Function 'fn_get_total_spent' created.</p>";


    // 3. Create Stored Procedure: sp_get_user_order_history
    $pdo->exec("DROP PROCEDURE IF EXISTS sp_get_user_order_history");

    $sqlProc = "
    CREATE PROCEDURE sp_get_user_order_history(IN uid INT)
    BEGIN
        SELECT * FROM orders WHERE user_id = uid ORDER BY created_at DESC;
    END;
    ";
    $pdo->exec($sqlProc);
    echo "<p class='text-success'>✅ Procedure 'sp_get_user_order_history' created.</p>";


    // 4. Create Trigger: trg_reduce_stock_after_order
    // We need to trigger this on order_items INSERT
    $pdo->exec("DROP TRIGGER IF EXISTS trg_reduce_stock_after_order");

    $sqlTrig = "
    CREATE TRIGGER trg_reduce_stock_after_order
    AFTER INSERT ON order_items
    FOR EACH ROW
    BEGIN
        UPDATE products 
        SET stock_qty = stock_qty - NEW.quantity 
        WHERE id = NEW.product_id;
    END;
    ";
    $pdo->exec($sqlTrig);
    echo "<p class='text-success'>✅ Trigger 'trg_reduce_stock_after_order' created.</p>";


    // 5. Create Indices for Performance
    // Note: These might fail if they already exist, so we wrap in try-catch blocks or use silent execution where possible in SQL, 
    // but standard MySQL CREATE INDEX doesn't support IF NOT EXISTS in older versions. We'll attempt it.

    try {
        $pdo->exec("CREATE INDEX idx_products_category ON products(category)");
        echo "<p class='text-success'>✅ Index 'idx_products_category' created.</p>";
    } catch (Exception $e) {
        echo "<p class='text-warning'>⚠️ Index 'idx_products_category' might already exist.</p>";
    }

    try {
        $pdo->exec("CREATE INDEX idx_orders_status ON orders(status)");
        echo "<p class='text-success'>✅ Index 'idx_orders_status' created.</p>";
    } catch (Exception $e) {
        echo "<p class='text-warning'>⚠️ Index 'idx_orders_status' might already exist.</p>";
    }


    echo "<h2>All Features Installed Successfully!</h2>";
    echo "<a href='index.php'>Go Home</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
?>