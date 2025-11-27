<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 1. Fetch Orders
try {
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
    $error = $e->getMessage();
}

// 2. Fetch Daily Sales Data for Chart
try {
    $sqlChart = "SELECT DATE(created_at) as order_date, SUM(total_amount) as daily_total 
                 FROM orders 
                 WHERE status != 'Cancelled' 
                 GROUP BY DATE(created_at) 
                 ORDER BY order_date ASC 
                 LIMIT 7";
    $stmtChart = $pdo->query($sqlChart);
    $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
    
    $dates = [];
    $totals = [];
    foreach ($chartData as $data) {
        $dates[] = date('M d', strtotime($data['order_date']));
        $totals[] = $data['daily_total'];
    }
} catch (Exception $e) {
    $dates = []; $totals = [];
}

// 3. Low Stock Check
$stmtLow = $pdo->query("SELECT * FROM products WHERE stock_qty <= 5");
$lowStockItems = $stmtLow->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard | FreshCart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="container-fluid py-4" style="background-color: var(--bg-color);">
    
    <div class="row g-4">
        <div class="col-lg-2 d-none d-lg-block">
            <div class="dashboard-card admin-sidebar d-flex flex-column">
                <div class="text-center mb-5 mt-2">
                    <h4 class="m-0" style="font-family: var(--font-serif); letter-spacing: -0.05em;">FreshCart<span style="color: var(--accent-color)">.</span></h4>
                    <small class="text-muted">Admin Panel</small>
                </div>
                
                <div class="nav flex-column nav-pills flex-grow-1">
                    <a href="admin.php" class="nav-link active mb-3"><i class="bi bi-grid-fill me-3"></i> Dashboard</a>
                    <a href="admin_products.php" class="nav-link mb-2"><i class="bi bi-box-seam me-3"></i> Inventory</a>
                    <a href="admin_users.php" class="nav-link mb-2"><i class="bi bi-people me-3"></i> Customers</a>
                    <a href="admin_reviews.php" class="nav-link mb-2"><i class="bi bi-star me-3"></i> Reviews</a>
                </div>

                <div class="mt-auto pt-4 border-top">
                    <a href="index.php" class="nav-link mb-2"><i class="bi bi-shop me-3"></i> View Shop</a>
                    <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-left me-3"></i> Logout</a>
                </div>
            </div>
        </div>

        <div class="col-lg-10">
            
            <div class="d-lg-none d-flex justify-content-between align-items-center mb-4">
                <h4 class="m-0">FreshCart Admin</h4>
                <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="m-0">Dashboard Overview</h2>
                    <p class="text-muted">Welcome back, Admin.</p>
                </div>
                <a href="export_orders.php" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-2"></i> Export Report</a>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-title">Total Orders</div>
                                <div class="stat-value"><?= count($orders) ?></div>
                            </div>
                            <div class="bg-light p-2 rounded-circle text-primary">
                                <i class="bi bi-receipt fs-4"></i>
                            </div>
                        </div>
                        <small class="text-muted mt-3 d-block">Lifetime volume</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-title">Revenue (7d)</div>
                                <div class="stat-value">$<?= number_format(array_sum($totals), 0) ?></div>
                            </div>
                            <div class="bg-light p-2 rounded-circle text-success">
                                <i class="bi bi-currency-dollar fs-4"></i>
                            </div>
                        </div>
                        <small class="text-success mt-3 d-block"><i class="bi bi-graph-up"></i> Active period</small>
                    </div>
                </div>

                <div class="col-md-6">
                    <?php if (count($lowStockItems) > 0): ?>
                        <div class="dashboard-card border-warning" style="background: rgba(255, 243, 205, 0.6);">
                            <div class="d-flex align-items-center mb-3 text-warning">
                                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                                <h6 class="m-0 fw-bold">Low Stock Alert</h6>
                            </div>
                            <div style="max-height: 80px; overflow-y: auto;">
                                <ul class="mb-0 small ps-3">
                                    <?php foreach ($lowStockItems as $item): ?>
                                        <li class="mb-1">
                                            <strong><?= htmlspecialchars($item['name']) ?></strong> 
                                            <span class="badge bg-warning text-dark rounded-pill ms-2"><?= $item['stock_qty'] ?> left</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a href="admin_products.php" class="btn btn-sm btn-outline-dark mt-2" style="font-size: 0.7rem;">Manage Inventory</a>
                        </div>
                    <?php else: ?>
                        <div class="dashboard-card d-flex align-items-center justify-content-center text-muted">
                            <div class="text-center">
                                <i class="bi bi-check-circle fs-3 text-success mb-2"></i>
                                <p class="m-0">Inventory levels are healthy.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mb-5">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h5 class="mb-4" style="font-family: var(--font-serif);">Sales Analytics</h5>
                        <canvas id="salesChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <h5 class="mb-3" style="font-family: var(--font-serif);">Recent Orders</h5>
            <div class="table-responsive table-glass shadow-sm">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach (array_slice($orders, 0, 10) as $order): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border">#<?= $order['id'] ?></span></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($order['customer_name']) ?></div>
                                    <small class="text-muted text-truncate d-block" style="max-width: 150px;"><?= htmlspecialchars($order['address']) ?></small>
                                </td>
                                <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                <td class="fw-bold text-success">$<?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <?php 
                                        $s = $order['status'];
                                        $cls = 'bg-secondary';
                                        if ($s == 'Pending') $cls = 'bg-warning text-dark';
                                        if ($s == 'Shipped') $cls = 'bg-info text-dark';
                                        if ($s == 'Delivered') $cls = 'bg-success';
                                        if ($s == 'Cancelled') $cls = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $cls ?>"><?= $s ?></span>
                                </td>
                                <td>
                                    <a href="order_details.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>,
                datasets: [{
                    label: 'Revenue ($)',
                    data: <?= json_encode($totals) ?>,
                    borderColor: '#5D866C', // Using your accent color
                    backgroundColor: 'rgba(93, 134, 108, 0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#5D866C',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>