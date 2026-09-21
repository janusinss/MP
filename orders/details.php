<?php
// orders/details.php
// Customer Detailed Order View & Farmstead Fulfillment Telemetry
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$pos = strpos($scriptName, '/grocery_app');
if ($pos !== false) {
    $rootPath = substr($scriptName, 0, $pos + strlen('/grocery_app')) . '/';
} else {
    $rootPath = '/';
}

// 1. Security Check: Customer or Admin Auth
if (!isset($_SESSION['user_id']) && empty($_SESSION['admin_logged_in'])) {
    header("Location: " . $rootPath . "login");
    exit;
}

if (!isset($_GET['order_id'])) {
    header("Location: " . $rootPath . "orders");
    exit;
}

$order_id = (int)$_GET['order_id'];
$user_id = $_SESSION['user_id'] ?? null;

// 2. Fetch Order (Strict User Isolation: User can only access their own order unless Admin)
if (!empty($_SESSION['admin_logged_in'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
}
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: " . $rootPath . "orders");
    exit;
}

// 3. Fetch Items with Produce Details
$stmtItems = $pdo->prepare("SELECT order_items.*, products.name, products.price, products.image, products.category 
                            FROM order_items 
                            JOIN products ON order_items.product_id = products.id 
                            WHERE order_items.order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// 4. Financial Calculations
$itemsSubtotal = 0;
foreach ($items as $item) {
    $itemsSubtotal += $item['price'] * $item['quantity'];
}
$deliveryFee = 0.00;
$discountAmount = max(0, $itemsSubtotal - (float)$order['total_amount']);

$s = $order['status'];
$badgeClass = match($s) {
    'Delivered' => 'status-delivered',
    'Shipped'   => 'status-shipped',
    'Cancelled' => 'status-cancelled',
    default     => 'status-pending'
};
$statusIcon = match($s) {
    'Delivered' => 'bi-check-circle-fill',
    'Shipped'   => 'bi-truck',
    'Cancelled' => 'bi-x-circle-fill',
    default     => 'bi-clock-history'
};
$statusLabel = match($s) {
    'Delivered' => 'Delivered',
    'Shipped'   => 'Shipped',
    'Cancelled' => 'Cancelled',
    default     => 'Pending'
};

include __DIR__ . '/../includes/header.php';
?>

<main class="details-page-wrapper">
    <div class="details-container">

        <!-- 1. Breadcrumb -->
        <nav class="details-breadcrumb d-none d-md-flex" aria-label="Breadcrumb">
            <a href="<?= $rootPath ?: './' ?>">
                <span>Marketplace</span>
            </a>
            <i class="bi bi-chevron-right details-breadcrumb-sep" aria-hidden="true"></i>
            <a href="<?= $rootPath ?>orders">
                <span>Order History</span>
            </a>
            <i class="bi bi-chevron-right details-breadcrumb-sep" aria-hidden="true"></i>
            <span class="details-breadcrumb-current">Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
        </nav>

        <!-- 2. Header Row Card -->
        <header class="details-header-card animate-fade-in">
            <div class="details-header-left">
                <div class="details-header-title-row">
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= $rootPath ?>orders" class="details-mobile-back-btn d-md-none" aria-label="Back to Order History">
                            <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        </a>
                        <h1 class="details-order-title">
                            Order <span class="details-order-id-mono">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        </h1>
                    </div>
                    <span class="details-status-badge <?= $badgeClass ?>" role="status">
                        <i class="bi <?= $statusIcon ?>" aria-hidden="true"></i>
                        <span><?= $statusLabel ?></span>
                    </span>
                </div>
                <div class="details-meta-strip">
                    <span class="details-meta-item">
                        <span>Placed <?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                    </span>
                    <span class="details-meta-item">
                        <span><?= date('h:i A', strtotime($order['created_at'])) ?></span>
                    </span>
                    <span class="details-meta-item">
                        <span><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?></span>
                    </span>
                </div>
            </div>

            <div class="details-header-actions">
                <button type="button" class="btn-details-action btn-details-print" onclick="window.print()" title="Print physical invoice or save as PDF">
                    <i class="bi bi-printer" aria-hidden="true"></i>
                    <span>Print Receipt</span>
                </button>
                <a href="<?= $rootPath ?>orders" class="btn-details-action btn-details-primary d-none d-md-inline-flex">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    <span>Back to Orders</span>
                </a>
            </div>
        </header>

        <!-- 3. Fulfillment Progress Stepper -->
        <?php if ($s !== 'Cancelled'): ?>
            <section class="details-stepper-card animate-fade-in" aria-label="Order fulfillment progress">
                <div class="stepper-header">
                    <h2 class="stepper-title">Order Status</h2>
                </div>

                <div class="fulfillment-track">
                    <!-- Step 1: Placed -->
                    <div class="fulfillment-step is-done">
                        <div class="step-node" aria-hidden="true">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <div class="step-meta">
                            <span class="step-label">Placed</span>
                            <span class="step-caption"><?= date('M d, h:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                    </div>

                    <!-- Step 2: Preparing -->
                    <div class="fulfillment-step <?= ($s === 'Shipped' || $s === 'Delivered') ? 'is-done' : ($s === 'Pending' ? 'is-current' : 'is-pending') ?>">
                        <div class="step-node" aria-hidden="true">
                            <?php if ($s === 'Shipped' || $s === 'Delivered'): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <i class="bi bi-box-seam"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-meta">
                            <span class="step-label">Preparing</span>
                            <span class="step-caption"><?= ($s === 'Pending') ? 'In progress' : 'Completed' ?></span>
                        </div>
                    </div>

                    <!-- Step 3: Shipped -->
                    <div class="fulfillment-step <?= ($s === 'Delivered') ? 'is-done' : ($s === 'Shipped' ? 'is-current' : 'is-pending') ?>">
                        <div class="step-node" aria-hidden="true">
                            <?php if ($s === 'Delivered'): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <i class="bi bi-truck"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-meta">
                            <span class="step-label">Shipped</span>
                            <span class="step-caption"><?= ($s === 'Shipped') ? 'In transit' : (($s === 'Delivered') ? 'Dispatched' : 'Pending') ?></span>
                        </div>
                    </div>

                    <!-- Step 4: Delivered -->
                    <div class="fulfillment-step <?= ($s === 'Delivered') ? 'is-done' : 'is-pending' ?>">
                        <div class="step-node" aria-hidden="true">
                            <?php if ($s === 'Delivered'): ?>
                                <i class="bi bi-check-circle-fill"></i>
                            <?php else: ?>
                                <i class="bi bi-house-door"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-meta">
                            <span class="step-label">Delivered</span>
                            <span class="step-caption"><?= ($s === 'Delivered') ? 'Delivered' : 'Pending' ?></span>
                        </div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <div class="order-cancelled-banner animate-fade-in" role="alert">
                <i class="bi bi-info-circle-fill cancelled-icon" aria-hidden="true"></i>
                <div>
                    <h2 class="cancelled-title">Order Cancelled</h2>
                    <p class="cancelled-text">
                        This order was cancelled. Reserved items have been returned to inventory.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- 4. Two-Column Asymmetric Content Grid -->
        <div class="details-grid">

            <!-- Column 1: Items & Order Accounting -->
            <div class="details-main-col">
                <section class="details-card animate-fade-in" aria-labelledby="purchased-items-heading">
                    <div class="details-card-header">
                        <h2 id="purchased-items-heading" class="details-card-title">Order Items</h2>
                        <span class="details-items-count"><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?></span>
                    </div>

                    <div class="details-items-list">
                        <?php foreach ($items as $item): ?>
                            <div class="details-item-row">
                                <div class="details-item-main">
                                    <img src="<?= $rootPath ?>assets/images/<?= htmlspecialchars($item['image'] ?: 'default.jpg') ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                         class="details-item-thumb" 
                                         width="64" 
                                         height="64" 
                                         loading="lazy">
                                    <div class="details-item-info">
                                        <h3 class="details-item-name"><?= htmlspecialchars($item['name']) ?></h3>
                                        <div class="details-item-meta">
                                            <span>$<?= number_format($item['price'], 2) ?> each</span>
                                            <span class="details-item-qty">Qty: <?= $item['quantity'] ?></span>
                                            <?php if (!empty($item['category'])): ?>
                                                <span class="text-muted small d-none d-sm-inline">&bull; <?= htmlspecialchars($item['category']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="details-item-total">
                                    $<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Financial Summary Breakdown -->
                    <div class="details-summary-card">
                        <div class="details-summary-row">
                            <span>Subtotal</span>
                            <span class="font-monospace fw-semibold">$<?= number_format($itemsSubtotal, 2) ?></span>
                        </div>
                        <div class="details-summary-row">
                            <span>Delivery</span>
                            <span class="text-success fw-semibold">FREE</span>
                        </div>
                        <?php if ($discountAmount > 0.005): ?>
                            <div class="details-summary-row discount-row">
                                <span>Promo Savings</span>
                                <span class="font-monospace fw-bold">-$<?= number_format($discountAmount, 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="details-summary-divider"></div>
                        <div class="details-total-row">
                            <span class="details-total-label">Grand Total</span>
                            <span class="details-total-value">$<?= number_format($order['total_amount'], 2) ?></span>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Column 2: Fulfillment Details & Actions -->
            <aside class="details-side-col">

                <!-- Card 1: Delivery Destination -->
                <section class="details-side-card animate-fade-in" aria-labelledby="delivery-dest-heading">
                    <h2 id="delivery-dest-heading" class="details-side-title">Delivery Destination</h2>
                    <div class="details-recipient-name">
                        <?= htmlspecialchars($order['customer_name']) ?>
                    </div>
                    <p class="details-recipient-address">
                        <?= nl2br(htmlspecialchars($order['address'])) ?>
                    </p>
                </section>

                <!-- Card 2: Payment Details -->
                <section class="details-side-card animate-fade-in" aria-labelledby="payment-details-heading">
                    <h2 id="payment-details-heading" class="details-side-title">Payment Method</h2>
                    <div class="details-payment-method">
                        <div class="payment-method-icon" aria-hidden="true">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div>
                            <div class="payment-method-name">Cash on Delivery</div>
                            <div class="payment-method-sub">Pay upon delivery</div>
                        </div>
                    </div>
                    <?php if ($s === 'Delivered'): ?>
                        <span class="payment-status-pill bg-success-subtle text-success">
                            Settled upon delivery
                        </span>
                    <?php elseif ($s === 'Cancelled'): ?>
                        <span class="payment-status-pill bg-secondary-subtle text-secondary">
                            Voided with order
                        </span>
                    <?php else: ?>
                        <span class="payment-status-pill bg-warning-subtle text-warning-emphasis">
                            Payable to courier
                        </span>
                    <?php endif; ?>
                </section>

                <!-- Card 3: Cancellation Option (Pending Orders Only) -->
                <?php if ($s === 'Pending'): ?>
                    <section class="details-side-card details-cancel-card animate-fade-in" aria-labelledby="cancel-order-heading">
                        <h2 id="cancel-order-heading" class="details-side-title">Cancel Order</h2>
                        <p class="details-cancel-desc">
                            You can cancel your order while it is still pending.
                        </p>
                        <form action="<?= $rootPath ?>orders/cancel" method="POST" onsubmit="return confirm('Are you sure you want to cancel order #<?= $order['id'] ?>?');" class="m-0">
                            <?= csrf_input() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" class="btn-details-cancel">
                                <span>Cancel Order</span>
                            </button>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Card 4: Need Help / Support Box -->
                <div class="details-support-box animate-fade-in">
                    <a href="<?= $rootPath ?>contact" class="details-support-link">
                        <i class="bi bi-chat-dots" aria-hidden="true"></i>
                        <span>Need help? Contact Support</span>
                    </a>
                </div>

            </aside>

        </div>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
