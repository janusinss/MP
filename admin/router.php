<?php
// admin/router.php
// AJAX View Renderer for Admin Dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo "<script>window.location.href='../login';</script>";
    exit;
}

$view = $_GET['view'] ?? 'dashboard';

// =========================================================================
// VIEW 1: DASHBOARD / ANALYTICS
// =========================================================================
if ($view == 'dashboard') {
    // 1. Fetch Orders
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Chart Data (Daily Revenue & Order Counts)
    try {
        $sqlChart = "SELECT * FROM view_daily_sales ORDER BY order_date ASC LIMIT 7";
        $stmtChart = $pdo->query($sqlChart);
        $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $sqlChart = "SELECT cast(created_at as date) AS order_date, sum(total_amount) AS daily_total, count(id) AS order_count FROM orders WHERE status <> 'Cancelled' GROUP BY cast(created_at as date) ORDER BY order_date ASC LIMIT 7";
        $stmtChart = $pdo->query($sqlChart);
        $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
    }
    $dates = [];
    $totals = [];
    $orderCounts = [];
    foreach ($chartData as $data) {
        $dates[] = date('M d', strtotime($data['order_date']));
        $totals[] = (float)$data['daily_total'];
        $orderCounts[] = (int)($data['order_count'] ?? 0);
    }

    // 3. Top Category Sales Distribution
    $topCategories = [];
    $totalCatRevenue = 0;
    try {
        $stmtCats = $pdo->query("SELECT p.category, SUM(oi.quantity * p.price) as cat_revenue, SUM(oi.quantity) as cat_qty 
                                 FROM order_items oi 
                                 JOIN products p ON oi.product_id = p.id 
                                 JOIN orders o ON oi.order_id = o.id 
                                 WHERE o.status != 'Cancelled' 
                                 GROUP BY p.category 
                                 ORDER BY cat_revenue DESC LIMIT 5");
        $topCategories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        foreach ($topCategories as $tc) {
            $totalCatRevenue += (float)$tc['cat_revenue'];
        }
    } catch (PDOException $e) {
        $topCategories = [];
    }

    // 4. Low stock items
    $stmtLow = $pdo->query("SELECT * FROM products WHERE stock_qty <= 5 OR stock_qty < (SELECT AVG(stock_qty) FROM products) * 0.1 ORDER BY stock_qty ASC LIMIT 5");
    $lowStockItems = $stmtLow->fetchAll(PDO::FETCH_ASSOC);

    // 5. Aggregate Financial & Order Metrics
    $totalRevenue = 0;
    $statusCounts = ['Pending' => 0, 'Shipped' => 0, 'Delivered' => 0, 'Cancelled' => 0];
    foreach ($orders as $o) {
        $st = $o['status'];
        if (isset($statusCounts[$st])) {
            $statusCounts[$st]++;
        }
        if ($st != 'Cancelled') {
            $totalRevenue += (float)$o['total_amount'];
        }
    }

    $totalOrderCount = count($orders);
    $pendingOrders = $statusCounts['Pending'];
    $deliveredOrders = $statusCounts['Delivered'];
    $shippedOrders = $statusCounts['Shipped'];
    $cancelledOrders = $statusCounts['Cancelled'];
    $validOrdersCount = $deliveredOrders + $shippedOrders + $pendingOrders;
    $fulfillmentRate = $totalOrderCount > 0 ? round((($deliveredOrders + $shippedOrders) / $totalOrderCount) * 100) : 100;
    $aov = ($validOrdersCount > 0 && $totalRevenue > 0) ? ($totalRevenue / max(1, $validOrdersCount)) : 0;
    $recentOrders = array_slice($orders, 0, 6);

    // 6. Secondary Store Metrics
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    $stmtCatalog = $pdo->query("SELECT COUNT(*) as total_skus, 
                                       SUM(CASE WHEN stock_qty = 0 THEN 1 ELSE 0 END) as out_of_stock,
                                       SUM(CASE WHEN stock_qty > 0 AND stock_qty <= 5 THEN 1 ELSE 0 END) as low_stock,
                                       SUM(stock_qty) as total_units
                                FROM products");
    $catalogStats = $stmtCatalog->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalSkus = (int)($catalogStats['total_skus'] ?? 0);
    $outOfStockCount = (int)($catalogStats['out_of_stock'] ?? 0);
    $lowStockCount = (int)($catalogStats['low_stock'] ?? 0);
    $totalUnits = (int)($catalogStats['total_units'] ?? 0);

    $stmtRev = $pdo->query("SELECT COUNT(*) as rev_count, AVG(rating) as avg_rating FROM reviews");
    $revStats = $stmtRev->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalRevCount = (int)($revStats['rev_count'] ?? 0);
    $avgRevRating = $totalRevCount > 0 ? number_format((float)$revStats['avg_rating'], 1) : '5.0';
    ?>

    <!-- View Header -->
    <div class="admin-view-header">
        <div>
            <span class="admin-kicker">Store Operations &amp; Intelligence</span>
            <h1 class="admin-view-title">Analytics</h1>
            <p class="admin-view-subtitle">Dashboard Overview &bull; Live store sales, order velocity, and inventory health.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small me-2 d-none d-md-inline">
                <i class="bi bi-clock-history text-success me-1"></i> Live Sync
            </span>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadView('dashboard')" title="Refresh Data">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <a href="export_orders.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download me-1"></i> Export Report
            </a>
        </div>
    </div>

    <!-- 1. Primary 4 KPI Cards -->
    <div class="row g-3 mb-4">
        <!-- Gross Revenue -->
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card">
                <div class="admin-kpi-label">Gross Revenue</div>
                <div class="admin-kpi-value text-dark">$<?= number_format($totalRevenue, 2) ?></div>
                <div class="admin-kpi-caption">
                    <span class="text-success fw-semibold">Net Sales</span> &bull; Paid customer checkouts
                </div>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card interactive" onclick="loadView('orders')" title="View all customer orders">
                <div class="admin-kpi-label">Total Orders</div>
                <div class="admin-kpi-value text-dark"><?= $totalOrderCount ?></div>
                <div class="admin-kpi-caption">
                    <span class="fw-semibold text-dark"><?= $fulfillmentRate ?>% Fulfilled</span> &bull; <?= $deliveredOrders ?> delivered
                </div>
            </div>
        </div>

        <!-- Average Order Value -->
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card">
                <div class="admin-kpi-label">Average Order Value</div>
                <div class="admin-kpi-value text-dark">$<?= number_format($aov, 2) ?></div>
                <div class="admin-kpi-caption">
                    Mean basket value across paid orders
                </div>
            </div>
        </div>

        <!-- Pending Orders -->
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card interactive" onclick="loadView('orders&status=Pending')" title="Filter by pending orders">
                <div class="admin-kpi-label">Pending Orders</div>
                <div class="admin-kpi-value <?= $pendingOrders > 0 ? 'text-dark' : 'text-muted' ?>">
                    <?= $pendingOrders ?>
                </div>
                <div class="admin-kpi-caption">
                    <?php if ($pendingOrders > 0): ?>
                        <span class="text-danger fw-semibold"><?= $pendingOrders ?> orders</span> awaiting warehouse pack
                    <?php else: ?>
                        <span class="text-success fw-semibold">All clear</span> &bull; Zero queue backlog
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Operational Health Unified Strip -->
    <div class="admin-card p-0 mb-4 overflow-hidden">
        <div class="row g-0">
            <!-- Col 1: Fulfillment Pipeline -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Fulfillment Pipeline</div>
                <div class="d-flex align-items-baseline justify-content-between mb-2">
                    <span class="fw-bold text-dark fs-6" style="font-variant-numeric: tabular-nums;"><?= $fulfillmentRate ?>% Dispatched</span>
                    <span class="text-muted small"><?= $deliveredOrders ?> / <?= $totalOrderCount ?> Orders</span>
                </div>
                <div class="admin-pipeline-bar mb-2">
                    <?php 
                        $delPct = $totalOrderCount > 0 ? round(($deliveredOrders / $totalOrderCount) * 100) : 0;
                        $shipPct = $totalOrderCount > 0 ? round(($shippedOrders / $totalOrderCount) * 100) : 0;
                        $penPct = $totalOrderCount > 0 ? round(($pendingOrders / $totalOrderCount) * 100) : 0;
                        $canPct = $totalOrderCount > 0 ? round(($cancelledOrders / $totalOrderCount) * 100) : 0;
                    ?>
                    <div class="admin-pipeline-seg bg-success" style="width: <?= $delPct ?>%;" title="Delivered: <?= $deliveredOrders ?>"></div>
                    <div class="admin-pipeline-seg bg-primary" style="width: <?= $shipPct ?>%;" title="Shipped: <?= $shippedOrders ?>"></div>
                    <div class="admin-pipeline-seg bg-warning" style="width: <?= $penPct ?>%;" title="Pending: <?= $pendingOrders ?>"></div>
                    <div class="admin-pipeline-seg bg-secondary" style="width: <?= $canPct ?>%;" title="Cancelled: <?= $cancelledOrders ?>"></div>
                </div>
                <div class="d-flex justify-content-between text-muted" style="font-size: 0.72rem;">
                    <span><i class="bi bi-circle-fill text-success" style="font-size: 0.5rem;"></i> <?= $deliveredOrders ?> Del</span>
                    <span><i class="bi bi-circle-fill text-primary" style="font-size: 0.5rem;"></i> <?= $shippedOrders ?> Ship</span>
                    <span><i class="bi bi-circle-fill text-warning" style="font-size: 0.5rem;"></i> <?= $pendingOrders ?> Pen</span>
                </div>
            </div>

            <!-- Col 2: Catalog Stock Health -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Catalog Stock Health</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-6" style="font-variant-numeric: tabular-nums;"><?= number_format($totalUnits) ?> Units</span>
                    <span class="text-muted small"><?= $totalSkus ?> SKUs</span>
                </div>
                <div class="text-secondary small mt-2">
                    <?php if ($outOfStockCount > 0): ?>
                        <span class="text-danger fw-semibold"><?= $outOfStockCount ?> Out of Stock</span> &bull; Needs Restock
                    <?php elseif ($lowStockCount > 0): ?>
                        <span class="text-warning-emphasis fw-semibold"><?= $lowStockCount ?> Low Stock Items</span>
                    <?php else: ?>
                        <span class="text-success fw-semibold"><i class="bi bi-check2 me-1"></i>Adequate stock</span> across all aisles
                    <?php endif; ?>
                </div>
            </div>

            <!-- Col 3: Customer Accounts -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-sm-bottom-0">
                <div class="admin-kpi-label mb-1">Client Accounts</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-6" style="font-variant-numeric: tabular-nums;"><?= $totalUsers ?> Profiles</span>
                    <span class="text-muted small">Registered</span>
                </div>
                <div class="mt-2">
                    <a href="#" onclick="loadView('users')" class="text-success text-decoration-none small fw-semibold">
                        View Client Directory <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Col 4: Customer Satisfaction -->
            <div class="col-sm-6 col-xl-3 p-3 px-4">
                <div class="admin-kpi-label mb-1">Customer Satisfaction</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-6" style="font-variant-numeric: tabular-nums;">
                        <i class="bi bi-star-fill text-warning me-1"></i><?= $avgRevRating ?> / 5.0
                    </span>
                    <span class="text-muted small"><?= $totalRevCount ?> Reviews</span>
                </div>
                <div class="mt-2">
                    <a href="#" onclick="loadView('reviews')" class="text-success text-decoration-none small fw-semibold">
                        Review Gallery <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Interactive Chart & Department Share -->
    <div class="row g-4 mb-4">
        <!-- Sales & Velocity Chart -->
        <div class="col-lg-8">
            <div class="admin-card h-100">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="admin-card-heading">Sales Revenue &amp; Velocity</h2>
                        <p class="text-muted small mb-0">Daily gross revenue and order frequency over the past 7 recorded days.</p>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Metric Toggle">
                        <button type="button" class="btn btn-dark" id="btnChartRev" onclick="switchChartMetric('revenue')">
                            Revenue ($)
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnChartVol" onclick="switchChartMetric('volume')">
                            Orders
                        </button>
                    </div>
                </div>
                <div style="height: 300px; position: relative;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Department Share -->
        <div class="col-lg-4">
            <div class="admin-card h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <h2 class="admin-card-heading">Department Share</h2>
                        <span class="text-muted small fw-semibold">$<?= number_format($totalCatRevenue, 2) ?> Tracked</span>
                    </div>
                    <p class="text-muted small mb-3">Gross revenue distribution by product aisle.</p>

                    <?php if (!empty($topCategories)): ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($topCategories as $tc): 
                                $pct = ($totalCatRevenue > 0) ? round(((float)$tc['cat_revenue'] / $totalCatRevenue) * 100) : 0;
                            ?>
                                <div>
                                    <div class="d-flex justify-content-between align-items-center small mb-1">
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($tc['category']) ?></span>
                                        <span class="text-muted" style="font-variant-numeric: tabular-nums;">
                                            $<?= number_format((float)$tc['cat_revenue'], 2) ?> &bull; <?= $pct ?>%
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 6px; background-color: #f1f5f9;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= max(6, $pct) ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="py-4 text-center text-muted small">No category sales recorded yet.</div>
                    <?php endif; ?>
                </div>

                <div class="border-top pt-3 mt-3">
                    <div class="d-flex justify-content-between align-items-center small text-muted">
                        <span>Total Aisle Volume</span>
                        <span class="fw-bold text-dark fs-6" style="font-variant-numeric: tabular-nums;">
                            $<?= number_format($totalCatRevenue, 2) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Inventory Alerts & Recent Orders Stream -->
    <div class="row g-4 mb-4">
        <!-- Low Inventory Watchlist -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="admin-card-heading">Low Inventory Watchlist</h2>
                        <p class="text-muted small mb-0">Products at or below reorder threshold.</p>
                    </div>
                    <span class="text-muted small fw-semibold"><?= count($lowStockItems) ?> Items</span>
                </div>

                <?php if (count($lowStockItems) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                            <tbody>
                                <?php foreach ($lowStockItems as $item):
                                    $qty = (int)$item['stock_qty'];
                                    $img = $item['image'] ?: 'default.jpg';
                                ?>
                                    <tr class="border-bottom">
                                        <td style="width: 44px; padding: 8px 0;">
                                            <img src="../assets/images/<?= htmlspecialchars($img) ?>" style="width: 36px; height: 36px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0;" alt="thumb">
                                        </td>
                                        <td style="padding: 8px;">
                                            <div class="fw-semibold text-dark small text-truncate" style="max-width: 220px;"><?= htmlspecialchars($item['name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;">
                                                <span style="font-family: monospace;">SKU: #<?= str_pad($item['id'], 4, '0', STR_PAD_LEFT) ?></span> &bull; <?= htmlspecialchars($item['category']) ?>
                                            </div>
                                        </td>
                                        <td class="text-center" style="padding: 8px;">
                                            <?php if ($qty === 0): ?>
                                                <span class="admin-status-pill status-cancelled">Out of stock</span>
                                            <?php else: ?>
                                                <span class="admin-status-pill status-pending"><?= $qty ?> remaining</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end" style="padding: 8px 0;">
                                            <a href="product_edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-2 fw-semibold" style="font-size: 0.75rem;">
                                                Restock
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pt-3 mt-1 text-end border-top">
                        <a href="#" onclick="loadView('products')" class="text-success text-decoration-none small fw-semibold">
                            Open Product Inventory <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="py-5 text-center text-muted small">
                        <i class="bi bi-check2-circle text-success fs-2 d-block mb-2"></i>
                        All catalog items meet healthy inventory reorder levels.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="admin-card-heading">Recent Orders</h2>
                        <p class="text-muted small mb-0">Latest customer order transactions.</p>
                    </div>
                    <a href="#" onclick="loadView('orders')" class="text-success text-decoration-none small fw-semibold">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

                <?php if (!empty($recentOrders)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                            <tbody>
                                <?php foreach ($recentOrders as $ro): 
                                    $status = $ro['status'];
                                    $pillClass = match($status) {
                                        'Delivered' => 'status-delivered',
                                        'Shipped' => 'status-shipped',
                                        'Pending' => 'status-pending',
                                        default => 'status-cancelled'
                                    };
                                ?>
                                    <tr class="border-bottom">
                                        <td style="padding: 8px 0; font-family: monospace; font-size: 0.8rem; font-weight: 600;">
                                            #<?= str_pad($ro['id'], 5, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td style="padding: 8px;">
                                            <div class="fw-semibold text-dark small"><?= htmlspecialchars($ro['customer_name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= date('M d, Y', strtotime($ro['created_at'])) ?></div>
                                        </td>
                                        <td class="text-end" style="padding: 8px; font-weight: 600; font-size: 0.85rem; font-variant-numeric: tabular-nums;">
                                            $<?= number_format((float)$ro['total_amount'], 2) ?>
                                        </td>
                                        <td class="text-center" style="padding: 8px;">
                                            <span class="admin-status-pill <?= $pillClass ?>"><?= $status ?></span>
                                        </td>
                                        <td class="text-end" style="padding: 8px 0;">
                                            <a href="order_details.php?order_id=<?= $ro['id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-2 fw-semibold" style="font-size: 0.75rem;">
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="py-5 text-center text-muted small">No customer orders recorded yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chart Configuration Script -->
    <script>
        (function() {
            const dates = <?= json_encode($dates) ?>;
            const revenueTotals = <?= json_encode($totals) ?>;
            const volumeCounts = <?= json_encode($orderCounts) ?>;

            if (window.salesChartInstance) {
                window.salesChartInstance.destroy();
            }

            const canvas = document.getElementById('salesChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            // Create gradient
            const revGradient = ctx.createLinearGradient(0, 0, 0, 300);
            revGradient.addColorStop(0, 'rgba(21, 128, 61, 0.16)');
            revGradient.addColorStop(1, 'rgba(21, 128, 61, 0.0)');

            const volGradient = ctx.createLinearGradient(0, 0, 0, 300);
            volGradient.addColorStop(0, 'rgba(37, 99, 235, 0.16)');
            volGradient.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

            let currentMetric = 'revenue';

            window.salesChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Gross Revenue ($)',
                        data: revenueTotals,
                        borderColor: '#15803d',
                        backgroundColor: revGradient,
                        borderWidth: 2.2,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#15803d',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 10,
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 12 },
                            cornerRadius: 6,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    if (currentMetric === 'revenue') {
                                        return 'Revenue: $' + Number(context.raw).toFixed(2);
                                    } else {
                                        return 'Orders: ' + context.raw;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: {
                                font: { size: 11 },
                                color: '#64748b',
                                callback: function(value) {
                                    return currentMetric === 'revenue' ? '$' + value : value;
                                }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 }, color: '#64748b' }
                        }
                    }
                }
            });

            window.switchChartMetric = function(metric) {
                currentMetric = metric;
                const btnRev = document.getElementById('btnChartRev');
                const btnVol = document.getElementById('btnChartVol');

                if (metric === 'revenue') {
                    btnRev.className = 'btn btn-dark';
                    btnVol.className = 'btn btn-outline-secondary';
                    window.salesChartInstance.data.datasets[0].label = 'Gross Revenue ($)';
                    window.salesChartInstance.data.datasets[0].data = revenueTotals;
                    window.salesChartInstance.data.datasets[0].borderColor = '#15803d';
                    window.salesChartInstance.data.datasets[0].backgroundColor = revGradient;
                    window.salesChartInstance.data.datasets[0].pointBackgroundColor = '#15803d';
                } else {
                    btnRev.className = 'btn btn-outline-secondary';
                    btnVol.className = 'btn btn-dark';
                    window.salesChartInstance.data.datasets[0].label = 'Order Volume';
                    window.salesChartInstance.data.datasets[0].data = volumeCounts;
                    window.salesChartInstance.data.datasets[0].borderColor = '#2563eb';
                    window.salesChartInstance.data.datasets[0].backgroundColor = volGradient;
                    window.salesChartInstance.data.datasets[0].pointBackgroundColor = '#2563eb';
                }
                window.salesChartInstance.update();
            };
        })();
    </script>
    <?php
}

// =========================================================================
// VIEW 2: PRODUCTS
// =========================================================================
elseif ($view == 'products') {
    $search = trim($_GET['search'] ?? '');
    $categoryFilter = trim($_GET['category'] ?? 'All');
    $stockFilter = trim($_GET['stock'] ?? 'All');

    // 1. Inventory telemetry stats across full catalog
    $totalSkus = 0;
    $totalUnits = 0;
    $totalValuation = 0.0;
    $outOfStockCount = 0;
    $lowStockCount = 0;
    $categoryCounts = [];

    // Scoped counts for active Aisle category filter
    $scopedTotal = 0;
    $scopedLowStock = 0;
    $scopedOutOfStock = 0;

    $stmtAllProd = $pdo->query("SELECT category, price, stock_qty FROM products");
    while ($r = $stmtAllProd->fetch(PDO::FETCH_ASSOC)) {
        $totalSkus++;
        $qty = (int)$r['stock_qty'];
        $pr = (float)$r['price'];
        $cat = $r['category'] ?: 'General';

        $totalUnits += $qty;
        $totalValuation += ($qty * $pr);

        if ($qty === 0) {
            $outOfStockCount++;
        } elseif ($qty < 5) {
            $lowStockCount++;
        }

        $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;

        // Scoped check for current category filter
        if ($categoryFilter === 'All' || $categoryFilter === $cat) {
            $scopedTotal++;
            if ($qty === 0) {
                $scopedOutOfStock++;
            } elseif ($qty < 5) {
                $scopedLowStock++;
            }
        }
    }
    ksort($categoryCounts);
    $alertsCount = $outOfStockCount + $lowStockCount;

    // 2. Query filtered products
    $sql = "SELECT * FROM products";
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "(name LIKE ? OR category LIKE ? OR CAST(id AS CHAR) LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($categoryFilter !== 'All' && $categoryFilter !== '') {
        $where[] = "category = ?";
        $params[] = $categoryFilter;
    }

    if ($stockFilter === 'low') {
        $where[] = "stock_qty > 0 AND stock_qty < 5";
    } elseif ($stockFilter === 'out') {
        $where[] = "stock_qty = 0";
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="admin-view-header">
        <div>
            <span class="admin-kicker">Catalog &amp; Stock Control</span>
            <h1 class="admin-view-title">Product Inventory</h1>
            <p class="admin-view-subtitle">Live catalog stock tracking, pricing, and product records.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="product_add.php" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1">
                <i class="bi bi-plus-circle"></i> Add New Product
            </a>
        </div>
    </div>

    <!-- 1. Inventory Operational Telemetry Strip -->
    <div class="admin-card p-0 mb-4 overflow-hidden">
        <div class="row g-0">
            <!-- Col 1: Total SKUs -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Catalog SKUs</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;"><?= number_format($totalSkus) ?> Products</span>
                    <span class="text-muted small"><?= count($categoryCounts) ?> Aisles</span>
                </div>
                <div class="text-muted small">
                    Active grocery inventory
                </div>
            </div>

            <!-- Col 2: On-Hand Units -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Total On-Hand Units</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;"><?= number_format($totalUnits) ?> Units</span>
                    <span class="text-muted small">In Warehouse</span>
                </div>
                <div class="text-muted small">
                    Physical shelf inventory
                </div>
            </div>

            <!-- Col 3: Inventory Valuation -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-sm-bottom-0">
                <div class="admin-kpi-label mb-1">Catalog Asset Value</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;">$<?= number_format($totalValuation, 2) ?></span>
                    <span class="text-success small fw-semibold">Retail Total</span>
                </div>
                <div class="text-muted small">
                    Gross inventory evaluation
                </div>
            </div>

            <!-- Col 4: Reorder Health -->
            <div class="col-sm-6 col-xl-3 p-3 px-4">
                <div class="admin-kpi-label mb-1">Inventory Health</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold fs-5 <?= $outOfStockCount > 0 ? 'text-danger' : ($lowStockCount > 0 ? 'text-warning-emphasis' : 'text-success') ?>" style="font-variant-numeric: tabular-nums;">
                        <?= $alertsCount > 0 ? $alertsCount . ' Alerts' : '100% Stocked' ?>
                    </span>
                    <span class="text-muted small">Threshold: 5</span>
                </div>
                <div class="small">
                    <?php if ($outOfStockCount > 0): ?>
                        <span class="text-danger fw-semibold"><?= $outOfStockCount ?> out of stock</span> &bull; Reorder needed
                    <?php elseif ($lowStockCount > 0): ?>
                        <span class="text-warning-emphasis fw-semibold"><?= $lowStockCount ?> low stock</span> &bull; Monitor aisles
                    <?php else: ?>
                        <span class="text-success fw-semibold"><i class="bi bi-check2 me-1"></i>All shelves healthy</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Search & Category Filters -->
    <div class="admin-card p-3 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <!-- Search Form -->
            <form onsubmit="event.preventDefault(); loadView('products<?= ($categoryFilter !== 'All') ? '&category=' . urlencode($categoryFilter) : '' ?><?= ($stockFilter !== 'All') ? '&stock=' . urlencode($stockFilter) : '' ?>&search=' + encodeURIComponent(this.search.value));" class="d-flex align-items-center gap-2" style="max-width: 320px; width: 100%;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, SKU..." value="<?= htmlspecialchars($search) ?>" style="font-size: 0.82rem;">
                </div>
                <button type="submit" class="btn btn-sm btn-dark px-3 fw-semibold text-nowrap">Search</button>
                <?php if ($search !== ''): ?>
                    <button type="button" onclick="loadView('products<?= ($categoryFilter !== 'All') ? '&category=' . urlencode($categoryFilter) : '' ?><?= ($stockFilter !== 'All') ? '&stock=' . urlencode($stockFilter) : '' ?>')" class="btn btn-sm btn-outline-secondary px-2 text-nowrap">Clear</button>
                <?php endif; ?>
            </form>

            <!-- Aisle & Stock Filter Controls -->
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Stock Status Filter Tabs (Consistent btn-group) -->
                <div class="btn-group btn-group-sm" role="group" aria-label="Stock Filter">
                    <?php
                    $stockOptions = [
                        'All' => ['label' => 'All Stock', 'count' => $scopedTotal],
                        'low' => ['label' => 'Low Stock (< 5)', 'count' => $scopedLowStock],
                        'out' => ['label' => 'Out of Stock (0)', 'count' => $scopedOutOfStock]
                    ];
                    foreach ($stockOptions as $stKey => $stData):
                        $isActive = ($stockFilter === $stKey);
                        $activeClass = $isActive ? 'btn-dark' : 'btn-outline-secondary';
                        $param = "products&stock=$stKey" . ($categoryFilter !== 'All' ? "&category=" . urlencode($categoryFilter) : '') . ($search !== '' ? "&search=" . urlencode($search) : '');
                    ?>
                        <button type="button" onclick="loadView('<?= $param ?>')" class="btn <?= $activeClass ?>">
                            <?= $stData['label'] ?> <span class="badge <?= $isActive ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary' ?> ms-1" style="font-size: 0.68rem;"><?= $stData['count'] ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Custom Interactive Aisle Dropdown -->
                <div class="dropdown position-relative d-inline-block">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-inline-flex align-items-center gap-1" type="button" id="aisleDropdownBtn" onclick="toggleAisleMenu(event)">
                        <span>Aisle: <strong><?= htmlspecialchars($categoryFilter) ?></strong></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" id="aisleDropdownMenu" style="font-size: 0.82rem; min-width: 220px; z-index: 1050;">
                        <li>
                            <a class="dropdown-item py-2 d-flex justify-content-between align-items-center <?= ($categoryFilter === 'All') ? 'active fw-bold' : '' ?>" href="javascript:void(0)" onclick="selectAisle('All')">
                                <span>All Categories</span>
                                <span class="badge rounded-pill ms-2 <?= ($categoryFilter === 'All') ? 'bg-white text-dark' : 'bg-secondary-subtle text-secondary' ?>"><?= $totalSkus ?></span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <?php foreach ($categoryCounts as $cName => $cCount): 
                            $isCatActive = ($categoryFilter === $cName);
                        ?>
                            <li>
                                <a class="dropdown-item py-2 d-flex justify-content-between align-items-center <?= $isCatActive ? 'active fw-bold' : '' ?>" href="javascript:void(0)" onclick="selectAisle('<?= htmlspecialchars(addslashes($cName)) ?>')">
                                    <span><?= htmlspecialchars($cName) ?></span>
                                    <span class="badge rounded-pill ms-2 <?= $isCatActive ? 'bg-white text-dark' : 'bg-secondary-subtle text-secondary' ?>"><?= $cCount ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if ($categoryFilter !== 'All' || $stockFilter !== 'All'): ?>
                    <button type="button" onclick="loadView('products')" class="btn btn-sm btn-link text-muted text-decoration-none small py-1 px-2" title="Reset all category and stock filters">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleAisleMenu(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const menu = document.getElementById('aisleDropdownMenu');
            if (menu) {
                menu.classList.toggle('show');
            }
        }

        function selectAisle(category) {
            const stock = '<?= urlencode($stockFilter) ?>';
            const search = '<?= urlencode($search) ?>';
            let param = 'products';
            if (category !== 'All') {
                param += '&category=' + encodeURIComponent(category);
            }
            if (stock !== 'All') {
                param += '&stock=' + stock;
            }
            if (search !== '') {
                param += '&search=' + search;
            }
            loadView(param);
        }

        document.addEventListener('click', function(e) {
            const menu = document.getElementById('aisleDropdownMenu');
            const btn = document.getElementById('aisleDropdownBtn');
            if (menu && menu.classList.contains('show')) {
                if (!btn || !btn.contains(e.target)) {
                    menu.classList.remove('show');
                }
            }
        });
    </script>

    <!-- 3. Inventory Products Master Table -->
    <div class="admin-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom">
                    <tr>
                        <th class="ps-3 py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Product Item</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Aisle / Category</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Retail Price</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">On-Hand Stock</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Stock Health</th>
                        <th class="pe-3 py-3 text-end text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $p): 
                            $sq = (int)$p['stock_qty'];
                            if ($sq === 0) {
                                $pill = '<span class="admin-status-pill status-cancelled">Out of Stock</span>';
                            } elseif ($sq < 5) {
                                $pill = '<span class="admin-status-pill status-pending">Low Stock</span>';
                            } else {
                                $pill = '<span class="admin-status-pill status-delivered">In Stock</span>';
                            }
                        ?>
                            <tr class="border-bottom">
                                <td class="ps-3 py-2">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="../assets/images/<?= $p['image'] ?: 'default.jpg' ?>" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0;" alt="thumb">
                                        <div>
                                            <div class="fw-semibold text-dark small"><?= htmlspecialchars($p['name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem; font-family: monospace;">SKU: #<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <span class="text-dark small fw-medium"><?= htmlspecialchars($p['category']) ?></span>
                                </td>
                                <td class="py-2">
                                    <span class="fw-semibold text-dark small" style="font-variant-numeric: tabular-nums;">$<?= number_format((float)$p['price'], 2) ?></span>
                                </td>
                                <td class="py-2">
                                    <span class="fw-semibold small text-dark" style="font-variant-numeric: tabular-nums;">
                                        <?= $sq ?> <?= $sq === 1 ? 'unit' : 'units' ?>
                                    </span>
                                </td>
                                <td class="py-2">
                                    <?= $pill ?>
                                </td>
                                <td class="pe-3 py-2 text-end">
                                    <a href="product_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-2 me-1" title="Edit Product">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="actions/product_delete.php?id=<?= $p['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Product" onclick="return confirm('Delete <?= htmlspecialchars(addslashes($p['name'])) ?> from catalog?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div class="py-3">
                                    <i class="bi bi-basket text-muted opacity-50 fs-2 d-block mb-2"></i>
                                    <div>No products found matching the criteria.</div>
                                    <?php if ($search !== '' || $categoryFilter !== 'All' || $stockFilter !== 'All'): ?>
                                        <button type="button" onclick="loadView('products')" class="btn btn-sm btn-link text-decoration-none mt-2">
                                            Reset Filters
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// =========================================================================
// VIEW 3: USERS
// =========================================================================
elseif ($view == 'users') {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="admin-view-header">
        <div>
            <span class="admin-kicker">Customer Directory</span>
            <h1 class="admin-view-title">Registered Customers</h1>
            <p class="admin-view-subtitle">Registered account holders and client transaction profiles.</p>
        </div>
        <div>
            <span class="text-muted small fw-semibold"><?= count($users) ?> Total Customers</span>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($users as $u): ?>
            <div class="col-md-6 col-xl-4">
                <div class="admin-card p-3 d-flex flex-column justify-content-between h-100">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-light text-muted border" style="font-family: monospace; font-size: 0.7rem;">
                                ID: #<?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?>
                            </span>
                            <a href="actions/user_delete.php?id=<?= $u['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="text-muted hover-danger" onclick="return confirm('Delete this user account?');" title="Delete User">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="admin-avatar-initial" style="width: 40px; height: 40px; font-size: 1rem;">
                                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                            </div>
                            <div class="overflow-hidden">
                                <div class="fw-semibold text-dark text-truncate small"><?= htmlspecialchars($u['full_name']) ?></div>
                                <div class="text-muted text-truncate" style="font-size: 0.75rem;"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size: 0.72rem;">
                            Joined <?= date('M d, Y', strtotime($u['created_at'])) ?>
                        </span>
                        <a href="#" onclick="loadView('customer_details&id=<?= $u['id'] ?>')" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;">
                            View Orders
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// =========================================================================
// VIEW 4: REVIEWS
// =========================================================================
elseif ($view == 'reviews') {
    $stmt = $pdo->query("SELECT r.*, u.full_name, p.name as product_name, p.image as product_image 
                         FROM reviews r 
                         JOIN users u ON r.user_id = u.id 
                         JOIN products p ON r.product_id = p.id 
                         ORDER BY r.created_at DESC");
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalReviews = count($reviews);
    $avgRating = 0;
    if ($totalReviews > 0) {
        $sum = array_sum(array_column($reviews, 'rating'));
        $avgRating = number_format($sum / $totalReviews, 1);
    }
    ?>
    <div class="admin-view-header">
        <div>
            <span class="admin-kicker">Shopper Feedback</span>
            <h1 class="admin-view-title">Review Gallery</h1>
            <p class="admin-view-subtitle">Customer product feedback and satisfaction ratings.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small"><strong><?= $totalReviews ?></strong> Reviews</span>
            <span class="text-muted small"><strong><?= $avgRating ?></strong> / 5.0 Average</span>
        </div>
    </div>

    <div class="row g-3">
        <?php if ($totalReviews > 0): ?>
            <?php foreach ($reviews as $r): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="admin-card p-3 d-flex flex-column justify-content-between h-100">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-semibold text-dark small"><?= htmlspecialchars($r['full_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
                                </div>
                                <div class="text-warning small">
                                    <?php for ($i = 0; $i < $r['rating']; $i++): ?>
                                        <i class="bi bi-star-fill"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-light rounded" style="font-size: 0.75rem;">
                                <img src="../assets/images/<?= $r['product_image'] ?: 'default.jpg' ?>" style="width: 24px; height: 24px; object-fit: cover; border-radius: 4px;" alt="prod">
                                <span class="fw-semibold text-dark text-truncate"><?= htmlspecialchars($r['product_name']) ?></span>
                            </div>
                            <p class="text-secondary small mb-0" style="font-style: italic; line-height: 1.4;">
                                "<?= htmlspecialchars($r['comment']) ?>"
                            </p>
                        </div>

                        <div class="border-top pt-2 mt-3 text-end">
                            <a href="actions/review_delete.php?id=<?= $r['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="text-muted hover-danger small text-decoration-none" onclick="return confirm('Delete this review?');" title="Delete review">
                                <i class="bi bi-trash me-1"></i> Remove
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="admin-card text-center py-5 text-muted">
                    No customer product reviews submitted yet.
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// =========================================================================
// VIEW 5: CUSTOMER DETAILS (DRILL DOWN)
// =========================================================================
elseif ($view == 'customer_details') {
    $userId = (int)($_GET['id'] ?? 0);
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo '<div class="alert alert-danger">Customer not found.</div>';
        exit;
    }

    $stmtOrders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmtOrders->execute([$userId]);
    $userOrders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    try {
        $stmtFn = $pdo->prepare("SELECT fn_get_total_spent(?)");
        $stmtFn->execute([$userId]);
        $lifetimeSpend = $stmtFn->fetchColumn() ?: 0.00;
    } catch (PDOException $e) {
        $stmtAlloc = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE user_id = ? AND status != 'Cancelled'");
        $stmtAlloc->execute([$userId]);
        $lifetimeSpend = $stmtAlloc->fetchColumn() ?: 0.00;
    }

    $totalOrders = count($userOrders);
    $avgOrder = $totalOrders > 0 ? $lifetimeSpend / $totalOrders : 0;
    ?>
    <div class="mb-3">
        <a href="#" onclick="loadView('users')" class="text-decoration-none text-muted small fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Customers
        </a>
    </div>

    <div class="admin-card p-4 mb-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="admin-avatar-initial" style="width: 52px; height: 52px; font-size: 1.4rem;">
                <?= strtoupper(substr($user['full_name'] ?? '?', 0, 1)) ?>
            </div>
            <div>
                <h2 class="fs-5 fw-bold text-dark mb-0"><?= htmlspecialchars($user['full_name'] ?? 'Unknown') ?></h2>
                <div class="text-muted small"><?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
        </div>

        <div class="row g-3 border-top pt-3">
            <div class="col-sm-4">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem;">Lifetime Spend</div>
                <div class="fs-5 fw-bold text-success">$<?= number_format((float)$lifetimeSpend, 2) ?></div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem;">Orders Placed</div>
                <div class="fs-5 fw-bold text-dark"><?= $totalOrders ?></div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem;">Average Order Value</div>
                <div class="fs-5 fw-bold text-dark">$<?= number_format((float)$avgOrder, 2) ?></div>
            </div>
        </div>
    </div>

    <h2 class="admin-card-heading mb-3">Order History</h2>
    <?php if (!empty($userOrders)): ?>
        <div class="admin-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-3 py-2 text-muted small text-uppercase">Order ID</th>
                            <th class="py-2 text-muted small text-uppercase">Date</th>
                            <th class="py-2 text-muted small text-uppercase">Total</th>
                            <th class="py-2 text-muted small text-uppercase">Status</th>
                            <th class="pe-3 py-2 text-end text-muted small text-uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userOrders as $order): ?>
                            <tr class="border-bottom">
                                <td class="ps-3 py-2 fw-semibold" style="font-family: monospace;">#<?= $order['id'] ?></td>
                                <td class="py-2 small text-muted"><?= date('F d, Y', strtotime($order['created_at'])) ?></td>
                                <td class="py-2 fw-semibold text-dark">$<?= number_format((float)$order['total_amount'], 2) ?></td>
                                <td class="py-2">
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= $order['status'] ?></span>
                                </td>
                                <td class="pe-3 py-2 text-end">
                                    <a href="order_details.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;">
                                        View Items
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <p class="text-muted small">No order history found for this customer.</p>
    <?php endif; ?>
    <?php
}

// =========================================================================
// VIEW 6: ORDERS
// =========================================================================
elseif ($view == 'orders') {
    // 1. Gather status metrics and financial totals
    $statusCounts = [
        'All' => 0,
        'Pending' => 0,
        'Shipped' => 0,
        'Delivered' => 0,
        'Cancelled' => 0
    ];
    $totalOrderRevenue = 0.0;
    
    $stmtStats = $pdo->query("SELECT status, COUNT(*) as cnt, SUM(total_amount) as sum_amt FROM orders GROUP BY status");
    while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
        $st = $row['status'];
        $cnt = (int)$row['cnt'];
        $sum = (float)($row['sum_amt'] ?? 0);
        $statusCounts['All'] += $cnt;
        if (isset($statusCounts[$st])) {
            $statusCounts[$st] = $cnt;
        }
        if ($st !== 'Cancelled') {
            $totalOrderRevenue += $sum;
        }
    }
    
    $deliveredCount = $statusCounts['Delivered'] ?? 0;
    $pendingCount = $statusCounts['Pending'] ?? 0;
    $shippedCount = $statusCounts['Shipped'] ?? 0;
    $completionRate = ($statusCounts['All'] > 0) ? round(($deliveredCount / $statusCounts['All']) * 100) : 0;

    // 2. Query filtered orders with item counts
    $statusFilter = $_GET['status'] ?? 'All';
    $search = trim($_GET['search'] ?? '');
    
    $sql = "SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count FROM orders o";
    $where = [];
    $params = [];

    if ($statusFilter != 'All' && in_array($statusFilter, ['Pending', 'Shipped', 'Delivered', 'Cancelled'], true)) {
        $where[] = "o.status = ?";
        $params[] = $statusFilter;
    }

    if ($search !== '') {
        $where[] = "(o.customer_name LIKE ? OR o.address LIKE ? OR CAST(o.id AS CHAR) LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="admin-view-header">
        <div>
            <span class="admin-kicker">Fulfillment &amp; Logistics</span>
            <h1 class="admin-view-title">Order Management</h1>
            <p class="admin-view-subtitle">Track customer orders, fulfillment pipelines, and delivery records.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="export_orders.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download me-1"></i> Export Orders (CSV)
            </a>
        </div>
    </div>

    <!-- 1. Operational Telemetry Strip -->
    <div class="admin-card p-0 mb-4 overflow-hidden">
        <div class="row g-0">
            <!-- Col 1: Total Orders -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Total Orders</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;"><?= number_format($statusCounts['All']) ?> Orders</span>
                    <span class="text-muted small">Recorded</span>
                </div>
                <div class="text-muted small">
                    All lifetime transactions
                </div>
            </div>

            <!-- Col 2: Gross Realized Volume -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Gross Order Volume</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;">$<?= number_format($totalOrderRevenue, 2) ?></span>
                    <span class="text-success small fw-semibold">Net Active</span>
                </div>
                <div class="text-muted small">
                    Excludes cancelled orders
                </div>
            </div>

            <!-- Col 3: Pending Dispatch -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-sm-bottom-0">
                <div class="admin-kpi-label mb-1">Pending Fulfillment</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold fs-5 <?= $pendingCount > 0 ? 'text-warning-emphasis' : 'text-dark' ?>" style="font-variant-numeric: tabular-nums;"><?= $pendingCount ?> Orders</span>
                    <span class="text-muted small">In Queue</span>
                </div>
                <div class="small">
                    <?= $pendingCount > 0 ? '<span class="text-warning-emphasis fw-semibold"><i class="bi bi-clock-history me-1"></i>Requires dispatch</span>' : '<span class="text-success fw-semibold"><i class="bi bi-check2 me-1"></i>Queue clear</span>' ?>
                </div>
            </div>

            <!-- Col 4: Fulfillment Rate -->
            <div class="col-sm-6 col-xl-3 p-3 px-4">
                <div class="admin-kpi-label mb-1">Fulfillment Rate</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;"><?= $completionRate ?>%</span>
                    <span class="text-success small fw-semibold"><?= $deliveredCount ?> Delivered</span>
                </div>
                <div class="text-muted small">
                    <?= $shippedCount ?> in transit &bull; <?= $statusCounts['Cancelled'] ?> cancelled
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Search & Status Filter Bar -->
    <div class="admin-card p-3 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <!-- Search Form -->
            <form onsubmit="event.preventDefault(); loadView('orders&status=<?= urlencode($statusFilter) ?>&search=' + encodeURIComponent(this.search.value));" class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 380px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by customer, address, or ID..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn btn-sm btn-outline-secondary px-3">Search</button>
                <?php if ($search !== ''): ?>
                    <button type="button" onclick="loadView('orders&status=<?= urlencode($statusFilter) ?>')" class="btn btn-sm btn-link text-muted text-decoration-none">Clear</button>
                <?php endif; ?>
            </form>

            <!-- Status Tabs -->
            <div class="btn-group btn-group-sm" role="group" aria-label="Order Status Filter">
                <?php
                $statuses = ['All', 'Pending', 'Shipped', 'Delivered', 'Cancelled'];
                foreach ($statuses as $s):
                    $activeClass = ($statusFilter == $s) ? 'btn-dark' : 'btn-outline-secondary';
                    $countLabel = $statusCounts[$s] ?? 0;
                    $searchParam = ($search !== '') ? '&search=' . urlencode($search) : '';
                    $param = ($s == 'All') ? 'orders' . $searchParam : "orders&status=$s" . $searchParam;
                ?>
                    <button type="button" onclick="loadView('<?= $param ?>')" class="btn <?= $activeClass ?>">
                        <?= $s ?> <span class="badge <?= ($statusFilter == $s) ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary' ?> ms-1" style="font-size: 0.68rem;"><?= $countLabel ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 3. Orders Master Table -->
    <div class="admin-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom">
                    <tr>
                        <th class="ps-3 py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Order ID</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Customer &amp; Delivery Destination</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Date Placed</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Items</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Total Amount</th>
                        <th class="py-3 text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Fulfillment Status</th>
                        <th class="pe-3 py-3 text-end text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($allOrders)): ?>
                        <?php foreach ($allOrders as $order): 
                            $s = $order['status'];
                            $pillClass = match($s) {
                                'Delivered' => 'status-delivered',
                                'Shipped' => 'status-shipped',
                                'Pending' => 'status-pending',
                                default => 'status-cancelled'
                            };
                            $itemCount = (int)($order['item_count'] ?? 0);
                        ?>
                            <tr class="border-bottom">
                                <td class="ps-3 py-3 fw-semibold" style="font-family: monospace; font-size: 0.82rem;">
                                    #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td class="py-3">
                                    <div class="fw-semibold text-dark small"><?= htmlspecialchars($order['customer_name']) ?></div>
                                    <div class="text-muted d-flex align-items-center gap-1" style="font-size: 0.72rem; max-width: 260px;">
                                        <i class="bi bi-geo-alt text-secondary" style="font-size: 0.7rem;"></i>
                                        <span class="text-truncate"><?= htmlspecialchars($order['address']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="text-dark small"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($order['created_at'])) ?></div>
                                </td>
                                <td class="py-3">
                                    <span class="text-secondary small fw-medium">
                                        <?= $itemCount ?> <?= $itemCount === 1 ? 'item' : 'items' ?>
                                    </span>
                                </td>
                                <td class="py-3 fw-bold text-dark small" style="font-variant-numeric: tabular-nums;">
                                    $<?= number_format((float)$order['total_amount'], 2) ?>
                                </td>
                                <td class="py-3">
                                    <span class="admin-status-pill <?= $pillClass ?>">
                                        <?= $s ?>
                                    </span>
                                </td>
                                <td class="pe-3 py-3 text-end">
                                    <a href="order_details.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-3 fw-semibold" style="font-size: 0.78rem;">
                                        Manage <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div class="py-3">
                                    <i class="bi bi-inbox text-muted opacity-50 fs-2 d-block mb-2"></i>
                                    <div>No orders found matching the filter criteria.</div>
                                    <?php if ($statusFilter !== 'All' || $search !== ''): ?>
                                        <button type="button" onclick="loadView('orders')" class="btn btn-sm btn-link text-decoration-none mt-2">
                                            Reset Filters
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>
