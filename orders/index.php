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

        <nav class="profile-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= $rootPath ?: './' ?>"><i class="bi bi-house-door"></i> Home</a>
            <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
            <span class="text-dark fw-medium">Order History</span>
        </nav>

        <div class="orders-page-header">
            <div>
                <h1 class="orders-title">My Orders</h1>
                <p class="orders-subtitle">Track your farm-to-table deliveries and past seasonal purchases.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm fw-medium">
                    <i class="bi bi-box-seam text-success me-1"></i> <?= count($orders) ?> <?= count($orders) === 1 ? 'Order' : 'Orders' ?> Placed
                </span>
                <a href="<?= $rootPath ?>profile" class="btn btn-outline-secondary rounded-pill px-3 py-2 btn-sm fw-medium">
                    <i class="bi bi-person-gear me-1"></i> Account Settings
                </a>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled'): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-check-circle-fill text-warning fs-5"></i>
                <div>Your order has been successfully cancelled and inventory restocked.</div>
            </div>
        <?php endif; ?>

        <?php if (count($orders) > 0): ?>
            <div class="row g-4">
                <?php foreach ($orders as $order): ?>
                    <div class="col-12">
                        <div class="order-card shadow-sm">
                            <div class="order-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-white border rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 44px; height: 44px;">
                                        <i class="bi bi-box-seam text-success fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="order-id">#<?= str_pad($order['id'], 6, "0", STR_PAD_LEFT) ?></span>
                                        <div class="order-date">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= date('M d, Y • h:i A', strtotime($order['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                $status = $order['status'] ?: 'Pending';
                                $badgeClass = 'status-pending';
                                $icon = 'bi-hourglass-split';

                                if ($status == 'Shipped') {
                                    $badgeClass = 'status-shipped';
                                    $icon = 'bi-truck';
                                }
                                if ($status == 'Delivered') {
                                    $badgeClass = 'status-delivered';
                                    $icon = 'bi-check-circle-fill';
                                }
                                if ($status == 'Cancelled') {
                                    $badgeClass = 'status-cancelled';
                                    $icon = 'bi-x-circle-fill';
                                }
                                ?>
                                <div class="status-pill <?= $badgeClass ?>">
                                    <i class="bi <?= $icon ?>"></i> <?= $status ?>
                                </div>
                            </div>

                            <div class="order-body">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-3 col-6">
                                        <span class="order-info-label">Order Total</span>
                                        <div class="order-total-price">$<?= number_format($order['total_amount'], 2) ?></div>
                                    </div>

                                    <div class="col-md-5 col-12">
                                        <span class="order-info-label">Delivery Destination</span>
                                        <div class="order-address text-truncate">
                                            <i class="bi bi-geo-alt-fill text-muted me-1"></i>
                                            <?= htmlspecialchars($order['address'] ?: 'Customer address on file') ?>
                                        </div>
                                    </div>

                                    <div class="col-md-4 col-12 d-flex justify-content-md-end align-items-center gap-2 flex-wrap">
                                        <a href="<?= $rootPath ?>order/<?= $order['id'] ?>" class="btn btn-outline-dark rounded-pill px-3 py-2 btn-sm fw-medium d-inline-flex align-items-center gap-2">
                                            <i class="bi bi-receipt"></i>
                                            <span>View Details</span>
                                        </a>

                                        <?php if ($status == 'Pending'): ?>
                                            <form action="<?= $rootPath ?>orders/cancel" method="POST" onsubmit="return confirm('Are you sure you want to cancel order #<?= $order['id'] ?>?');" class="m-0">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger rounded-pill px-3 py-2 btn-sm fw-medium">
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

            <div class="empty-orders-container animate-fade-in shadow-xs">
                <i class="bi bi-basket3 empty-orders-icon" style="color: var(--color-primary, #15803d); opacity: 0.4;"></i>
                <h3 style="font-family: var(--font-serif); font-weight: 700;">No orders yet</h3>
                <p class="text-muted mb-4" style="max-width: 480px; margin-left: auto; margin-right: auto;">
                    You haven't placed any seasonal orders yet. Discover today's fresh morning harvest from local family farms!
                </p>
                <a href="<?= $rootPath ?: './' ?>#catalog" class="btn btn-primary rounded-pill px-5 py-2 fw-semibold shadow-sm">
                    Start Shopping Fresh
                </a>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
