<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$view = $_GET['view'] ?? 'dashboard';

// --- VIEW 1: DASHBOARD (Overview) ---
if ($view == 'dashboard') {
    // 1. Fetch Orders
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Chart Data
    $sqlChart = "SELECT DATE(created_at) as order_date, SUM(total_amount) as daily_total FROM orders WHERE status != 'Cancelled' GROUP BY DATE(created_at) ORDER BY order_date ASC LIMIT 7";
    $stmtChart = $pdo->query($sqlChart);
    $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
    $dates = []; $totals = [];
    foreach ($chartData as $data) { $dates[] = date('M d', strtotime($data['order_date'])); $totals[] = $data['daily_total']; }

    // 3. Stats Logic
    $stmtLow = $pdo->query("SELECT * FROM products WHERE stock_qty <= 5");
    $lowStockItems = $stmtLow->fetchAll(PDO::FETCH_ASSOC);
    
    // --- UPDATED REVENUE CALCULATION ---
    $totalRevenue = 0;
    $pendingOrders = 0;

    foreach($orders as $o) { 
        // Only add to revenue if the order is NOT Cancelled
        if($o['status'] != 'Cancelled') {
            $totalRevenue += $o['total_amount'];
        }

        // Count pending orders
        if($o['status'] == 'Pending') {
            $pendingOrders++; 
        }
    }

    ?>
    
    <div class="dash-welcome-banner animate-fade-in">
        <div>
            <h2 class="dash-title">Dashboard Overview</h2>
            <p class="dash-subtitle mb-0">Here's what's happening with your store today.</p>
        </div>
        <a href="export_orders.php" class="btn-glass-white text-decoration-none">
            <i class="bi bi-download me-2"></i> Export Report
        </a>
    </div>

    <div class="row g-4 mb-5 animate-fade-in">
        <div class="col-md-4">
            <div class="dash-stat-card">
                <i class="bi bi-receipt dash-watermark-icon"></i>
                <span class="dash-stat-label">Total Orders</span>
                <div class="dash-stat-value"><?= count($orders) ?></div>
                <div class="mt-2 text-success small fw-bold">
                    <i class="bi bi-arrow-up-right"></i> Lifetime
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="dash-stat-card">
                <i class="bi bi-currency-dollar dash-watermark-icon"></i>
                <span class="dash-stat-label">Total Revenue</span>
                <div class="dash-stat-value">$<?= number_format($totalRevenue, 2) ?></div>
                <div class="mt-2 text-success small fw-bold">
                    <i class="bi bi-graph-up"></i> Trending Up
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="dash-stat-card" style="border-left: 4px solid #ffc107;">
                <i class="bi bi-hourglass-split dash-watermark-icon"></i>
                <span class="dash-stat-label text-warning">Pending Actions</span>
                <div class="dash-stat-value"><?= $pendingOrders ?></div>
                <div class="mt-2 text-warning small fw-bold">
                    Needs Attention
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5 animate-fade-in">
        <div class="col-lg-8">
            <div class="dashboard-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="font-serif m-0">Sales Analytics</h5>
                    <select class="form-select form-select-sm w-auto border-0 bg-light">
                        <option>Last 7 Days</option>
                    </select>
                </div>
                <canvas id="salesChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert-card shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <div class="alert-header-icon me-3">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                        <div>
                            <h5 class="m-0 fw-bold text-dark font-serif">Low Stock</h5>
                            <small class="text-muted">Requires immediate attention</small>
                        </div>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill border border-warning px-3"><?= count($lowStockItems) ?> Items</span>
                </div>
                
                <?php if (count($lowStockItems) > 0): ?>
                    <div style="max-height: 280px; overflow-y: auto; padding: 2px;">
                        <?php foreach ($lowStockItems as $item): 
                            // Calculate simple bar width: (stock / 20) * 100. Max 100%.
                            // This visualizes how close it is to 0.
                            $percent = min(100, ($item['stock_qty'] / 10) * 100); 
                            $img = $item['image'] ? $item['image'] : 'default.jpg';
                        ?>
                            <div class="stock-item-modern">
                                <img src="assets/images/<?= $img ?>" class="stock-img" alt="img">
                                
                                <div class="stock-info">
                                    <span class="stock-name" title="<?= htmlspecialchars($item['name']) ?>">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </span>
                                    <div class="stock-progress-bg">
                                        <div class="stock-progress-bar" style="width: <?= $percent ?>%;"></div>
                                    </div>
                                </div>

                                <div class="stock-badge-critical">
                                    <?= $item['stock_qty'] ?>
                                    <span>Left</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="#" onclick="loadView('products')" class="btn-restock-pulse mt-4 d-block text-decoration-none text-center">
                        Manage Inventory <i class="bi bi-arrow-right ms-2"></i>
                    </a>

                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-check-lg fs-1 text-success"></i>
                        </div>
                        <h6 class="fw-bold">All Good!</h6>
                        <p class="text-muted small">Inventory levels are healthy.</p>
                    </div>
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
                    backgroundColor: (context) => {
                        const ctx = context.chart.ctx;
                        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'rgba(93, 134, 108, 0.2)');
                        gradient.addColorStop(1, 'rgba(93, 134, 108, 0)');
                        return gradient;
                    },
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#5D866C',
                    pointBorderWidth: 2
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
    <?php
}

