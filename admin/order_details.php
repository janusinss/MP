<?php
// admin/order_details.php
// FreshCart Admin Portal - Order Inspection & Fulfillment Workspace
// Compliant with UI_Always.md & frontend.md standards
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
$rootPath = function_exists('get_app_root') ? get_app_root() : '/';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: " . $rootPath . "login");
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($order_id <= 0) {
    die("Order ID missing.");
}

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf_token()) {
        http_response_code(403);
        die("Security validation failed. Invalid CSRF token.");
    }
    $allowedStatuses = ['Pending', 'Shipped', 'Delivered', 'Cancelled'];
    $new_status = $_POST['status'] ?? '';
    if (in_array($new_status, $allowedStatuses, true)) {
        $stmtUpdate = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmtUpdate->execute([$new_status, $order_id]);
    }
    
    header("Location: order_details.php?order_id=" . $order_id . "&msg=updated");
    exit;
}

// Fetch Data
try {
    $stmt = $pdo->prepare("SELECT orders.*, users.email AS customer_email 
                           FROM orders 
                           LEFT JOIN users ON orders.user_id = users.id 
                           WHERE orders.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) { die("Order not found!"); }

    $sqlItems = "SELECT order_items.*, products.name, products.price, products.image 
                 FROM order_items 
                 JOIN products ON order_items.product_id = products.id 
                 WHERE order_items.order_id = ?";
    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute([$order_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Admin Order Details Error: " . $e->getMessage());
    die("An unexpected error occurred while loading order details.");
}

$s = $order['status'];
$pillClass = match($s) {
    'Delivered' => 'status-delivered',
    'Shipped' => 'status-shipped',
    'Pending' => 'status-pending',
    default => 'status-cancelled'
};

// Calculate Stepper State
$step1Class = 'completed';
$step2Class = '';
$step3Class = '';
$step4Class = '';
$progressWidth = '15%';

if ($s === 'Cancelled') {
    $step2Class = 'cancelled';
    $progressWidth = '35%';
} elseif ($s === 'Pending') {
    $step2Class = 'active';
    $progressWidth = '35%';
} elseif ($s === 'Shipped') {
    $step2Class = 'completed';
    $step3Class = 'active';
    $progressWidth = '68%';
} elseif ($s === 'Delivered') {
    $step2Class = 'completed';
    $step3Class = 'completed';
    $step4Class = 'completed';
    $progressWidth = '100%';
}

// Avatar Initials
$nameParts = explode(' ', trim($order['customer_name'] ?? 'Customer'));
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
if (empty($initials)) $initials = 'CU';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?> | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <script>
        // Security: Defeat Back-Forward Cache (bfcache) & History Navigation Leaks after Logout
        (function() {
            function enforceFreshAuth() {
                var navEntries = window.performance && window.performance.getEntriesByType ? window.performance.getEntriesByType('navigation') : null;
                var isBackForward = (navEntries && navEntries.length > 0 && navEntries[0].type === 'back_forward') || 
                                    (window.performance && window.performance.navigation && window.performance.navigation.type === 2);
                if (isBackForward) {
                    window.location.reload();
                }
            }
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                } else {
                    enforceFreshAuth();
                }
            });
        })();
    </script>
