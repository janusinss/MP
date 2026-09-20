<?php
// orders/index.php
// Customer Order History using Stored Procedure sp_get_user_order_history
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch orders for THIS user only (Stored Procedure with graceful SELECT fallback)
try {
    $stmt = $pdo->prepare("CALL sp_get_user_order_history(?)");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
} catch (PDOException $e) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../includes/header.php';
?>

<main class="orders-page-wrapper">
    <div class="container">

        <!-- Orders Header -->
        <div class="orders-header-row">
            <div>
                <nav class="orders-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= $rootPath ?: './' ?>"><i class="bi bi-house-door"></i> Marketplace</a>
                    <i class="bi bi-chevron-right" style="font-size: 0.72rem;"></i>
                    <span class="text-dark fw-medium">Order History</span>
                </nav>
                <h1 class="orders-header-title">My Orders</h1>
                <p class="orders-header-meta">Track and manage your farm-to-table deliveries and past seasonal purchases.</p>
            </div>
            <div class="orders-header-actions">
                <span class="orders-count-indicator">
                    <i class="bi bi-box-seam text-success" aria-hidden="true"></i>
                    <span><?= count($orders) ?> <?= count($orders) === 1 ? 'Order' : 'Orders' ?> Placed</span>
                </span>
                <a href="<?= $rootPath ?>profile" class="btn-continue-browsing">
                    <i class="bi bi-person-gear" aria-hidden="true"></i>
                    <span>Account Settings</span>
                </a>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled'): ?>
            <div class="alert alert-warning border-0 rounded-3 mb-4 d-flex align-items-center gap-2 py-2 px-3 small" role="alert">
                <i class="bi bi-check-circle-fill text-warning flex-shrink-0" aria-hidden="true"></i>
                <div>Your order has been successfully cancelled and inventory restocked.</div>
            </div>
        <?php endif; ?>

        <?php if (count($orders) > 0): ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): 
                    $status = $order['status'] ?: 'Pending';
                    $statusLower = strtolower($status);
                    $icon = 'bi-hourglass-split';

                    if ($status == 'Shipped') {
                        $icon = 'bi-truck';
                    } elseif ($status == 'Delivered') {
                        $icon = 'bi-check-circle-fill';
                    } elseif ($status == 'Cancelled') {
                        $icon = 'bi-x-circle-fill';
                    }
                ?>
                    <div class="order-history-card">
                        <div class="order-card-top">
                            <div class="order-meta-lead">
                                <span class="order-card-ref">#<?= str_pad($order['id'], 6, "0", STR_PAD_LEFT) ?></span>
                                <span class="order-card-date">
                                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                                    <span><?= date('M d, Y • h:i A', strtotime($order['created_at'])) ?></span>
                                </span>
                            </div>

                            <span class="order-status-badge status-<?= $statusLower ?>">
                                <i class="bi <?= $icon ?>" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($status) ?></span>
                            </span>
                        </div>

                        <div class="order-card-main">
                            <div>
                                <span class="order-col-label">Total Paid / Due</span>
                                <div class="order-total-val">$<?= number_format($order['total_amount'], 2) ?></div>
                            </div>

                            <div>
                                <span class="order-col-label">Delivery Destination</span>
                                <p class="order-dest-val text-truncate">
                                    <i class="bi bi-geo-alt-fill text-muted me-1" aria-hidden="true"></i>
                                    <?= htmlspecialchars($order['address'] ?: 'Customer address on file') ?>
                                </p>
                            </div>

                            <div class="order-card-actions">
                                <a href="<?= $rootPath ?>order/<?= $order['id'] ?>" class="btn-order-view">
                                    <i class="bi bi-receipt" aria-hidden="true"></i>
                                    <span>View Details</span>
                                </a>

                                <?php if ($status == 'Pending'): ?>
                                    <form action="<?= $rootPath ?>orders/cancel" method="POST" onsubmit="return confirm('Are you sure you want to cancel order #<?= $order['id'] ?>?');" class="m-0">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <button type="submit" class="btn-order-cancel">
                                            <i class="bi bi-x-circle" aria-hidden="true"></i>
                                            <span>Cancel Order</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>

            <div class="orders-empty-card">
                <div class="orders-empty-icon" aria-hidden="true">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h2 class="orders-empty-title">No orders placed yet</h2>
                <p class="orders-empty-text">
                    You have not placed any harvest orders yet. Discover today's morning harvest from regional organic family farms!
                </p>
                <a href="<?= $rootPath ?: './' ?>" class="btn-continue-browsing d-inline-flex">
                    <i class="bi bi-basket me-1" aria-hidden="true"></i>
                    <span>Start Shopping Fresh</span>
                </a>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
