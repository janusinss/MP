<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['order_id'])) {
    die("Order ID missing.");
}

$order_id = $_GET['order_id'];

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmtUpdate = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$new_status, $order_id]);
    
    // CHANGED: Redirect to main admin router with the "Orders" view active and a success message
    header("Location: admin.php?view=orders&msg=updated");
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
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Order #<?= $order_id ?> | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body style="background-color: var(--bg-color);">

    <div class="container mt-5 order-details-wrapper">

        <div class="page-nav-header animate-fade-in">
            <a href="admin.php" class="btn-back-glass">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="invoice-card animate-fade-in">
            
            <div class="invoice-header-modern">
                <div>
                    <h1 class="invoice-title">Order #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></h1>
                    <div class="invoice-meta">
                        <span><i class="bi bi-calendar3"></i> <?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                        <span><i class="bi bi-clock"></i> <?= date('h:i A', strtotime($order['created_at'])) ?></span>
                    </div>
                </div>
                
                <?php 
                    $s = $order['status'];
                    $badgeStyle = 'background: #eee; color: #555;'; // Default
                    if ($s == 'Pending') $badgeStyle = 'background: #fff3cd; color: #856404; border: 1px solid #ffeeba;';
                    if ($s == 'Shipped') $badgeStyle = 'background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;';
                    if ($s == 'Delivered') $badgeStyle = 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;';
                    if ($s == 'Cancelled') $badgeStyle = 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
                ?>
                <div class="invoice-status-badge" style="<?= $badgeStyle ?>">
                    <?= $s ?>
                </div>
            </div>

            <div class="invoice-info-grid">
                
                <div>
                    <span class="info-box-title">Billed To</span>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($order['customer_name']) ?></h5>
                    <address class="customer-address mb-0">
                        <?= nl2br(htmlspecialchars($order['address'])) ?>
                    </address>
                </div>

                <div>
                    <span class="info-box-title">Manage Order Status</span>
                    <div class="status-update-container">
                        <form method="POST" class="d-flex gap-2">
                            <select name="status" class="form-select status-select-custom">
                                <option value="Pending" <?= $s == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Shipped" <?= $s == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="Delivered" <?= $s == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="Cancelled" <?= $s == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-primary btn-sm px-3 rounded-pill">Update</button>
                        </form>
                        <small class="text-muted mt-2 d-block fst-italic">
                            * Updating status sends email notification (simulated).
                        </small>
                    </div>
                </div>

            </div>

            <div class="invoice-table-container">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th width="50%">Item Description</th>
                            <th width="15%" class="text-center">Unit Price</th>
                            <th width="15%" class="text-center">Quantity</th>
                            <th width="20%" class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="item-preview">
                                    <img src="assets/images/<?= $item['image'] ?: 'default.jpg' ?>" class="item-thumb" alt="img">
                                    <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                                </div>
                            </td>
                            <td class="text-center text-muted">$<?= number_format($item['price'], 2) ?></td>
                            <td class="text-center fw-bold">x <?= $item['quantity'] ?></td>
                            <td class="text-end fw-bold">$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="invoice-footer">
                <div class="total-group">
                    <span class="total-label">Grand Total</span>
                    <span class="total-value-lg">$<?= number_format($order['total_amount'], 2) ?></span>
                </div>
            </div>

        </div>

    </div>

</body>
</html>