</head>
<body class="admin-body" style="background-color: var(--color-canvas, #F7F6F2); color: #0f172a; min-height: 100vh;">

    <div class="container py-4 order-details-wrapper">

        <!-- Top Navigation & Actions Bar -->
        <header class="admin-order-topbar no-print" aria-label="Order Navigation">
            <a href="index.php?view=orders" class="admin-order-back-btn" aria-label="Back to Orders Directory">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> <span>Back to Orders</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <button type="button" onclick="window.print()" class="admin-order-action-btn" aria-label="Print Order Invoice">
                    <i class="bi bi-printer" aria-hidden="true"></i> <span>Print Invoice</span>
                </button>
            </div>
        </header>


        <!-- Order View Header -->
        <section class="admin-order-header" aria-labelledby="orderTitle">
            <div class="admin-order-header-info">
                <span class="admin-kicker">Fulfillment &amp; Order Logistics</span>
                <div class="admin-order-title-row">
                    <h1 id="orderTitle" class="admin-order-title">Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></h1>
                    <span class="admin-status-pill <?= $pillClass ?>">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;" aria-hidden="true"></i> <?= htmlspecialchars($s) ?>
                    </span>
                </div>
                <p class="admin-order-meta">
                    <i class="bi bi-calendar3" aria-hidden="true"></i> Placed on <?= date('F d, Y \a\t h:i A', strtotime($order['created_at'])) ?>
                </p>
            </div>
            <div class="text-end d-none d-md-block">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Grand Total</div>
                <div class="fw-bold text-dark fs-3" style="font-variant-numeric: tabular-nums;">
                    $<?= number_format((float)$order['total_amount'], 2) ?>
                </div>
            </div>
        </section>

        <!-- 4-Stage Fulfillment Stepper Pipeline -->
        <div class="admin-stepper-card no-print" aria-label="Order Fulfillment Progress">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Fulfillment Journey</span>
                <span class="small fw-semibold text-dark">Status: <?= htmlspecialchars($s) ?></span>
            </div>
            <div class="admin-stepper-track" role="list">
                <div class="admin-stepper-progress" style="width: <?= $progressWidth ?>;"></div>
                
                <div class="admin-step-item <?= $step1Class ?>" role="listitem">
                    <div class="admin-step-circle"><i class="bi bi-check-lg" aria-hidden="true"></i></div>
                    <span class="admin-step-label">Placed</span>
                </div>
                <div class="admin-step-item <?= $step2Class ?>" role="listitem">
                    <div class="admin-step-circle">
                        <?php if ($step2Class === 'completed'): ?><i class="bi bi-check-lg" aria-hidden="true"></i>
                        <?php elseif ($step2Class === 'cancelled'): ?><i class="bi bi-x-lg" aria-hidden="true"></i>
                        <?php else: ?>2<?php endif; ?>
                    </div>
                    <span class="admin-step-label"><?= $s === 'Cancelled' ? 'Cancelled' : 'Confirmed' ?></span>
                </div>
                <div class="admin-step-item <?= $step3Class ?>" role="listitem">
                    <div class="admin-step-circle">
                        <?php if ($step3Class === 'completed'): ?><i class="bi bi-check-lg" aria-hidden="true"></i>
                        <?php else: ?>3<?php endif; ?>
                    </div>
                    <span class="admin-step-label">Shipped</span>
                </div>
                <div class="admin-step-item <?= $step4Class ?>" role="listitem">
                    <div class="admin-step-circle">
                        <?php if ($step4Class === 'completed'): ?><i class="bi bi-check-lg" aria-hidden="true"></i>
                        <?php else: ?>4<?php endif; ?>
                    </div>
                    <span class="admin-step-label">Delivered</span>
                </div>
            </div>
        </div>

        <!-- 2-Column Responsive Workspace Grid -->
        <div class="row g-4 mb-4">
            <!-- Col 1: Customer Details & Order Items -->
            <div class="col-lg-7">
                <!-- Customer Details Card -->
                <div class="admin-card mb-4">
                    <h2 class="admin-card-heading mb-3">Customer &amp; Delivery Destination</h2>
                    
                    <div class="admin-customer-profile">
                        <div class="admin-customer-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></div>
                        <div class="admin-customer-info">
                            <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Recipient Name</div>
                            <h3 class="admin-customer-name"><?= htmlspecialchars($order['customer_name']) ?></h3>
                            <?php if (!empty($order['customer_email'])): ?>
                                <div class="text-muted small d-flex align-items-center gap-1">
                                    <i class="bi bi-envelope" aria-hidden="true"></i> <?= htmlspecialchars($order['customer_email']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.68rem; letter-spacing: 0.05em;">Shipping Destination</div>
                        <div class="admin-address-card">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-geo-alt-fill text-danger mt-1 flex-shrink-0" aria-hidden="true"></i>
                                <p class="admin-address-text"><?= nl2br(htmlspecialchars($order['address'])) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="text-muted small">
                            Payment Method: <span class="fw-semibold text-dark">Cash on Delivery</span>
                        </div>
                        <span class="badge bg-light text-secondary border px-2 py-1 small">
                            <i class="bi bi-cash-coin me-1" aria-hidden="true"></i> Pay Upon Arrival
                        </span>
                    </div>
                </div>

                <!-- Purchased Items Card -->
                <div class="admin-card p-0 overflow-hidden">
                    <div class="p-3 px-4 border-bottom bg-light d-flex align-items-center justify-content-between">
                        <h2 class="admin-card-heading mb-0">Purchased Order Items</h2>
                        <span class="badge bg-white text-dark border px-2 py-1 small fw-semibold">
                            <?= count($items) ?> <?= count($items) === 1 ? 'Item' : 'Items' ?>
                        </span>
                    </div>

                    <!-- Desktop & Tablet Table (>= 768px) -->
                    <div class="table-responsive admin-order-items-table">
                        <table class="table align-middle mb-0">
                            <thead class="bg-white border-bottom">
                                <tr>
                                    <th class="ps-4 py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Product Description</th>
                                    <th class="py-3 text-center text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Unit Price</th>
                                    <th class="py-3 text-center text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Quantity</th>
                                    <th class="pe-4 py-3 text-end text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $itemsSubtotal = 0.0;
                                foreach ($items as $item): 
                                    $lineTotal = (float)$item['price'] * (int)$item['quantity'];
                                    $itemsSubtotal += $lineTotal;
                                ?>
                                    <tr class="border-bottom">
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="../assets/images/<?= htmlspecialchars($item['image'] ?: 'default.jpg') ?>" class="admin-order-item-thumb" alt="<?= htmlspecialchars($item['name']) ?>">
                                                <div>
                                                    <div class="fw-semibold text-dark small"><?= htmlspecialchars($item['name']) ?></div>
                                                    <div class="text-muted" style="font-size: 0.7rem; font-family: monospace;">SKU: #<?= str_pad($item['product_id'], 4, '0', STR_PAD_LEFT) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 text-center text-secondary small" style="font-variant-numeric: tabular-nums;">
                                            $<?= number_format((float)$item['price'], 2) ?>
                                        </td>
                                        <td class="py-3 text-center small">
                                            <span class="admin-order-item-qty">&times; <?= (int)$item['quantity'] ?></span>
                                        </td>
                                        <td class="pe-4 py-3 text-end fw-bold text-dark small" style="font-variant-numeric: tabular-nums;">
                                            $<?= number_format($lineTotal, 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile-First Items Feed (< 768px) - Eliminates Squashed Table Columns -->
                    <div class="admin-order-items-feed" aria-label="Purchased Items Mobile List">
                        <?php foreach ($items as $item): 
                            $lineTotal = (float)$item['price'] * (int)$item['quantity'];
                        ?>
                            <div class="admin-order-item-card">
                                <img src="../assets/images/<?= htmlspecialchars($item['image'] ?: 'default.jpg') ?>" class="admin-order-item-thumb" alt="<?= htmlspecialchars($item['name']) ?>">
                                <div class="admin-order-item-detail">
                                    <div class="admin-order-item-title"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="admin-order-item-sku">SKU: #<?= str_pad($item['product_id'], 4, '0', STR_PAD_LEFT) ?></div>
                                    <div class="admin-order-item-calc">
                                        <span class="admin-order-item-qty">$<?= number_format((float)$item['price'], 2) ?> &times; <?= (int)$item['quantity'] ?></span>
                                        <span class="admin-order-item-total">$<?= number_format($lineTotal, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Financial Summary Receipt Footer -->
                    <div class="p-3 px-4 bg-light border-top">
                        <div class="row justify-content-end">
                            <div class="col-sm-7 col-md-5">
                                <div class="d-flex justify-content-between text-muted small mb-1">
                                    <span>Items Subtotal:</span>
                                    <span class="fw-semibold text-dark" style="font-variant-numeric: tabular-nums;">$<?= number_format($itemsSubtotal, 2) ?></span>
                                </div>
                                <div class="d-flex justify-content-between text-muted small mb-2">
                                    <span>Delivery &amp; Handling:</span>
                                    <span class="text-success fw-semibold">Free</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-baseline border-top pt-2">
                                    <span class="fw-bold text-dark">Grand Total:</span>
                                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;">$<?= number_format((float)$order['total_amount'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Col 2: Fulfillment Status Control & Quick Actions -->
            <div class="col-lg-5">
                <!-- Status Management Card -->
                <div class="admin-card mb-4">
                    <h2 class="admin-card-heading mb-3">Fulfillment Status Control</h2>
                    
                    <div class="mb-3">
                        <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.68rem; letter-spacing: 0.05em;">Current Operational State</div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="admin-status-pill <?= $pillClass ?>"><?= htmlspecialchars($s) ?></span>
                            <span class="text-muted small">&bull; Synchronized in real-time</span>
                        </div>
                    </div>

                    <!-- Operational Role Clarity -->
                    <div class="d-flex align-items-center gap-2 mb-3 p-2 px-3 rounded-2" style="background: #f8fafc; border: 1px dashed #cbd5e1; font-size: 0.78rem;">
                        <i class="bi bi-person-badge text-primary flex-shrink-0" aria-hidden="true"></i>
                        <span class="text-muted"><strong>Admin Dispatch Console:</strong> Update fulfillment as items are packed and courier reports completion.</span>
                    </div>

                    <div class="admin-status-control-box no-print">
                        <form method="POST">
                            <?= csrf_input() ?>
                            <label for="statusSelect" class="form-label text-muted small text-uppercase fw-bold mb-2" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                                Mutate Order Status
                            </label>
                            
                            <!-- Non-Colliding Status Group with Clean Non-Truncating Labels -->
                            <div class="admin-status-form-group">
                                <div class="admin-status-select-wrap">
                                    <select id="statusSelect" name="status" aria-label="Select Order Fulfillment Status">
                                        <option value="Pending" <?= $s == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Shipped" <?= $s == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="Delivered" <?= $s == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="Cancelled" <?= $s == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="admin-status-submit-btn">
                                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Update Status
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="border-top pt-2 mt-3 text-muted small" style="font-size: 0.78rem; line-height: 1.45;">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        Status mutations automatically trigger inventory reconciliation and dispatch customer notifications.
                    </div>
                </div>

                <!-- Courier Dispatch & Delivery Execution Card -->
                <div class="admin-card mb-4">
                    <h2 class="admin-card-heading mb-3">Courier &amp; Delivery Logistics</h2>
                    
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-3 p-3 bg-light rounded-3 border">
                            <div class="rounded-2 p-2 bg-white border text-success fs-5 flex-shrink-0">
                                <i class="bi bi-truck" aria-hidden="true"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Assigned Courier Fleet</div>
                                <div class="fw-bold text-dark small">FreshCart Express (In-House Fleet)</div>
                                <div class="text-muted small mt-1" style="font-size: 0.76rem;">
                                    Status: <?= $s === 'Delivered' ? '<span class="text-success fw-bold"><i class="bi bi-check2-circle me-1"></i>Delivery Complete &amp; Verified</span>' : ($s === 'Shipped' ? '<span class="text-primary fw-bold"><i class="bi bi-box-arrow-right me-1"></i>Out for Delivery</span>' : '<span class="text-warning fw-bold"><i class="bi bi-clock-history me-1"></i>Awaiting Dispatch</span>') ?>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded-2 border small" style="background: #fafafa;">
                            <span class="text-muted">Payment Collection (COD):</span>
                            <span class="fw-bold <?= $s === 'Delivered' ? 'text-success' : 'text-dark' ?>">
                                <?= $s === 'Delivered' ? '<i class="bi bi-check-circle-fill text-success me-1"></i> Paid in Full ($' . number_format((float)$order['total_amount'], 2) . ')' : '<i class="bi bi-cash me-1"></i> Collect $' . number_format((float)$order['total_amount'], 2) . ' Cash' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Logistics Meta & Quick Actions Card -->
                <div class="admin-card no-print">
                    <h2 class="admin-card-heading mb-3">Order Operational Tools</h2>
                    
                    <div class="d-flex flex-column gap-2">
                        <button type="button" onclick="window.print()" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 py-2">
                            <i class="bi bi-printer" aria-hidden="true"></i>
                            <span>Print Customer Invoice</span>
                        </button>
                        <a href="index.php?view=orders" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 py-2">
                            <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                            <span>Browse All Orders</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Native Mobile Bottom Tab Rail (Fixed 5-Tab) -->
    <nav class="admin-mobile-bottom-nav d-lg-none no-print" aria-label="Mobile Navigation">
        <ul class="admin-mobile-nav-grid">
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=dashboard" class="admin-mobile-nav-btn">
                    <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=orders" class="admin-mobile-nav-btn active">
                    <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=products" class="admin-mobile-nav-btn">
                    <i class="bi bi-box-seam" aria-hidden="true"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=users" class="admin-mobile-nav-btn">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=reviews" class="admin-mobile-nav-btn">
                    <i class="bi bi-star" aria-hidden="true"></i>
                    <span>Reviews</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Floating Notifications Container (Web: Bottom-Left, Mobile: UX-Friendly Docked) -->
    <div class="fresh-toast-container" id="freshToastContainer" aria-live="polite" aria-atomic="true">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="fresh-toast fresh-toast-success is-visible no-print" role="alert" id="orderStatusToast">
                <div class="fresh-toast-icon">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                </div>
                <div class="fresh-toast-body">
                    <div class="fresh-toast-title">Fulfillment Updated</div>
                    <div class="fresh-toast-message">Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?> is now marked as <strong><?= htmlspecialchars($s) ?></strong>.</div>
                </div>
                <button type="button" class="fresh-toast-close" onclick="dismissOrderStatusToast()" aria-label="Dismiss notification">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
                <div class="fresh-toast-progress">
                    <div class="fresh-toast-progress-bar" style="transform: scaleX(0); transition: transform 4500ms linear;"></div>
                </div>
            </div>
            <script>
                function dismissOrderStatusToast() {
                    const el = document.getElementById('orderStatusToast');
                    if (el) {
                        el.classList.remove('is-visible');
                        el.classList.add('is-hiding');
                        setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 280);
                    }
                    const url = new URL(window.location.href);
                    url.searchParams.delete('msg');
                    window.history.replaceState({}, document.title, url.toString());
                }
                setTimeout(dismissOrderStatusToast, 4500);
            </script>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/toast.js?v=<?= time() ?>"></script>
</body>
</html>
