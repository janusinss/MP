<?php
// orders/success.php
// Order Confirmation & Printable Receipt
require_once __DIR__ . '/../config/db.php';
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

// 1. Validate Order ID existence
if (!isset($_GET['orderid'])) {
    header("Location: " . $rootPath);
    exit;
}

$order_id = (int)$_GET['orderid'];

// 2. SECURE FETCH: Ensure the order belongs to the user OR the admin OR was placed in this session
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Admin can view any order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
} elseif (isset($_SESSION['user_id'])) {
    // Logged in customer can view their own orders or their current session order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND (user_id = ? OR id = ?)");
    $stmt->execute([$order_id, $_SESSION['user_id'], $_SESSION['last_order_id'] ?? 0]);
} elseif (isset($_SESSION['last_order_id']) && (int)$_SESSION['last_order_id'] === $order_id) {
    // Guest customer viewing the order they just placed
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
} else {
    // Fallback: If accessed directly without auth or session, redirect cleanly to shop
    header("Location: " . $rootPath);
    exit;
}

$order = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

// 3. If no order found, redirect
if (!$order) {
    header("Location: " . $rootPath);
    exit;
}

// Fetch items only after security check passes
$stmtItems = $pdo->prepare("SELECT order_items.*, products.name, products.price, products.image 
                            FROM order_items 
                            JOIN products ON order_items.product_id = products.id 
                            WHERE order_items.order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<main class="success-page-wrapper">
    <div class="success-container">
        
        <div class="receipt-card">
            
            <!-- Clean Header Section -->
            <div class="success-card-header">
                <div class="success-status-icon" aria-hidden="true">
                    <i class="bi bi-check2"></i>
                </div>
                <span class="success-kicker">Harvest Dispatch Confirmed</span>
                <h1 class="success-title">Order Confirmed!</h1>
                <p class="success-subtitle">
                    Thank you, <?= htmlspecialchars($order['customer_name']) ?>. Your farm-fresh harvest is scheduled for careful packing and temperature-controlled courier transit.
                </p>
            </div>

            <!-- Telemetry Details Strip -->
            <div class="receipt-meta-strip">
                <div class="receipt-meta-cell">
                    <span class="receipt-meta-label">Order Reference</span>
                    <span class="receipt-meta-val receipt-order-ref">#<?= str_pad($order['id'], 6, "0", STR_PAD_LEFT) ?></span>
                </div>
                <div class="receipt-meta-cell">
                    <span class="receipt-meta-label">Date &amp; Time</span>
                    <span class="receipt-meta-val"><?= date('M d, Y • h:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="receipt-meta-cell">
                    <span class="receipt-meta-label">Payment Status</span>
                    <span class="receipt-meta-val text-success">
                        <i class="bi bi-cash-stack me-1" aria-hidden="true"></i> Cash on Delivery
                    </span>
                </div>
                <div class="receipt-meta-cell">
                    <span class="receipt-meta-label">Fulfillment Status</span>
                    <span class="receipt-meta-val">
                        <i class="bi bi-clock-history me-1 text-primary" aria-hidden="true"></i> <?= htmlspecialchars($order['status']) ?>
                    </span>
                </div>
            </div>

            <!-- Receipt Body -->
            <div class="receipt-body">
                
                <!-- Delivery Destination Block -->
                <div class="receipt-fulfillment-block">
                    <span class="receipt-section-title d-block">Delivery Destination</span>
                    <div class="receipt-recipient-name"><?= htmlspecialchars($order['customer_name']) ?></div>
                    <p class="receipt-recipient-address"><?= htmlspecialchars($order['address']) ?></p>
                </div>

                <!-- Itemized Harvest List -->
                <div class="receipt-items-table">
                    <span class="receipt-section-title d-block">Itemized Harvest Basket</span>
                    <?php 
                    $subtotal = 0;
                    foreach ($items as $item): 
                        $lineTotal = $item['price'] * $item['quantity'];
                        $subtotal += $lineTotal;
                    ?>
                        <div class="receipt-item-row">
                            <div class="receipt-item-desc">
                                <span class="receipt-item-qty-tag"><?= (int)$item['quantity'] ?>&times;</span>
                                <span class="receipt-item-name" title="<?= htmlspecialchars($item['name']) ?>">
                                    <?= htmlspecialchars($item['name']) ?>
                                </span>
                            </div>
                            <div class="receipt-item-price">$<?= number_format($lineTotal, 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Price Breakdown Calculations -->
                <div class="receipt-calc-table">
                    <div class="receipt-calc-row">
                        <span>Produce Subtotal</span>
                        <span class="receipt-calc-val">$<?= number_format($subtotal, 2) ?></span>
                    </div>

                    <?php 
                    $discount = $subtotal - $order['total_amount']; 
                    if ($discount > 0.005): 
                    ?>
                        <div class="receipt-calc-row text-success">
                            <span>
                                <i class="bi bi-tag-fill me-1" aria-hidden="true"></i> Promotional Discount
                            </span>
                            <span class="receipt-calc-val">-$<?= number_format($discount, 2) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="receipt-calc-row">
                        <span>Farm Route Delivery</span>
                        <span class="receipt-calc-val text-success">Complimentary (Free)</span>
                    </div>

                    <div class="receipt-total-row">
                        <div>
                            <span class="receipt-total-label">Total Paid / Due</span>
                            <small class="d-block text-muted" style="font-size: 0.75rem;">Includes all taxes &amp; packaging</small>
                        </div>
                        <div class="receipt-total-amount">$<?= number_format($order['total_amount'], 2) ?></div>
                    </div>
                </div>

                <!-- Action Buttons (No Print) -->
                <div class="receipt-actions no-print">
                    <button type="button" onclick="window.print()" class="btn-print-receipt btn-print">
                        <i class="bi bi-printer" aria-hidden="true"></i>
                        <span>Print Receipt</span>
                    </button>
                    
                    <a href="<?= $rootPath ?>" class="btn-continue-shopping btn-continue">
                        <i class="bi bi-basket" aria-hidden="true"></i>
                        <span>Continue Shopping</span>
                    </a>
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="receipt-history-link-wrap no-print">
                        <a href="<?= $rootPath ?>orders" class="receipt-history-link">
                            <i class="bi bi-receipt-cutoff me-1" aria-hidden="true"></i> View in Order History &rarr;
                        </a>
                    </div>
                <?php endif; ?>

                <p class="receipt-guarantee-note">
                    <i class="bi bi-shield-check me-1 text-success" aria-hidden="true"></i>
                    All deliveries are covered by our 100% Crisp Harvest Quality Guarantee.
                </p>

            </div>

        </div>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
