<?php
// cart/update.php
// Modifies item quantity or removes item if count drops below 1
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id'], $_POST['action'])) {
    $id = (int)$_POST['product_id'];
    $action = $_POST['action'];
    
    if (!isset($_SESSION['cart'][$id])) {
        header("Location: index.php");
        exit;
    }

    // Check Stock Limit First
    $stmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product) {
        if ($action === 'increase') {
            if ($_SESSION['cart'][$id] < $product['stock_qty']) {
                $_SESSION['cart'][$id]++;
            }
        } elseif ($action === 'decrease') {
            if ($_SESSION['cart'][$id] > 1) {
                $_SESSION['cart'][$id]--;
            } else {
                unset($_SESSION['cart'][$id]); // Remove if decreasing from 1
            }
        }
    }
}

header("Location: ./");
exit;
?>
