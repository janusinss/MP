<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$view = $_GET['view'] ?? 'dashboard';

// --- VIEW 1: DASHBOARD ---
if ($view == 'dashboard') {
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sqlChart = "SELECT DATE(created_at) as order_date, SUM(total_amount) as daily_total FROM orders WHERE status != 'Cancelled' GROUP BY DATE(created_at) ORDER BY order_date ASC LIMIT 7";
    $stmtChart = $pdo->query($sqlChart);
    $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
    $dates = []; $totals = [];
    foreach ($chartData as $data) { $dates[] = date('M d', strtotime($data['order_date'])); $totals[] = $data['daily_total']; }

    $stmtLow = $pdo->query("SELECT * FROM products WHERE stock_qty <= 5");
    $lowStockItems = $stmtLow->fetchAll(PDO::FETCH_ASSOC);
    
    $totalRevenue = 0;
    $pendingOrders = 0;
    foreach($orders as $o) { 
        if($o['status'] != 'Cancelled') $totalRevenue += $o['total_amount'];
        if($o['status'] == 'Pending') $pendingOrders++;
    }
    ?>
    
    <div class="dash-welcome-banner">
        <div>
            <h2 class="dash-title">Dashboard Overview</h2>
            <p class="dash-subtitle mb-0">Here's what's happening with your store today.</p>
        </div>
        <a href="export_orders.php" class="btn-glass-white text-decoration-none">
            <i class="bi bi-download me-2"></i> Export Report
        </a>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="dash-stat-card interactive" onclick="loadView('orders')">
                <i class="bi bi-receipt dash-watermark-icon"></i>
                <span class="dash-stat-label">Total Orders</span>
                <div class="dash-stat-value"><?= count($orders) ?></div>
                <div class="mt-2 text-success small fw-bold"><i class="bi bi-arrow-right-circle"></i> View All</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat-card">
                <i class="bi bi-currency-dollar dash-watermark-icon"></i>
                <span class="dash-stat-label">Total Revenue</span>
                <div class="dash-stat-value">$<?= number_format($totalRevenue, 2) ?></div>
                <div class="mt-2 text-success small fw-bold"><i class="bi bi-graph-up"></i> Trending Up</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat-card interactive" style="border-left: 4px solid #ffc107;" onclick="loadView('orders&status=Pending')">
                <i class="bi bi-hourglass-split dash-watermark-icon"></i>
                <span class="dash-stat-label text-warning">Pending Actions</span>
                <div class="dash-stat-value"><?= $pendingOrders ?></div>
                <div class="mt-2 text-warning small fw-bold"><i class="bi bi-exclamation-circle"></i> Needs Attention</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-8">
            <div class="dashboard-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="font-serif m-0">Sales Analytics</h5>
                    <select class="form-select form-select-sm w-auto border-0 bg-light"><option>Last 7 Days</option></select>
                </div>
                <canvas id="salesChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert-card shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <div class="alert-header-icon me-3"><i class="bi bi-bell-fill"></i></div>
                        <div><h5 class="m-0 fw-bold text-dark font-serif">Low Stock</h5><small class="text-muted">Requires immediate attention</small></div>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill border border-warning px-3"><?= count($lowStockItems) ?> Items</span>
                </div>
                
                <?php if (count($lowStockItems) > 0): ?>
                    <div style="max-height: 280px; overflow-y: auto; padding: 2px;">
                        <?php foreach ($lowStockItems as $item): 
                            $qty = $item['stock_qty'];
                            $percent = ($qty == 0) ? 0 : min(100, ($qty / 10) * 100); 
                            $img = $item['image'] ? $item['image'] : 'default.jpg';
                        ?>
                            <a href="edit_product.php?id=<?= $item['id'] ?>" class="text-decoration-none text-reset">
                                <div class="stock-item-modern">
                                    <img src="assets/images/<?= $img ?>" class="stock-img" alt="img">
                                    <div class="stock-info">
                                        <span class="stock-name"><?= htmlspecialchars($item['name']) ?></span>
                                        <div class="stock-progress-bg"><div class="stock-progress-bar" style="width: <?= $percent ?>%; background: <?= $qty == 0 ? '#adb5bd' : '#dc3545' ?>;"></div></div>
                                    </div>
                                    <?php if ($qty == 0): ?>
                                        <div class="stock-badge-out"><i class="bi bi-x-lg" style="font-size: 0.9rem;"></i><span>No Stock</span></div>
                                    <?php else: ?>
                                        <div class="stock-badge-critical"><?= $qty ?><span>Left</span></div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <a href="#" onclick="loadView('products')" class="btn-restock-pulse mt-4 d-block text-decoration-none text-center">Manage Inventory <i class="bi bi-arrow-right ms-2"></i></a>
                <?php else: ?>
                    <div class="text-center py-5"><h6 class="fw-bold">All Good!</h6><p class="text-muted small">Inventory levels are healthy.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        if(window.salesChartInstance) window.salesChartInstance.destroy();
        var ctx = document.getElementById('salesChart').getContext('2d');
        window.salesChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($totals) ?>,
                    borderColor: '#5D866C',
                    backgroundColor: 'rgba(93, 134, 108, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    </script>
    <?php
}

