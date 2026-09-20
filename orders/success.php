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
    <div class="success-wide-container">
        
        <div class="receipt-card success-wide-card">
            
            <!-- Wide Header Section: Lead + Telemetry Strip -->
            <div class="success-card-header">
                <div class="success-header-lead">
                    <div class="success-status-icon" aria-hidden="true">
                        <i class="bi bi-check2"></i>
                    </div>
                    <div>
                        <span class="success-kicker">Harvest Dispatch Confirmed</span>
                        <h1 class="success-title">Order Confirmed &amp; Scheduled!</h1>
                        <p class="success-subtitle">
                            Thank you, <?= htmlspecialchars($order['customer_name']) ?>. Your farm-fresh harvest is scheduled for careful packing and temperature-controlled courier transit.
                        </p>
                    </div>
                </div>

                <!-- Telemetry Meta Panel (Right-aligned in header) -->
                <div class="success-meta-panel">
                    <div class="success-meta-row">
                        <span class="success-meta-lbl">Order Ref</span>
                        <span class="success-meta-val is-mono">#<?= str_pad($order['id'], 6, "0", STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="success-meta-row">
                        <span class="success-meta-lbl">Placed</span>
                        <span class="success-meta-val"><?= date('M d, Y • h:i A', strtotime($order['created_at'])) ?></span>
                    </div>
                    <div class="success-meta-row">
                        <span class="success-meta-lbl">Payment</span>
                        <span class="success-meta-val text-success">
                            <i class="bi bi-cash-stack me-1" aria-hidden="true"></i> Cash on Delivery
                        </span>
                    </div>
                    <div class="success-meta-row">
                        <span class="success-meta-lbl">Status</span>
                        <span class="success-meta-val">
                            <i class="bi bi-clock-history me-1 text-primary" aria-hidden="true"></i> <?= htmlspecialchars($order['status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Wide Card Body: 2 Columns (7:5) -->
            <div class="success-card-body">
                <div class="row g-4 g-lg-5">
                    
                    <!-- Left Column: Delivery Details & Transit Timeline -->
                    <div class="col-lg-7">
                        <!-- Delivery Destination Subcard -->
                        <div class="success-subcard">
                            <div class="success-subcard-title">
                                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                                <span>Delivery Destination</span>
                            </div>
                            <div class="success-destination-name"><?= htmlspecialchars($order['customer_name']) ?></div>
                            <p class="success-destination-address"><?= htmlspecialchars($order['address']) ?></p>
                            <p class="success-destination-hint">
                                <i class="bi bi-shield-check" aria-hidden="true"></i>
                                <span>Courier contactless drop-off enabled for this location</span>
                            </p>
                        </div>

                        <!-- Dispatch Transit Tracker Subcard -->
                        <div class="success-subcard mb-0">
                            <div class="success-subcard-title">
                                <i class="bi bi-truck" aria-hidden="true"></i>
                                <span>Fulfillment Progress</span>
                            </div>
                            
                            <div class="dispatch-stepper" aria-label="Order fulfillment progress">
                                <div class="dispatch-step is-done">
                                    <div class="dispatch-step-dot" aria-hidden="true"><i class="bi bi-check"></i></div>
                                    <span class="dispatch-step-label">Order Placed</span>
                                </div>
                                <div class="dispatch-step is-active">
                                    <div class="dispatch-step-dot" aria-hidden="true">2</div>
                                    <span class="dispatch-step-label">Harvest Packing</span>
                                </div>
                                <div class="dispatch-step">
                                    <div class="dispatch-step-dot" aria-hidden="true">3</div>
                                    <span class="dispatch-step-label">Courier Transit</span>
                                </div>
                                <div class="dispatch-step">
                                    <div class="dispatch-step-dot" aria-hidden="true">4</div>
                                    <span class="dispatch-step-label">Delivered</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Itemized Harvest Basket & Pricing Calculation -->
                    <div class="col-lg-5">
                        <div class="success-summary-panel">
                            <div class="success-subcard-title mb-3">
                                <i class="bi bi-basket2-fill" aria-hidden="true"></i>
                                <span>Harvest Basket (<?= count($items) ?> items)</span>
                            </div>

                            <!-- Scrollable Itemized List -->
                            <div class="success-items-scroll">
                                <?php 
                                $subtotal = 0;
                                foreach ($items as $item): 
                                    $lineTotal = $item['price'] * $item['quantity'];
                                    $subtotal += $lineTotal;
                                    $imgFile = !empty($item['image']) ? $item['image'] : 'placeholder.jpg';
                                    $imgSrc = $rootPath . 'assets/images/' . htmlspecialchars($imgFile);
                                ?>
                                    <div class="success-item-row">
                                        <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="success-item-thumb" onerror="this.onerror=null; this.src='<?= $rootPath ?>assets/images/placeholder.jpg';">
                                        <div class="success-item-details">
                                            <h4 class="success-item-name" title="<?= htmlspecialchars($item['name']) ?>">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </h4>
                                            <div class="success-item-rate">
                                                <?= (int)$item['quantity'] ?> &times; $<?= number_format($item['price'], 2) ?>
                                            </div>
                                        </div>
                                        <div class="success-item-total">$<?= number_format($lineTotal, 2) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pricing Calculations -->
                            <div class="success-calc-table">
                                <div class="success-calc-row">
                                    <span>Produce Subtotal</span>
                                    <span class="success-calc-val">$<?= number_format($subtotal, 2) ?></span>
                                </div>

                                <?php 
                                $discount = $subtotal - $order['total_amount']; 
                                if ($discount > 0.005): 
                                ?>
                                    <div class="receipt-calc-row text-success success-calc-row">
                                        <span>
                                            <i class="bi bi-tag-fill me-1" aria-hidden="true"></i> Promotional Discount
                                        </span>
                                        <span class="success-calc-val">-$<?= number_format($discount, 2) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="success-calc-row">
                                    <span>Farm Route Delivery</span>
                                    <span class="success-calc-val text-success">Complimentary (Free)</span>
                                </div>

                                <div class="success-total-row">
                                    <div>
                                        <span class="success-total-label">Total Paid / Due</span>
                                        <small class="d-block text-muted" style="font-size: 0.75rem;">Includes all taxes &amp; packaging</small>
                                    </div>
                                    <div class="success-total-amount">$<?= number_format($order['total_amount'], 2) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Bottom Action Bar Spanning Full Width -->
                <div class="success-bottom-bar no-print">
                    <div class="success-footer-meta">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?= $rootPath ?>orders" class="success-history-link">
                                <i class="bi bi-receipt-cutoff me-1" aria-hidden="true"></i> View in Order History &rarr;
                            </a>
                        <?php endif; ?>

                        <span class="success-guarantee-badge">
                            <i class="bi bi-patch-check-fill text-success" aria-hidden="true"></i>
                            100% Crisp Harvest Quality Guarantee
                        </span>
                    </div>

                    <div class="success-actions-group">
                        <button type="button" onclick="window.print()" class="btn-print-receipt btn-print">
                            <i class="bi bi-printer" aria-hidden="true"></i>
                            <span>Print Receipt</span>
                        </button>
                        
                        <a href="<?= $rootPath ?>" class="btn-continue-shopping btn-continue">
                            <i class="bi bi-basket" aria-hidden="true"></i>
                            <span>Continue Shopping</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