// --- VIEW 2: PRODUCTS ---
elseif ($view == 'products') {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <div class="inventory-header-card animate-fade-in">
        <div>
            <h2 class="admin-header-title">Product Inventory</h2>
            <p class="text-muted m-0">Manage your catalog, stock levels, and pricing.</p>
        </div>
        <a href="add_product.php" class="btn-add-product text-decoration-none">
            <i class="bi bi-plus-circle-fill fs-5"></i> Add New Product
        </a>
    </div>

    <div class="inventory-table-container animate-fade-in">
        <div class="table-responsive">
            <table class="table table-inventory mb-0">
                <thead>
                    <tr>
                        <th style="padding-left: 2rem;">Item</th>
                        <th>Price</th>
                        <th>Stock Status</th>
                        <th class="text-end" style="padding-right: 2rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="padding-left: 2rem;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="inv-img-box">
                                    <img src="assets/images/<?= $p['image'] ?: 'default.jpg' ?>" alt="img">
                                </div>
                                <div>
                                    <span class="inv-product-name"><?= htmlspecialchars($p['name']) ?></span>
                                    <span class="inv-product-cat"><?= htmlspecialchars($p['category']) ?></span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="inv-price">$<?= number_format($p['price'], 2) ?></span>
                        </td>

                        <td>
                            <?php 
                                if($p['stock_qty'] == 0) {
                                    echo '<div class="stock-status stock-out"><span class="stock-dot"></span> Out of Stock</div>';
                                } elseif($p['stock_qty'] < 5) {
                                    echo '<div class="stock-status stock-low"><span class="stock-dot"></span> Low Stock (' . $p['stock_qty'] . ')</div>';
                                } else {
                                    echo '<div class="stock-status stock-high"><span class="stock-dot"></span> In Stock (' . $p['stock_qty'] . ')</div>';
                                }
                            ?>
                        </td>

                        <td class="text-end" style="padding-right: 2rem;">
                            <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn-icon-action" title="Edit">
                                <i class="bi bi-pencil-fill" style="font-size: 0.9rem;"></i>
                            </a>
                            <a href="delete_product.php?id=<?= $p['id'] ?>" class="btn-icon-action btn-icon-delete" title="Delete" onclick="return confirm('Delete this product?');">
                                <i class="bi bi-trash-fill" style="font-size: 0.9rem;"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if(count($products) == 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-box-seam fs-1 text-muted opacity-50 mb-3 d-block"></i>
                <p class="text-muted">No products found in inventory.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// --- VIEW 3: CUSTOMERS ---
elseif ($view == 'users') {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <div class="d-flex justify-content-between align-items-center mb-5 animate-fade-in">
        <div>
            <h2 class="admin-header-title">Registered Customers</h2>
            <p class="text-muted m-0">Manage your community and view order histories.</p>
        </div>
        <div class="bg-white px-4 py-2 rounded-pill shadow-sm border d-flex align-items-center gap-2">
            <i class="bi bi-people-fill text-success"></i>
            <span class="fw-bold"><?= count($users) ?></span> <span class="text-muted small text-uppercase">Members</span>
        </div>
    </div>

    <div class="row g-4 animate-fade-in">
        <?php foreach ($users as $u): ?>
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="customer-card-grid">
                    
                    <span class="cust-id-badge">ID: <?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?></span>
                    
                    <a href="delete_user.php?id=<?= $u['id'] ?>" class="btn-delete-user" onclick="return confirm('Delete this user?');" title="Delete User">
                        <i class="bi bi-trash-fill"></i>
                    </a>

                    <div class="cust-avatar-lg">
                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                    </div>
                    
                    <h5 class="cust-name text-truncate"><?= htmlspecialchars($u['full_name']) ?></h5>
                    <p class="cust-email text-truncate"><?= htmlspecialchars($u['email']) ?></p>
                    
                    <div class="cust-divider"></div>
                    
                    <span class="cust-joined">
                        <i class="bi bi-calendar2 me-1"></i> Joined <?= date('M d, Y', strtotime($u['created_at'])) ?>
                    </span>

                    <a href="#" onclick="loadView('customer_details&id=<?= $u['id'] ?>')" class="btn-view-orders">
                        View Orders
                    </a>

                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (count($users) == 0): ?>
        <div class="text-center py-5 text-muted animate-fade-in">
            <i class="bi bi-person-x display-1 mb-3 opacity-25"></i>
            <p>No customers registered yet.</p>
        </div>
    <?php endif; ?>

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
    ?>
    
    <div class="d-flex justify-content-between align-items-center mb-5 animate-fade-in">
        <div>
            <h2 class="admin-header-title">Customer Feedback</h2>
            <p class="text-muted m-0">See what people are saying about your products.</p>
        </div>
        <div class="bg-white px-4 py-2 rounded-pill shadow-sm border">
            <span class="fw-bold text-dark"><?= count($reviews) ?></span> <span class="text-muted small text-uppercase">Reviews</span>
        </div>
    </div>

    <div class="row g-4 reviews-grid-container animate-fade-in">
        <?php if (count($reviews) > 0): ?>
            <?php foreach ($reviews as $r): ?>
                <div class="col-xl-4 col-md-6">
                    <div class="review-gallery-card">
                        
                        <div class="review-product-badge">
                            <img src="assets/images/<?= $r['product_image'] ?: 'default.jpg' ?>" class="review-prod-img">
                            <div class="review-prod-name text-truncate">
                                <?= htmlspecialchars($r['product_name']) ?>
                            </div>
                        </div>

                        <div class="review-user-row">
                            <div class="review-avatar">
                                <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                            </div>
                            <div class="review-meta">
                                <span class="review-username"><?= htmlspecialchars($r['full_name']) ?></span>
                                <span class="review-time"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                            </div>
                        </div>

                        <div class="review-stars">
                            <?php for($i=0; $i<$r['rating']; $i++) echo '<i class="bi bi-star-fill"></i>'; ?>
                            <?php for($i=$r['rating']; $i<5; $i++) echo '<i class="bi bi-star text-muted opacity-25"></i>'; ?>
                        </div>

                        <p class="review-text">
                            "<?= htmlspecialchars($r['comment']) ?>"
                        </p>

                        <div class="review-actions">
                            <a href="delete_review.php?id=<?= $r['id'] ?>" class="btn-del-icon" title="Delete Review" onclick="return confirm('Delete this review?');">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-chat-square-heart display-1 text-muted opacity-25 mb-3 d-block"></i>
                <h4 class="text-muted font-serif">No reviews yet.</h4>
                <p class="text-muted">Wait for customers to share their love!</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// --- VIEW 5: INDIVIDUAL CUSTOMER DETAILS & ORDER HISTORY (NEW!) ---
elseif ($view == 'customer_details') {
    $userId = $_GET['id'];
    
    // Fetch User Info
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if(!$user) { echo "<div class='alert alert-danger'>User not found.</div>"; exit; }

    // Fetch Orders for this User
    $stmtOrders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmtOrders->execute([$userId]);
    $userOrders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Stats
    $lifetimeSpend = array_sum(array_column($userOrders, 'total_amount'));
    $totalOrders = count($userOrders);
    $avgOrder = $totalOrders > 0 ? $lifetimeSpend / $totalOrders : 0;
    ?>
    
    <div class="mb-4 animate-fade-in">
        <a href="#" onclick="loadView('users')" class="text-decoration-none text-muted small fw-bold text-uppercase">
            <i class="bi bi-arrow-left me-1"></i> Back to Customers
        </a>
    </div>

    <div class="admin-customer-header animate-fade-in">
        <div class="d-flex align-items-center gap-4">
            <div class="customer-avatar-large">
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="font-serif m-0"><?= htmlspecialchars($user['full_name']) ?></h2>
                <p class="text-muted m-0"><i class="bi bi-envelope me-2"></i> <?= htmlspecialchars($user['email']) ?></p>
                <p class="text-muted m-0 small"><i class="bi bi-geo-alt me-2"></i> <?= htmlspecialchars($user['address'] ?: 'No address provided') ?></p>
            </div>
        </div>

        <div class="customer-stats-row">
            <div>
                <span class="c-stat-label">Total Spend</span>
                <div class="c-stat-value text-success">$<?= number_format($lifetimeSpend, 2) ?></div>
            </div>
            <div>
                <span class="c-stat-label">Total Orders</span>
                <div class="c-stat-value"><?= $totalOrders ?></div>
            </div>
            <div>
                <span class="c-stat-label">Avg. Order</span>
                <div class="c-stat-value text-muted">$<?= number_format($avgOrder, 2) ?></div>
            </div>
            <div>
                <span class="c-stat-label">Customer Since</span>
                <div class="c-stat-value text-muted" style="font-size: 1.1rem; padding-top: 5px;">
                    <?= date('M Y', strtotime($user['created_at'])) ?>
                </div>
            </div>
        </div>
    </div>

    <h4 class="font-serif mb-4 animate-fade-in">Order History</h4>
    
    <?php if (count($userOrders) > 0): ?>
        <div class="timeline-wrapper animate-fade-in">
            <?php foreach ($userOrders as $order): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-card">
                        
                        <div class="timeline-header">
                            <div>
                                <span class="badge bg-light text-dark border me-2">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                <span class="t-date"><?= date('F d, Y • h:i A', strtotime($order['created_at'])) ?></span>
                            </div>
                            <div class="t-total">$<?= number_format($order['total_amount'], 2) ?></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <?php 
                                    $s = $order['status'];
                                    $cls = 'bg-secondary';
                                    if ($s == 'Pending') $cls = 'bg-warning text-dark';
                                    if ($s == 'Shipped') $cls = 'bg-info text-dark';
                                    if ($s == 'Delivered') $cls = 'bg-success';
                                    if ($s == 'Cancelled') $cls = 'bg-danger';
                                ?>
                                <span class="badge rounded-pill <?= $cls ?>"><?= $s ?></span>
                                <span class="small text-muted ms-2">
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($order['address']) ?>
                                </span>
                            </div>
                            
                            <a href="order_details.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3">
                                View Items
                            </a>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5 text-muted animate-fade-in">
            <i class="bi bi-cart-x fs-1 mb-3 d-block opacity-50"></i>
            <p>No orders found for this customer yet.</p>
        </div>
    <?php endif; ?>

    <?php
}
?>