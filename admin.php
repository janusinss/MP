<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 1. Fetch All Orders (Existing Logic)
try {
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
    $error = $e->getMessage();
}

// 2. ADVANCED: Fetch Daily Sales Data for the Chart
// We use SQL 'GROUP BY' to sum up the totals for each day
try {
    $sqlChart = "SELECT DATE(created_at) as order_date, SUM(total_amount) as daily_total 
                 FROM orders 
                 WHERE status != 'Cancelled' 
                 GROUP BY DATE(created_at) 
                 ORDER BY order_date ASC 
                 LIMIT 7"; // Last 7 days
    $stmtChart = $pdo->query($sqlChart);
    $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for JavaScript
    $dates = [];
    $totals = [];
    foreach ($chartData as $data) {
        $dates[] = date('M d', strtotime($data['order_date'])); // Format: Nov 21
        $totals[] = $data['daily_total'];
    }

} catch (Exception $e) {
    $dates = [];
    $totals = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="container mt-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>👮‍♂️ Admin Dashboard</h2>
        <div>
            <a href="export_orders.php" class="btn btn-success me-2">⬇ Export CSV</a>
            <a href="admin_products.php" class="btn btn-primary me-2">📦 Products</a>
            <a href="admin_reviews.php" class="btn btn-warning text-dark me-2">⭐ Reviews</a>
            
            <a href="index.php" class="btn btn-outline-secondary me-2">Go to Shop</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">Error: <?= $error ?></div>
    <?php endif; ?>
    <?php
    // Fetch items with less than 5 stock
    $stmtLow = $pdo->query("SELECT * FROM products WHERE stock_qty <= 5");
    $lowStockItems = $stmtLow->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (count($lowStockItems) > 0): ?>
        <div class="alert alert-warning shadow-sm border-warning">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Low Stock Alert</h5>
            <p class="mb-0">The following items are running low and need restocking:</p>
            <ul class="mb-2 mt-2">
                <?php foreach ($lowStockItems as $item): ?>
                    <li>
                        <strong><?= htmlspecialchars($item['name']) ?></strong> 
                        (Only <?= $item['stock_qty'] ?> left)
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="admin_products.php" class="btn btn-sm btn-warning text-dark">Manage Inventory</a>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">📈 Sales Overview (Last 7 Active Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Recent Orders</h5>
        </div>
        <div class="card-body">
            <?php if (count($orders) > 0): ?>
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Date</th> <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($order['customer_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($order['address']) ?></small>
                                </td>
                                <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                <td class="fw-bold text-success">$<?= number_format($order['total_amount'], 2) ?></td>
                                
                                <td>
                                    <?php 
                                        $status = $order['status'];
                                        $badgeClass = 'bg-secondary';
                                        if ($status == 'Pending') $badgeClass = 'bg-warning text-dark';
                                        if ($status == 'Shipped') $badgeClass = 'bg-info text-dark';
                                        if ($status == 'Delivered') $badgeClass = 'bg-success';
                                        if ($status == 'Cancelled') $badgeClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= $status ?></span>
                                </td>
                                
                                <td>
                                    <a href="order_details.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">
                                        View Items
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info text-center m-0">No orders found yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>, // PHP Arrays to JS
                datasets: [{
                    label: 'Daily Revenue ($)',
                    data: <?= json_encode($totals) ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4 // Makes the line curved
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>

</body>
</html>