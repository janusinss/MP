<?php
include 'db.php';
session_start();
// Security Check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
// Fetch orders for THIS user only
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Orders | FreshCart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="container mt-5 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="m-0" style="font-family: var(--font-serif); font-weight: 700;">My Orders</h2>
            <p class="text-muted m-0">Track your past purchases and returns.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i> Back to Shop
        </a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'cancelled'): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>Order successfully cancelled.</div>
        </div>
    <?php endif; ?>

    <?php if (count($orders) > 0): ?>
        <div class="row">
            <?php foreach ($orders as $order): ?>
                <div class="col-md-12">
                    
                    <div class="order-card">
                        <div class="order-header">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-white border rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                    <i class="bi bi-box-seam text-secondary fs-5"></i>
                                </div>
                                <div>
                                    <span class="order-id">#<?= str_pad($order['id'], 6, "0", STR_PAD_LEFT) ?></span>
                                    <div class="order-date">
                                        <i class="bi bi-calendar3"></i> 
                                        <?= date('M d, Y • h:i A', strtotime($order['created_at'])) ?>
                                    </div>
                                </div>
                            </div>

                            <?php 
                                $status = $order['status'] ?: 'Pending';
                                $badgeClass = 'status-pending';
                                $icon = 'bi-hourglass-split';

                                if ($status == 'Shipped') { $badgeClass = 'status-shipped'; $icon = 'bi-truck'; }
                                if ($status == 'Delivered') { $badgeClass = 'status-delivered'; $icon = 'bi-check-circle-fill'; }
                                if ($status == 'Cancelled') { $badgeClass = 'status-cancelled'; $icon = 'bi-x-circle-fill'; }
                            ?>
                            <div class="status-pill <?= $badgeClass ?>">
                                <i class="bi <?= $icon ?>"></i> <?= $status ?>
                            </div>
                        </div>

                        <div class="order-body">
                            <div class="row align-items-center">
                                
                                <div class="col-md-3 mb-3 mb-md-0">
                                    <span class="order-info-label">Total Amount</span>
                                    <div class="order-total-price">$<?= number_format($order['total_amount'], 2) ?></div>
                                </div>

                                <div class="col-md-5 mb-3 mb-md-0">
                                    <span class="order-info-label">Delivery Address</span>
                                    <div class="order-address text-truncate">
                                        <i class="bi bi-geo-alt-fill text-muted me-1"></i>
                                        <?= htmlspecialchars($order['address']) ?>
                                    </div>
                                </div>

                                <div class="col-md-4 text-md-end d-flex flex-column align-items-end gap-2">
                                    
                                    <a href="user_order_details.php?order_id=<?= $order['id'] ?>" class="btn btn-light rounded-circle border" title="View Details">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>

                                    <?php if ($status == 'Pending'): ?>
                                        <form action="user_cancel_order.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger rounded-pill px-4 btn-sm">
                                                Cancel Order
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        
        <div class="empty-orders-container animate-fade-in">
            <i class="bi bi-cart-x empty-orders-icon"></i>
            <h3 style="font-family: var(--font-serif);">No orders yet</h3>
            <p class="text-muted mb-4">You haven't placed any orders yet. Fill your pantry with fresh goodness!</p>
            <a href="index.php" class="btn btn-primary rounded-pill px-5 py-3 shadow-sm">Start Shopping</a>
        </div>

    <?php endif; ?>

</body>
</html>