// --- VIEW 2: PRODUCTS ---
elseif ($view == 'products') {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="inventory-header-card">
        <div>
            <h2 class="admin-header-title">Product Inventory</h2>
            <p class="text-muted m-0">Manage your catalog, stock levels, and pricing.</p>
        </div>
        <a href="add_product.php" class="btn-add-product text-decoration-none"><i class="bi bi-plus-circle-fill fs-5"></i> Add New Product</a>
    </div>
    <div class="inventory-table-container">
        <div class="table-responsive">
            <table class="table table-inventory mb-0">
                <thead><tr><th style="padding-left: 2rem;">Item</th><th>Price</th><th>Stock Status</th><th class="text-end" style="padding-right: 2rem;">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="padding-left: 2rem;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="inv-img-box"><img src="assets/images/<?= $p['image'] ?: 'default.jpg' ?>" alt="img"></div>
                                <div><span class="inv-product-name"><?= htmlspecialchars($p['name']) ?></span><span class="inv-product-cat"><?= htmlspecialchars($p['category']) ?></span></div>
                            </div>
                        </td>
                        <td><span class="inv-price">$<?= number_format($p['price'], 2) ?></span></td>
                        <td>
                            <?php 
                                if($p['stock_qty'] == 0) echo '<div class="stock-status stock-out"><span class="stock-dot"></span> No Stock</div>';
                                elseif($p['stock_qty'] < 5) echo '<div class="stock-status stock-low"><span class="stock-dot"></span> Low Stock ('.$p['stock_qty'].')</div>';
                                else echo '<div class="stock-status stock-high"><span class="stock-dot"></span> In Stock ('.$p['stock_qty'].')</div>';
                            ?>
                        </td>
                        <td class="text-end" style="padding-right: 2rem;">
                            <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn-icon-action" title="Edit"><i class="bi bi-pencil-fill" style="font-size: 0.9rem;"></i></a>
                            <a href="delete_product.php?id=<?= $p['id'] ?>" class="btn-icon-action btn-icon-delete" title="Delete" onclick="return confirm('Delete product?');"><i class="bi bi-trash-fill" style="font-size: 0.9rem;"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// --- VIEW 3: CUSTOMERS ---
elseif ($view == 'users') {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div><h2 class="admin-header-title">Registered Customers</h2><p class="text-muted m-0">Manage your community.</p></div>
        <div class="bg-white px-4 py-2 rounded-pill shadow-sm border d-flex align-items-center gap-2"><i class="bi bi-people-fill text-success"></i><span class="fw-bold"><?= count($users) ?></span> <span class="text-muted small text-uppercase">Members</span></div>
    </div>
    <div class="row g-4">
        <?php foreach ($users as $u): ?>
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="customer-card-grid">
                    <span class="cust-id-badge">ID: <?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?></span>
                    <a href="delete_user.php?id=<?= $u['id'] ?>" class="btn-delete-user" onclick="return confirm('Delete this user?');" title="Delete User"><i class="bi bi-trash-fill"></i></a>
                    <div class="cust-avatar-lg"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
                    <h5 class="cust-name text-truncate"><?= htmlspecialchars($u['full_name']) ?></h5>
                    <p class="cust-email text-truncate"><?= htmlspecialchars($u['email']) ?></p>
                    <div class="cust-divider"></div>
                    <span class="cust-joined"><i class="bi bi-calendar2 me-1"></i> Joined <?= date('M d, Y', strtotime($u['created_at'])) ?></span>
                    <a href="#" onclick="loadView('customer_details&id=<?= $u['id'] ?>')" class="btn-view-orders">View Orders</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// --- VIEW 4: REVIEWS ---
elseif ($view == 'reviews') {
    $stmt = $pdo->query("SELECT r.*, u.full_name, p.name as product_name, p.image as product_image 
                         FROM reviews r 
                         JOIN users u ON r.user_id = u.id 
                         JOIN products p ON r.product_id = p.id 
                         ORDER BY r.created_at DESC");
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Stats
    $totalReviews = count($reviews);
    $avgRating = 0;
    if ($totalReviews > 0) {
        $sum = array_sum(array_column($reviews, 'rating'));
        $avgRating = number_format($sum / $totalReviews, 1);
    }
    ?>
    
    <div class="d-flex justify-content-between align-items-end mb-5">
        <div>
            <h2 class="admin-header-title">Review Gallery</h2>
            <p class="text-muted m-0">Insights directly from your customers.</p>
        </div>
    </div>

    <div class="review-stats-bar">
        <div class="r-stat-box">
            <div class="r-icon"><i class="bi bi-chat-quote-fill"></i></div>
            <div class="r-info">
                <h3><?= $totalReviews ?></h3>
                <span>Total Reviews</span>
            </div>
        </div>
        <div class="r-stat-box">
            <div class="r-icon" style="background: #e0f2fe; color: #0369a1;"><i class="bi bi-star-half"></i></div>
            <div class="r-info">
                <h3><?= $avgRating ?></h3>
                <span>Average Rating</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php if ($totalReviews > 0): ?>
            <?php foreach ($reviews as $r): ?>
                <div class="col-xl-4 col-md-6">
                    <div class="review-card-modern">
                        <div class="rc-quote-icon">”</div>
                        
                        <div class="rc-header">
                            <div class="rc-user">
                                <div class="rc-avatar"><?= strtoupper(substr($r['full_name'], 0, 1)) ?></div>
                                <div class="rc-meta">
                                    <h6><?= htmlspecialchars($r['full_name']) ?></h6>
                                    <span><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                                </div>
                            </div>
                            <div class="rc-product-badge">
                                <img src="assets/images/<?= $r['product_image'] ?: 'default.jpg' ?>">
                                <span class="text-truncate"><?= htmlspecialchars($r['product_name']) ?></span>
                            </div>
                        </div>

                        <div class="rc-rating">
                            <?php for($i=0; $i<$r['rating']; $i++) echo '<i class="bi bi-star-fill"></i>'; ?>
                            <?php for($i=$r['rating']; $i<5; $i++) echo '<i class="bi bi-star text-muted opacity-25"></i>'; ?>
                        </div>

                        <p class="rc-comment">
                            <?= htmlspecialchars($r['comment']) ?>
                        </p>

                        <div class="rc-actions">
                            <a href="delete_review.php?id=<?= $r['id'] ?>" class="btn-delete-review text-decoration-none" onclick="return confirm('Delete this review?');">
                                <i class="bi bi-trash me-1"></i> Remove
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5 bg-white rounded-4 border border-light">
                    <div class="mb-3">
                        <span style="font-size: 4rem;">💬</span>
                    </div>
                    <h4 class="text-muted font-serif">Quiet around here...</h4>
                    <p class="text-muted">No customer reviews have been posted yet.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// --- VIEW 5: CUSTOMER DETAILS ---
elseif ($view == 'customer_details') {
    $userId = $_GET['id'];
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $stmtOrders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmtOrders->execute([$userId]);
    $userOrders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);
    
    $lifetimeSpend = array_sum(array_column($userOrders, 'total_amount'));
    $totalOrders = count($userOrders);
    $avgOrder = $totalOrders > 0 ? $lifetimeSpend / $totalOrders : 0;
    ?>
    <div class="mb-4">
        <a href="#" onclick="loadView('users')" class="text-decoration-none text-muted small fw-bold text-uppercase"><i class="bi bi-arrow-left me-1"></i> Back to Customers</a>
    </div>
    <div class="admin-customer-header">
        <div class="d-flex align-items-center gap-4">
            <div class="customer-avatar-large"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
            <div>
                <h2 class="font-serif m-0"><?= htmlspecialchars($user['full_name']) ?></h2>
                <p class="text-muted m-0"><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>
        <div class="customer-stats-row">
            <div><span class="c-stat-label">Total Spend</span><div class="c-stat-value text-success">$<?= number_format($lifetimeSpend, 2) ?></div></div>
            <div><span class="c-stat-label">Total Orders</span><div class="c-stat-value"><?= $totalOrders ?></div></div>
            <div><span class="c-stat-label">Avg. Order</span><div class="c-stat-value text-muted">$<?= number_format($avgOrder, 2) ?></div></div>
        </div>
    </div>
    <h4 class="font-serif mb-4">Order History</h4>
    <div class="timeline-wrapper">
        <?php foreach ($userOrders as $order): ?>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-card">
                    <div class="timeline-header">
                        <div><span class="badge bg-light text-dark border me-2">#<?= $order['id'] ?></span><span class="t-date"><?= date('F d, Y', strtotime($order['created_at'])) ?></span></div>
                        <div class="t-total">$<?= number_format($order['total_amount'], 2) ?></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-secondary"><?= $order['status'] ?></span>
                        <a href="order_details.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3">View Items</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// --- VIEW 6: ORDERS MANAGEMENT ---
elseif ($view == 'orders') {
    $statusFilter = $_GET['status'] ?? 'All';
    
    $sql = "SELECT * FROM orders";
    $params = [];
    if ($statusFilter != 'All') {
        $sql .= " WHERE status = ?";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="d-flex justify-content-between align-items-end mb-5">
        <div>
            <h2 class="admin-header-title">Order Management</h2>
            <p class="text-muted m-0">Track, fulfill, and manage customer orders.</p>
        </div>
        <div class="filter-glass-container">
            <?php 
            $statuses = ['All', 'Pending', 'Shipped', 'Delivered', 'Cancelled'];
            foreach($statuses as $s): 
                $activeClass = ($statusFilter == $s) ? 'active' : '';
                $param = ($s == 'All') ? 'orders' : "orders&status=$s";
            ?>
                <button onclick="loadView('<?= $param ?>')" class="filter-tab-btn <?= $activeClass ?>"><?= $s ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dashboard-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="bg-light border-bottom">
                    <tr>
                        <th class="ps-4 py-3 text-muted small text-uppercase">Order ID</th>
                        <th class="py-3 text-muted small text-uppercase">Customer</th>
                        <th class="py-3 text-muted small text-uppercase">Date Placed</th>
                        <th class="py-3 text-muted small text-uppercase">Total</th>
                        <th class="py-3 text-muted small text-uppercase">Status</th>
                        <th class="pe-4 py-3 text-end text-muted small text-uppercase">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($allOrders) > 0): ?>
                        <?php foreach ($allOrders as $order): ?>
                        <tr class="order-row-strip">
                            <td class="ps-4"><span class="order-id-badge">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                            <td>
                                <div class="order-customer-box">
                                    <span class="order-customer-name"><?= htmlspecialchars($order['customer_name']) ?></span>
                                    <div class="order-customer-meta"><i class="bi bi-geo-alt-fill" style="font-size: 0.7rem;"></i> <span class="text-truncate" style="max-width: 150px;"><?= htmlspecialchars($order['address']) ?></span></div>
                                </div>
                            </td>
                            <td><div class="text-dark fw-bold small"><?= date('M d, Y', strtotime($order['created_at'])) ?></div><small class="text-muted"><?= date('h:i A', strtotime($order['created_at'])) ?></small></td>
                            <td><span class="order-total-display">$<?= number_format($order['total_amount'], 2) ?></span></td>
                            <td>
                                <?php 
                                    $s = $order['status'];
                                    $classMap = ['Pending' => 'st-pending', 'Shipped' => 'st-shipped', 'Delivered' => 'st-delivered', 'Cancelled' => 'st-cancelled'];
                                    $stClass = $classMap[$s] ?? 'st-pending';
                                ?>
                                <div class="status-indicator <?= $stClass ?>"><span class="status-dot"></span> <?= $s ?></div>
                            </td>
                            <td class="pe-4 text-end"><a href="order_details.php?order_id=<?= $order['id'] ?>" class="btn-manage-pill text-decoration-none">Manage <i class="bi bi-arrow-right ms-1"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5"><div class="py-5"><i class="bi bi-inbox display-3 text-muted opacity-25 mb-3 d-block"></i><h5 class="text-muted">No <?= $statusFilter == 'All' ? '' : $statusFilter ?> orders found.</h5></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>