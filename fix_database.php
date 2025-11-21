<?php
include 'db.php';

try {
    // 1. Fix Empty/Null Statuses -> Set to 'Pending'
    $sql1 = "UPDATE orders SET status = 'Pending' WHERE status IS NULL OR status = ''";
    $pdo->query($sql1);
    echo "✅ Fixed empty statuses.<br>";

    // 2. Fix Lowercase Statuses -> Capitalize them (e.g. 'pending' -> 'Pending')
    $sql2 = "UPDATE orders SET status = 'Pending' WHERE status = 'pending'";
    $pdo->query($sql2);
    echo "✅ Fixed lowercase statuses.<br>";
    
    echo "<hr><strong>🎉 Database Repair Complete!</strong><br>";
    echo "Go back to <a href='my_orders.php'>My Orders</a> to check.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>