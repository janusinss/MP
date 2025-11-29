<?php
include 'db.php';
session_start();

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

if (!isset($_GET['order_id'])) {
    header("Location: my_orders.php");
    exit;
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// 2. Fetch Order (Verify it belongs to this user!)
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: my_orders.php");
    exit;
}

// 3. Fetch Items
$stmtItems = $pdo->prepare("SELECT order_items.*, products.name, products.price, products.image 
                            FROM order_items 
                            JOIN products ON order_items.product_id = products.id 
                            WHERE order_items.order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';

// Status Logic for UI
$s = $order['status'];
$badgeClass = 'ts-pending';
$icon = 'bi-hourglass-split';
$progressWidth = '5%'; // Default for pending

if ($s == 'Shipped') { 
    $badgeClass = 'ts-shipped'; 
    $icon = 'bi-truck';
    $progressWidth = '50%';
}
if ($s == 'Delivered') { 
    $badgeClass = 'ts-delivered'; 
    $icon = 'bi-check-circle-fill'; 
    $progressWidth = '100%';
}
if ($s == 'Cancelled') { 
    $badgeClass = 'ts-cancelled'; 
    $icon = 'bi-x-circle-fill'; 
    $progressWidth = '0%'; // Hide progress for cancelled
}
?>

<div class="container mt-5 mb-5 order-ticket-wrapper">
    
    <div class="mb-4 animate-fade-in">
        <a href="my_orders.php" class="text-decoration-none text-muted small fw-bold text-uppercase">
            <i class="bi bi-arrow-left me-1"></i> Back to Order History
        </a>
    </div>

    <div class="order-ticket-card animate-fade-in">
        
        <div class="ticket-header">
            <div>
                <h1 class="ticket-id">Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></h1>
                <div class="ticket-meta">
                    <span><i class="bi bi-calendar3 me-1"></i> <?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                    <span><i class="bi bi-clock me-1"></i> <?= date('h:i A', strtotime($order['created_at'])) ?></span>
                </div>
            </div>
            <div class="ticket-status-badge <?= $badgeClass ?>">
                <i class="bi <?= $icon ?>"></i> <?= $s ?>
            </div>
        </div>

        <?php if ($s != 'Cancelled'): ?>
        <div class="order-progress-wrapper pt-4">
            <div class="progress-track">
                <div class="progress-fill" style="width: <?= $progressWidth ?>;"></div>
                
                <div class="progress-step active">
                    <span class="step-label">Ordered</span>
                </div>
                
                <div class="progress-step <?= ($s == 'Shipped' || $s == 'Delivered') ? 'active' : '' ?>">
                    <span class="step-label">Shipped</span>
                </div>
                
                <div class="progress-step <?= ($s == 'Delivered') ? 'active' : '' ?>">
                    <span class="step-label">Delivered</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="ticket-info-grid">
            <div>
                <span class="info-label">Delivered To</span>
                <div class="info-content">
                    <strong><?= htmlspecialchars($order['customer_name']) ?></strong><br>
                    <?= nl2br(htmlspecialchars($order['address'])) ?>
                </div>
            </div>
            <div>
                <span class="info-label">Payment Method</span>
                <div class="info-content">
                    <i class="bi bi-cash-stack text-success me-2"></i> Cash on Delivery
                </div>
            </div>
        </div>

        <div class="ticket-items-container">
            <span class="info-label mb-3">Items Ordered</span>
            
            <?php foreach ($items as $item): ?>
                <div class="ticket-item">
                    <img src="assets/images/<?= $item['image'] ?: 'default.jpg' ?>" class="ticket-thumb" alt="Product">
                    <div class="ticket-item-details">
                        <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                        <span class="item-qty">Qty: <?= $item['quantity'] ?></span>
                    </div>
                    <div class="item-price">
                        $<?= number_format($item['price'] * $item['quantity'], 2) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ticket-footer">
            <span class="ticket-total-label">Grand Total</span>
            <span class="ticket-total-value">$<?= number_format($order['total_amount'], 2) ?></span>
        </div>

        <?php if ($s == 'Pending'): ?>
            <div class="p-4 text-center bg-light border-top">
                <form action="user_cancel_order.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger rounded-pill px-4 text-uppercase small fw-bold">
                        Cancel Order
                    </button>
                </form>
                <p class="text-muted small mt-2 mb-0">You can only cancel orders before they are shipped.</p>
            </div>
        <?php endif; ?>

    </div>
    
    <div class="text-center mt-4">
        <a href="contact.php" class="text-muted small text-decoration-none">Need help with this order? Contact Support</a>
    </div>

</div>

<?php include 'includes/footer.php'; ?>