<?php
// orders/index.php
// Customer Order History using Stored Procedure sp_get_user_order_history
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = function_exists('get_app_root') ? get_app_root() : '/';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $rootPath . "login");
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

// Fetch first item and item counts for each order
$orderSummaries = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    try {
        $stmtItems = $pdo->prepare("
            SELECT oi.order_id, oi.product_id, oi.quantity, p.name AS product_name, p.image AS product_image 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id IN ($placeholders) 
            ORDER BY oi.id ASC
        ");
        $stmtItems->execute($orderIds);
        $rawItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rawItems as $item) {
            $oid = $item['order_id'];
            if (!isset($orderSummaries[$oid])) {
                $orderSummaries[$oid] = [
                    'first_image' => $item['product_image'] ?? '',
                    'first_name'  => $item['product_name'] ?? 'Fresh Harvest Produce',
                    'first_qty'   => (int)($item['quantity'] ?? 1),
                    'total_items' => 0
                ];
            }
            $orderSummaries[$oid]['total_items'] += 1;
        }
    } catch (PDOException $e) {
        error_log("Failed to load order item previews: " . $e->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
?>

<main class="orders-page-wrapper">
    <div class="container">

        <!-- Orders Header -->
        <div class="orders-header-row">
            <div>
                <nav class="orders-breadcrumb d-none d-md-flex" aria-label="Breadcrumb">
                    <a href="<?= $rootPath ?: './' ?>">Marketplace</a>
                    <i class="bi bi-chevron-right" style="font-size: 0.72rem;" aria-hidden="true"></i>
                    <span class="text-dark fw-medium">Order History</span>
                </nav>
                <div class="d-flex align-items-baseline gap-2">
                    <h1 class="orders-header-title mb-0">My Orders</h1>
                    <span class="orders-count-badge"><?= count($orders) ?></span>
                </div>
                <p class="orders-header-meta">Track deliveries and past purchases.</p>
            </div>
        </div>


        <?php if (count($orders) > 0): ?>
            <!-- Quick Status Filter Chips -->
            <div class="orders-filter-bar mb-3" aria-label="Filter Orders by Status">
                <div class="orders-filter-chips">
                    <button type="button" class="orders-chip-btn active" data-filter="all">
                        <span>All</span>
                        <span class="chip-count"><?= count($orders) ?></span>
                    </button>
                    <button type="button" class="orders-chip-btn" data-filter="pending">
                        <span>Pending</span>
                    </button>
                    <button type="button" class="orders-chip-btn" data-filter="shipped">
                        <span>Shipped</span>
                    </button>
                    <button type="button" class="orders-chip-btn" data-filter="delivered">
                        <span>Delivered</span>
                    </button>
                    <button type="button" class="orders-chip-btn" data-filter="cancelled">
                        <span>Cancelled</span>
                    </button>
                </div>
            </div>

            <div class="orders-list" id="ordersList">
                <?php foreach ($orders as $order): 
                    $status = $order['status'] ?: 'Pending';
                    $statusLower = strtolower($status);
                    $summary = $orderSummaries[$order['id']] ?? null;
                    $itemImage = $summary['first_image'] ?? '';
                    $itemName = $summary['first_name'] ?? 'Fresh Harvest Produce';
                    $totalItems = $summary['total_items'] ?? 1;
                    $extraCount = $totalItems - 1;
                    $imageExists = !empty($itemImage) && file_exists(__DIR__ . '/../assets/images/' . $itemImage);
                ?>
                    <div class="order-history-card shadow-sm" data-status="<?= $statusLower ?>">
                        <div class="order-card-top">
                            <div class="order-meta-lead">
                                <span class="order-card-ref">#<?= str_pad($order['id'], 6, "0", STR_PAD_LEFT) ?></span>
                                <span class="order-card-date">
                                    <span><?= date('M d, Y • h:i A', strtotime($order['created_at'])) ?></span>
                                </span>
                            </div>

                            <span class="order-status-badge status-<?= $statusLower ?>">
                                <span><?= htmlspecialchars($status) ?></span>
                            </span>
                        </div>

                        <div class="order-card-main">
                            <!-- 1. First Picture of what user chose to buy -->
                            <div class="order-card-item-preview">
                                <a href="<?= $rootPath ?>order/<?= $order['id'] ?>" class="order-thumb-wrap" aria-label="View <?= htmlspecialchars($itemName) ?>">
                                    <?php if ($imageExists): ?>
                                        <img src="<?= $rootPath ?>assets/images/<?= htmlspecialchars($itemImage) ?>" 
                                             alt="<?= htmlspecialchars($itemName) ?>" 
                                             class="order-product-thumb" 
                                             width="60" 
                                             height="60" 
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="order-thumb-fallback" aria-hidden="true">
                                            <i class="bi bi-box-seam"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                <div class="order-item-info">
                                    <a href="<?= $rootPath ?>order/<?= $order['id'] ?>" class="order-item-name text-truncate" title="<?= htmlspecialchars($itemName) ?>">
                                        <?= htmlspecialchars($itemName) ?>
                                    </a>
                                    <span class="order-item-count">
                                        <?php if ($extraCount > 0): ?>
                                            <span class="order-count-pill">+<?= $extraCount ?> more item<?= $extraCount > 1 ? 's' : '' ?></span>
                                        <?php else: ?>
                                            <span class="order-count-single">1 item</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="order-card-body-row">
                                <div class="order-card-dest-group">
                                    <span class="order-col-label">Delivery Destination</span>
                                    <p class="order-dest-val text-truncate mb-0" title="<?= htmlspecialchars($order['address'] ?: 'Customer address on file') ?>">
                                        <i class="bi bi-geo-alt text-muted me-1" aria-hidden="true"></i>
                                        <span><?= htmlspecialchars($order['address'] ?: 'Customer address on file') ?></span>
                                    </p>
                                </div>

                                <div class="order-card-detail-group">
                                    <span class="order-col-label">Total Amount</span>
                                    <div class="order-total-val">₱<?= number_format($order['total_amount'], 2) ?></div>
                                </div>
                            </div>

                            <div class="order-card-actions">
                                <a href="<?= $rootPath ?>order/<?= $order['id'] ?>" class="btn-order-view" aria-label="View details for order #<?= $order['id'] ?>">
                                    <span>View Details</span>
                                </a>

                                <?php if ($status == 'Pending'): ?>
                                    <form action="<?= $rootPath ?>orders/cancel" method="POST" class="order-cancel-form m-0" data-confirm="Are you sure you want to cancel order #<?= $order['id'] ?>? This cannot be undone." data-confirm-title="Cancel Order #<?= $order['id'] ?>?" data-confirm-btn="Yes, Cancel Order" data-confirm-type="danger">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <button type="submit" class="btn-order-cancel" aria-label="Cancel order #<?= $order['id'] ?>">
                                            <span>Cancel</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <script>
            document.querySelectorAll('.orders-chip-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.orders-chip-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const filter = this.getAttribute('data-filter');
                    const cards = document.querySelectorAll('.order-history-card');
                    let visibleCount = 0;
                    cards.forEach(card => {
                        if (filter === 'all' || card.getAttribute('data-status') === filter) {
                            card.style.display = '';
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
            </script>

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
