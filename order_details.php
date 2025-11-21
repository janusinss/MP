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

// *** NEW: Handle Status Update ***
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmtUpdate = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$new_status, $order_id]);
    
    // Refresh the page to show the new status
    header("Location: order_details.php?order_id=" . $order_id);
    exit;
}

try {
    // 1. Fetch Order Information
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found!");
    }

    // 2. Fetch Order Items
    $sqlItems = "SELECT order_items.*, products.name, products.price 
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
    <title>Order #<?= $order_id ?> Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5">

    <a href="admin.php" class="btn btn-secondary mb-3">&larr; Back to Dashboard</a>

    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Order #<?= $order['id'] ?> Details</h4>
            
            <?php 
                $badgeClass = 'bg-secondary';
                if ($order['status'] == 'Pending') $badgeClass = 'bg-warning text-dark';
                if ($order['status'] == 'Shipped') $badgeClass = 'bg-info text-dark';
                if ($order['status'] == 'Delivered') $badgeClass = 'bg-success';
                if ($order['status'] == 'Cancelled') $badgeClass = 'bg-danger';
            ?>
            <span class="badge <?= $badgeClass ?> fs-6"><?= $order['status'] ?></span>
        </div>
        <div class="card-body">
            
            <div class="row mb-4 align-items-center">
                <div class="col-md-4">
                    <h5 class="text-muted">Customer:</h5>
                    <p class="fw-bold mb-1"><?= htmlspecialchars($order['customer_name']) ?></p>
                    <p class="text-muted small"><?= htmlspecialchars($order['address']) ?></p>
                </div>
                
                <div class="col-md-8">
                    <div class="card bg-light border-0">
                        <div class="card-body p-3">
                            <form method="POST" class="row g-2 align-items-center">
                                <div class="col-auto">
                                    <label class="fw-bold">Update Status:</label>
                                </div>
                                <div class="col-auto">
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="Pending" <?= $order['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Shipped" <?= $order['status'] == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="Delivered" <?= $order['status'] == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="Cancelled" <?= $order['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <hr>

            <h5 class="mb-3">Items Purchased:</h5>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <?php $subtotal = $item['price'] * $item['quantity']; ?>
                    <tr>
                        <td style="width: 80px;">
                            <div style="width: 50px; height: 50px; background-color: #eee; display: flex; align-items: center; justify-content: center; border-radius: 5px;">
                                📷
                            </div>
                        </td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>$<?= number_format($item['price'], 2) ?></td>
                        <td>x <?= $item['quantity'] ?></td>
                        <td class="fw-bold">$<?= number_format($subtotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Grand Total:</strong></td>
                        <td class="bg-success text-white fw-bold">$<?= number_format($order['total_amount'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</body>
</html>