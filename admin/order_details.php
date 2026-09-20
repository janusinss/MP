<?php
// admin/order_details.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['order_id'])) {
    die("Order ID missing.");
}

$order_id = (int)$_GET['order_id'];

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
    
    header("Location: index.php?view=orders&msg=updated");
    exit;
}

// Fetch Data
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
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
    die("Error: " . htmlspecialchars($e->getMessage()));
}

$s = $order['status'];
$pillClass = match($s) {
    'Delivered' => 'status-delivered',
    'Shipped' => 'status-shipped',
    'Pending' => 'status-pending',
    default => 'status-cancelled'
};
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
</head>
<body style="background-color: var(--color-canvas, #F7F6F2); color: #0f172a; min-height: 100vh;">

    <div class="container py-4 order-details-wrapper" style="max-width: 960px;">

        <!-- Navigation & Actions Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <a href="index.php?view=orders" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Back to Orders
            </a>
            <div class="d-flex align-items-center gap-2">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                    <i class="bi bi-printer"></i> Print Invoice
                </button>
            </div>
        </div>

        <!-- View Header -->
        <div class="admin-view-header mb-4">
            <div>
                <span class="admin-kicker">Fulfillment &amp; Order Logistics</span>
                <h1 class="admin-view-title mb-1">Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></h1>
                <p class="admin-view-subtitle mb-0">
                    Placed on <?= date('F d, Y \a\t h:i A', strtotime($order['created_at'])) ?>
                </p>
            </div>
            <div class="text-end">
                <span class="admin-status-pill <?= $pillClass ?> px-3 py-1" style="font-size: 0.85rem;">
                    <?= $s ?>
                </span>
            </div>
        </div>

        <!-- Two-Column Operational Details -->
        <div class="row g-4 mb-4">
            <!-- Col 1: Customer & Delivery Address -->
            <div class="col-md-6">
                <div class="admin-card h-100">
                    <h2 class="admin-card-heading mb-3">Customer &amp; Delivery Destination</h2>
                    <div class="mb-3">
                        <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem;">Recipient Name</div>
                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($order['customer_name']) ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem;">Shipping Address</div>
                        <div class="text-dark small d-flex align-items-start gap-2 mt-1">
                            <i class="bi bi-geo-alt text-muted mt-1"></i>
                            <div><?= nl2br(htmlspecialchars($order['address'])) ?></div>
                        </div>
                    </div>
                    <div class="border-top pt-2 mt-auto">
                        <div class="text-muted small">
                            Payment Method: <span class="fw-semibold text-dark">Cash on Delivery</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Col 2: Status Management -->
            <div class="col-md-6">
                <div class="admin-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h2 class="admin-card-heading mb-3">Fulfillment Status Control</h2>
                        <div class="mb-3">
                            <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Current Operational State</div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="admin-status-pill <?= $pillClass ?>"><?= $s ?></span>
                                <span class="text-muted small">&bull; Updated in real-time</span>
                            </div>
                        </div>

                        <form method="POST" class="no-print">
                            <?= csrf_input() ?>
                            <label for="statusSelect" class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.7rem;">Change Status</label>
                            <div class="d-flex gap-2">
                                <select id="statusSelect" name="status" class="form-select form-select-sm">
                                    <option value="Pending" <?= $s == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Shipped" <?= $s == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                    <option value="Delivered" <?= $s == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                    <option value="Cancelled" <?= $s == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-sm btn-dark px-3 fw-semibold text-nowrap">
                                    Update Status
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="border-top pt-2 mt-3 text-muted small">
                        Status mutations automatically trigger inventory adjustments and notify order tracking.
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchased Items Breakdown -->
        <div class="admin-card p-0 overflow-hidden mb-4">
            <div class="p-3 px-4 border-bottom bg-light">
                <h2 class="admin-card-heading mb-0">Purchased Order Items</h2>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-white border-bottom">
                        <tr>
                            <th class="ps-4 py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Item Description</th>
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
                                        <img src="../assets/images/<?= $item['image'] ?: 'default.jpg' ?>" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0;" alt="item">
                                        <div>
                                            <div class="fw-semibold text-dark small"><?= htmlspecialchars($item['name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem; font-family: monospace;">SKU: #<?= str_pad($item['product_id'], 4, '0', STR_PAD_LEFT) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-center text-secondary small" style="font-variant-numeric: tabular-nums;">
                                    $<?= number_format((float)$item['price'], 2) ?>
                                </td>
                                <td class="py-3 text-center fw-semibold text-dark small">
                                    &times; <?= (int)$item['quantity'] ?>
                                </td>
                                <td class="pe-4 py-3 text-end fw-bold text-dark small" style="font-variant-numeric: tabular-nums;">
                                    $<?= number_format($lineTotal, 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Financial Reconciliation Footer -->
            <div class="p-3 px-4 bg-light border-top">
                <div class="row justify-content-end">
                    <div class="col-sm-6 col-md-4">
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

</body>
</html>
