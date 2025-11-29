<?php
include 'db.php';
session_start();

// 1. Validate Order ID existence
if (!isset($_GET['orderid'])) {
    header("Location: index.php");
    exit;
}

$order_id = $_GET['orderid'];

// 2. SECURE FETCH: Ensure the order belongs to the user OR the admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Admin can view any order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
} else {
    // Customers can ONLY view their own orders
    // If user is not logged in, user_id is 0, which won't match any order
    $user_id = $_SESSION['user_id'] ?? 0; 
    
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
}

$order = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. CRITICAL CHECK: If no order found (or doesn't belong to user), KICK THEM OUT
if (!$order) {
    header("Location: index.php");
    exit; // Stop script execution immediately
}

// Fetch items only after security check passes
$stmtItems = $pdo->prepare("SELECT order_items.*, products.name, products.price 
                            FROM order_items 
                            JOIN products ON order_items.product_id = products.id 
                            WHERE order_items.order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="success-page-wrapper">
    <div class="receipt-card">
        
        <div class="success-header-bg">
            <div class="success-icon-box">
                <i class="bi bi-check-lg"></i>
            </div>
            <h2 class="m-0" style="font-family: var(--font-serif);">Order Confirmed!</h2>
            <p class="opacity-75 mb-0">Thank you for shopping with FreshCart.</p>
        </div>

        <div class="receipt-body">
            
            <div class="receipt-meta">
                <p class="mb-1"><strong>Order #<?= str_pad($order['id'], 6, "0", STR_PAD_LEFT) ?></strong></p>
                <p class="mb-0"><?= date('F d, Y • h:i A', strtotime($order['created_at'])) ?></p>
            </div>

            <div class="text-center mb-4">
                <small class="text-muted text-uppercase fw-bold">Billed To</small>
                <h5 class="mb-1"><?= htmlspecialchars($order['customer_name']) ?></h5>
                <p class="text-muted small mb-0 px-4"><?= htmlspecialchars($order['address']) ?></p>
            </div>

            <div class="receipt-divider"></div>

            <div class="mb-4">
                <?php $subtotal = 0; ?>
                <?php foreach ($items as $item): ?>
                    <?php 
                        $lineTotal = $item['price'] * $item['quantity'];
                        $subtotal += $lineTotal;
                    ?>
                    <div class="receipt-item-row">
                        <div>
                            <span class="receipt-item-qty"><?= $item['quantity'] ?>x</span>
                            <?= htmlspecialchars($item['name']) ?>
                        </div>
                        <div class="fw-bold">$<?= number_format($lineTotal, 2) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="receipt-divider"></div>

            <?php $discount = $subtotal - $order['total_amount']; ?>
            <?php if ($discount > 0): ?>
                <div class="receipt-item-row text-success">
                    <span>Discount Applied</span>
                    <span>-$<?= number_format($discount, 2) ?></span>
                </div>
            <?php endif; ?>

            <div class="receipt-total-row">
                <span class="total-label">Total Paid</span>
                <span class="total-amount">$<?= number_format($order['total_amount'], 2) ?></span>
            </div>

            <div class="mt-5 no-print">
                <button onclick="window.print()" class="btn-print">
                    <i class="bi bi-printer me-2"></i> Print Receipt
                </button>
                <a href="index.php" class="btn-continue d-block text-center text-decoration-none">
                    Continue Shopping
                </a>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>