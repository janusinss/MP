<?php
// admin/router.php
// AJAX View Renderer for Admin Dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Administrator session enforcement (Phase 3 & 7.2)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $rootPath = function_exists('get_app_root') ? get_app_root() : '/';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized: Administrator authentication required.', 'redirect' => $rootPath . 'login']);
        exit;
    }
    http_response_code(403);
    header("Location: " . $rootPath . "login");
    exit;
}

$view = $_GET['view'] ?? 'dashboard';

/**
 * Intelligent Multi-Token Search Builder for Admin Console
 * Supports:
 *  - ID numbers (raw: 4832, padded: 04832, prefixed: #4832, #04832, ORD-4832, SKU #0101)
 *  - Text columns (case-insensitive substring LIKE %term%)
 *  - Stripped prefixes (#, $, SKU, ORD, etc.)
 */
if (!function_exists('buildAdminSearchClause')) {
    function buildAdminSearchClause($search, $idColumn, $textColumns = [], $padLength = 5) {
        $search = trim($search);
        if ($search === '') {
            return ['', []];
        }

        $orConditions = [];
        $params = [];

        // 1. Text field searches with raw query
        foreach ($textColumns as $col) {
            $orConditions[] = "$col LIKE ?";
            $params[] = "%$search%";
        }

        // 2. ID string matching (raw integer text)
        $orConditions[] = "CAST($idColumn AS CHAR) LIKE ?";
        $params[] = "%$search%";

        // 3. ID string matching (zero-padded to standard length)
        $orConditions[] = "LPAD(CAST($idColumn AS CHAR), $padLength, '0') LIKE ?";
        $params[] = "%$search%";

        // 4. Digits extraction for prefixed formats (e.g. #04832, #101, SKU #0101, ORD-4832)
        $digitsOnly = preg_replace('/[^0-9]/', '', $search);
        if ($digitsOnly !== '') {
            $intVal = (int)ltrim($digitsOnly, '0');
            if ($intVal > 0) {
                $orConditions[] = "$idColumn = ?";
                $params[] = $intVal;

                $orConditions[] = "CAST($idColumn AS CHAR) LIKE ?";
                $params[] = "%$intVal%";

                $orConditions[] = "LPAD(CAST($idColumn AS CHAR), $padLength, '0') LIKE ?";
                $params[] = "%$digitsOnly%";
            }
        }

        // 5. Cleaned text query (stripping #, $, SKU, ORD, order, user prefixes)
        $cleanText = trim(preg_replace('/^(order|ord|sku|user|acc|account|review|rev)[\s\-_:#]*/i', '', $search));
        $cleanText = trim(preg_replace('/[#$:]/', '', $cleanText));
        if ($cleanText !== '' && $cleanText !== $search) {
            foreach ($textColumns as $col) {
                $orConditions[] = "$col LIKE ?";
                $params[] = "%$cleanText%";
            }
        }

        return ['(' . implode(' OR ', $orConditions) . ')', $params];
    }
}

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
        <div class="admin-view-header-main">
            <span class="admin-kicker">Store Operations &amp; Intelligence</span>
            <div class="admin-view-heading-group">
                <h1 class="admin-view-title">Analytics</h1>
            </div>
            <p class="admin-view-subtitle">Store sales velocity, fulfillment rates, and stock health.</p>
        </div>
        <div class="admin-view-header-actions">
            <button type="button" class="admin-header-btn admin-header-btn-icon" onclick="loadView('dashboard')" title="Refresh Analytics Data" aria-label="Refresh Data">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <a href="export_orders.php" class="admin-header-btn" title="Export Orders Report">
                <i class="bi bi-download"></i>
                <span>Export Report</span>
            </a>
        </div>
    </div>

    <!-- 1. Primary 4 KPI Cards -->
    <div class="row g-2 g-md-3 mb-4">
        <!-- Gross Revenue -->
        <div class="col-6 col-xl-3">
            <div class="admin-card h-100">
                <div class="admin-kpi-label">Gross Revenue</div>
                <div class="admin-kpi-value text-dark">₱<?= number_format($totalRevenue, 2) ?></div>
                <div class="admin-kpi-caption">
                    <span class="text-success fw-semibold">Net Sales</span> &bull; Checkouts
                </div>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="col-6 col-xl-3">
            <div class="admin-card interactive h-100" onclick="loadView('orders')" title="View all customer orders">
                <div class="admin-kpi-label">Total Orders</div>
                <div class="admin-kpi-value text-dark"><?= $totalOrderCount ?></div>
                <div class="admin-kpi-caption">
                    <span class="fw-semibold text-dark"><?= $fulfillmentRate ?>% Fulfilled</span>
                </div>
            </div>
        </div>

        <!-- Average Order Value -->
        <div class="col-6 col-xl-3">
            <div class="admin-card h-100">
                <div class="admin-kpi-label">Avg. Order Value</div>
                <div class="admin-kpi-value text-dark">₱<?= number_format($aov, 2) ?></div>
                <div class="admin-kpi-caption">
                    Mean basket value
                </div>
            </div>
        </div>

        <!-- Pending Orders -->
        <div class="col-6 col-xl-3">
            <div class="admin-card interactive h-100" onclick="loadView('orders&status=Pending')" title="Filter by pending orders">
                <div class="admin-kpi-label">Pending Orders</div>
                <div class="admin-kpi-value <?= $pendingOrders > 0 ? 'text-dark' : 'text-muted' ?>">
                    <?= $pendingOrders ?>
                </div>
                <div class="admin-kpi-caption">
                    <?php if ($pendingOrders > 0): ?>
                        <span class="text-danger fw-semibold"><?= $pendingOrders ?> orders</span> in pack
                    <?php else: ?>
                        <span class="text-success fw-semibold">All clear</span> &bull; 0 backlog
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
                        <span class="text-muted small fw-semibold">₱<?= number_format($totalCatRevenue, 2) ?> Tracked</span>
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
                                            ₱<?= number_format((float)$tc['cat_revenue'], 2) ?> &bull; <?= $pct ?>%
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
                            ₱<?= number_format($totalCatRevenue, 2) ?>
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
                    <!-- Desktop Table -->
                    <div class="table-responsive d-none d-md-block">
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

                    <!-- Mobile Touch Feed -->
                    <div class="d-md-none admin-touch-feed">
                        <?php foreach ($lowStockItems as $item):
                            $qty = (int)$item['stock_qty'];
                            $img = $item['image'] ?: 'default.jpg';
                        ?>
                            <div class="admin-touch-card">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="../assets/images/<?= htmlspecialchars($img) ?>" style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; flex-shrink: 0;" alt="thumb">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="fw-semibold text-dark text-truncate" style="font-size: 0.88rem;"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.74rem;">
                                            <span style="font-family: monospace;">#<?= str_pad($item['id'], 4, '0', STR_PAD_LEFT) ?></span> &bull; <?= htmlspecialchars($item['category']) ?>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 text-end">
                                        <?php if ($qty === 0): ?>
                                            <span class="admin-status-pill status-cancelled">Out of stock</span>
                                        <?php else: ?>
                                            <span class="admin-status-pill status-pending"><?= $qty ?> left</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="admin-touch-card-footer mt-2 pt-2">
                                    <a href="product_edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-secondary w-100 admin-touch-action-btn">
                                        Restock SKU
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                    <!-- Desktop Table -->
                    <div class="table-responsive d-none d-md-block">
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
                                            ₱<?= number_format((float)$ro['total_amount'], 2) ?>
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

                    <!-- Mobile Touch Feed -->
                    <div class="d-md-none admin-touch-feed">
                        <?php foreach ($recentOrders as $ro): 
                            $status = $ro['status'];
                            $pillClass = match($status) {
                                'Delivered' => 'status-delivered',
                                'Shipped' => 'status-shipped',
                                'Pending' => 'status-pending',
                                default => 'status-cancelled'
                            };
                        ?>
                            <div class="admin-touch-card">
                                <div class="admin-touch-card-header">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.75rem;">#<?= str_pad($ro['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                        <span class="fw-semibold text-dark text-truncate" style="max-width: 140px; font-size: 0.88rem;"><?= htmlspecialchars($ro['customer_name']) ?></span>
                                    </div>
                                    <span class="admin-status-pill <?= $pillClass ?>"><?= $status ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center my-2">
                                    <span class="text-muted small"><?= date('M d, Y', strtotime($ro['created_at'])) ?></span>
                                    <span class="fw-bold text-dark fs-6" style="font-variant-numeric: tabular-nums;">₱<?= number_format((float)$ro['total_amount'], 2) ?></span>
                                </div>
                                <div class="admin-touch-card-footer">
                                    <a href="order_details.php?order_id=<?= $ro['id'] ?>" class="btn btn-sm btn-outline-secondary w-100 admin-touch-action-btn d-flex align-items-center justify-content-center gap-1">
                                        <span>Manage Order</span> <i class="bi bi-chevron-right small"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                                        return 'Revenue: ₱' + Number(context.raw).toFixed(2);
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
                                    return currentMetric === 'revenue' ? '₱' + value : value;
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
    $sql = "SELECT p.* FROM products p";
    $where = [];
    $params = [];

    if ($search !== '') {
        list($searchClause, $searchParams) = buildAdminSearchClause($search, 'p.id', ['p.name', 'p.category', 'CAST(p.price AS CHAR)'], 4);
        if ($searchClause !== '') {
            $where[] = $searchClause;
            $params = array_merge($params, $searchParams);
        }
    }

    if ($categoryFilter !== 'All' && $categoryFilter !== '') {
        $where[] = "p.category = ?";
        $params[] = $categoryFilter;
    }

    if ($stockFilter === 'low') {
        $where[] = "p.stock_qty > 0 AND p.stock_qty < 5";
    } elseif ($stockFilter === 'out') {
        $where[] = "p.stock_qty = 0";
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="admin-view-header">
        <div class="admin-view-header-main">
            <span class="admin-kicker">Catalog &amp; Stock Control</span>
            <div class="admin-view-heading-group">
                <h1 class="admin-view-title">Product Inventory</h1>
                <span class="admin-count-badge"><?= $search !== '' ? number_format(count($products)) . ' Found' : number_format($totalSkus) . ' SKUs' ?></span>
            </div>
            <p class="admin-view-subtitle">Live catalog stock tracking, pricing, and product records.</p>
        </div>
        <div class="admin-view-header-actions">
            <button type="button" class="admin-header-btn admin-header-btn-icon" onclick="loadView('products')" title="Refresh Product Inventory" aria-label="Refresh Inventory">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <a href="product_add.php" class="admin-header-btn admin-header-btn-primary" title="Add New Product">
                <i class="bi bi-plus-circle"></i>
                <span>Add Product</span>
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
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;">₱<?= number_format($totalValuation, 2) ?></span>
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
    <div class="admin-card admin-toolbar-card mb-4">
        <!-- Mobile 1-Line Search & Filters Bar -->
        <div class="d-lg-none">
            <form onsubmit="event.preventDefault(); loadView('products<?= ($categoryFilter !== 'All') ? '&category=' . urlencode($categoryFilter) : '' ?><?= ($stockFilter !== 'All') ? '&stock=' . urlencode($stockFilter) : '' ?>&search=' + encodeURIComponent(this.search.value.trim()));" class="admin-search-filter-row">
                <div class="admin-search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="search" placeholder="Search product name, SKU #ID, aisle, price..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                </div>
                <button type="submit" class="admin-search-submit-btn" title="Search Catalog" aria-label="Search">
                    <i class="bi bi-search"></i>
                </button>
                <!-- Stock Dropdown (Mobile) -->
                <div class="dropdown">
                    <button class="btn admin-filter-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by Stock Status">
                        <span class="filter-text">Stock: <strong><?= ($stockFilter === 'All') ? 'All' : ($stockFilter === 'low' ? 'Low' : 'Out') ?></strong></span>
                        <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown-menu shadow">
                        <li class="admin-dropdown-header">Inventory Status</li>
                        <?php
                        $stockOptions = [
                            'All' => ['label' => 'All Stock', 'count' => $scopedTotal],
                            'low' => ['label' => 'Low Stock (< 5)', 'count' => $scopedLowStock],
                            'out' => ['label' => 'Out of Stock (0)', 'count' => $scopedOutOfStock]
                        ];
                        foreach ($stockOptions as $stKey => $stData):
                            $isActive = ($stockFilter === $stKey);
                            $param = "products&stock=$stKey" . ($categoryFilter !== 'All' ? "&category=" . urlencode($categoryFilter) : '') . ($search !== '' ? "&search=" . urlencode($search) : '');
                        ?>
                            <li>
                                <button type="button" onclick="loadView('<?= $param ?>')" class="admin-dropdown-item <?= $isActive ? 'active' : '' ?>">
                                    <span class="item-label">
                                        <?php if ($isActive): ?><i class="bi bi-check2 text-success"></i><?php endif; ?>
                                        <?= $stData['label'] ?>
                                    </span>
                                    <span class="badge"><?= $stData['count'] ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <!-- Aisle Dropdown (Mobile) -->
                <div class="dropdown">
                    <button class="btn admin-filter-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by Aisle / Category">
                        <span class="filter-text">Aisle: <strong><?= htmlspecialchars($categoryFilter) ?></strong></span>
                        <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown-menu shadow">
                        <li class="admin-dropdown-header">Store Aisles</li>
                        <li>
                            <button type="button" class="admin-dropdown-item <?= ($categoryFilter === 'All') ? 'active' : '' ?>" onclick="selectAisle('All')">
                                <span class="item-label">
                                    <?php if ($categoryFilter === 'All'): ?><i class="bi bi-check2 text-success"></i><?php endif; ?>
                                    All Categories
                                </span>
                                <span class="badge"><?= $totalSkus ?></span>
                            </button>
                        </li>
                        <li class="admin-dropdown-divider"></li>
                        <?php foreach ($categoryCounts as $cName => $cCount): 
                            $isCatActive = ($categoryFilter === $cName);
                        ?>
                            <li>
                                <button type="button" class="admin-dropdown-item <?= $isCatActive ? 'active' : '' ?>" onclick="selectAisle('<?= htmlspecialchars(addslashes($cName)) ?>')">
                                    <span class="item-label">
                                        <?php if ($isCatActive): ?><i class="bi bi-check2 text-success"></i><?php endif; ?>
                                        <?= htmlspecialchars($cName) ?>
                                    </span>
                                    <span class="badge"><?= $cCount ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </form>
            <?php if ($search !== '' || $categoryFilter !== 'All'): ?>
                <div class="mt-2 text-end d-flex justify-content-end gap-2">
                    <?php if ($search !== ''): ?>
                        <button type="button" onclick="loadView('products<?= ($categoryFilter !== 'All') ? '&category=' . urlencode($categoryFilter) : '' ?><?= ($stockFilter !== 'All') ? '&stock=' . urlencode($stockFilter) : '' ?>')" class="admin-search-clear-btn">
                            <i class="bi bi-x-circle me-1"></i>Clear search
                        </button>
                    <?php endif; ?>
                    <?php if ($categoryFilter !== 'All'): ?>
                        <button type="button" onclick="selectAisle('All')" class="admin-search-clear-btn text-muted">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset aisle
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Desktop Search & Expanded Chip Tabs Bar -->
        <div class="d-none d-lg-flex align-items-center justify-content-between gap-3 admin-desktop-toolbar">
            <div class="d-flex align-items-center gap-2">
                <div class="admin-chip-rail" role="group" aria-label="Stock Filter">
                    <?php
                    foreach ($stockOptions as $stKey => $stData):
                        $isActive = ($stockFilter === $stKey);
                        $activeClass = $isActive ? 'active' : '';
                        $param = "products&stock=$stKey" . ($categoryFilter !== 'All' ? "&category=" . urlencode($categoryFilter) : '') . ($search !== '' ? "&search=" . urlencode($search) : '');
                    ?>
                        <button type="button" onclick="loadView('<?= $param ?>')" class="admin-chip-btn <?= $activeClass ?>">
                            <span><?= $stData['label'] ?></span> <span class="badge <?= $isActive ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary' ?> ms-1" style="font-size: 0.68rem;"><?= $stData['count'] ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="dropdown">
                    <button class="btn admin-filter-dropdown-btn dropdown-toggle <?= ($categoryFilter !== 'All') ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="filter-text">Aisle: <strong><?= htmlspecialchars($categoryFilter) ?></strong></span>
                        <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown-menu shadow">
                        <li class="admin-dropdown-header">Store Aisles</li>
                        <li>
                            <button type="button" class="admin-dropdown-item <?= ($categoryFilter === 'All') ? 'active' : '' ?>" onclick="selectAisle('All')">
                                <span class="item-label">
                                    <?php if ($categoryFilter === 'All'): ?><i class="bi bi-check2 text-success"></i><?php endif; ?>
                                    All Categories
                                </span>
                                <span class="badge"><?= $totalSkus ?></span>
                            </button>
                        </li>
                        <li class="admin-dropdown-divider"></li>
                        <?php foreach ($categoryCounts as $cName => $cCount): 
                            $isCatActive = ($categoryFilter === $cName);
                        ?>
                            <li>
                                <button type="button" class="admin-dropdown-item <?= $isCatActive ? 'active' : '' ?>" onclick="selectAisle('<?= htmlspecialchars(addslashes($cName)) ?>')">
                                    <span class="item-label">
                                        <?php if ($isCatActive): ?><i class="bi bi-check2 text-success"></i><?php endif; ?>
                                        <?= htmlspecialchars($cName) ?>
                                    </span>
                                    <span class="badge"><?= $cCount ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <form onsubmit="event.preventDefault(); loadView('products<?= ($categoryFilter !== 'All') ? '&category=' . urlencode($categoryFilter) : '' ?><?= ($stockFilter !== 'All') ? '&stock=' . urlencode($stockFilter) : '' ?>&search=' + encodeURIComponent(this.search.value.trim()));" class="admin-desktop-search-form">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search product name, SKU #ID, aisle, price..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                </div>
                <button type="submit" class="admin-desktop-search-btn">Search</button>
                <?php if ($search !== ''): ?>
                    <button type="button" onclick="loadView('products<?= ($categoryFilter !== 'All') ? '&category=' . urlencode($categoryFilter) : '' ?><?= ($stockFilter !== 'All') ? '&stock=' . urlencode($stockFilter) : '' ?>')" class="admin-desktop-clear-btn" title="Clear search">Clear</button>
                <?php endif; ?>
            </form>
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
        <!-- Desktop Table View -->
        <div class="table-responsive d-none d-md-block">
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
                                    <span class="fw-semibold text-dark small" style="font-variant-numeric: tabular-nums;">₱<?= number_format((float)$p['price'], 2) ?></span>
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

        <!-- Mobile Touch Card Feed -->
        <div class="d-md-none admin-touch-feed p-2 p-sm-3">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $p): 
                    $sq = (int)$p['stock_qty'];
                    if ($sq === 0) {
                        $pill = '<span class="admin-status-pill status-cancelled">Out of Stock</span>';
                    } elseif ($sq < 5) {
                        $pill = '<span class="admin-status-pill status-pending">' . $sq . ' Left (Low)</span>';
                    } else {
                        $pill = '<span class="admin-status-pill status-delivered">' . $sq . ' Units</span>';
                    }
                ?>
                    <div class="admin-touch-card">
                        <div class="d-flex align-items-start gap-3">
                            <img src="../assets/images/<?= $p['image'] ?: 'default.jpg' ?>" style="width: 52px; height: 52px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; flex-shrink: 0;" alt="thumb">
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.92rem;"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="fw-bold text-dark fs-6" style="font-variant-numeric: tabular-nums;">₱<?= number_format((float)$p['price'], 2) ?></div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.72rem;">#<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?></span>
                                    <span class="text-muted small"><?= htmlspecialchars($p['category']) ?></span>
                                </div>
                                <div class="mt-2">
                                    <?= $pill ?>
                                </div>
                            </div>
                        </div>
                        <div class="admin-touch-card-footer mt-3">
                            <a href="product_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary flex-grow-1 admin-touch-action-btn d-flex align-items-center justify-content-center gap-1">
                                <i class="bi bi-pencil me-1"></i> <span>Edit Product</span>
                            </a>
                            <a href="actions/product_delete.php?id=<?= $p['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-sm btn-outline-danger admin-touch-action-btn d-flex align-items-center justify-content-center px-3" onclick="return confirm('Delete <?= htmlspecialchars(addslashes($p['name'])) ?>?');" title="Delete Product" aria-label="Delete">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-basket text-muted opacity-50 fs-2 d-block mb-2"></i>
                    <div>No products found matching criteria.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// =========================================================================
// VIEW 3: USERS (REGISTERED CUSTOMERS)
// =========================================================================
elseif ($view == 'users') {
    // 1. Telemetry across full customer base
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    $stmtBuyerStats = $pdo->query("
        SELECT 
            COUNT(DISTINCT user_id) as active_buyers,
            COALESCE(SUM(total_amount), 0) as total_spend,
            COUNT(*) as total_orders
        FROM orders 
        WHERE status != 'Cancelled' AND user_id IS NOT NULL
    ");
    $buyerStats = $stmtBuyerStats->fetch(PDO::FETCH_ASSOC) ?: [];
    $activeBuyers = (int)($buyerStats['active_buyers'] ?? 0);
    $totalSpend = (float)($buyerStats['total_spend'] ?? 0);
    $totalOrdersCount = (int)($buyerStats['total_orders'] ?? 0);
    $avgSpendPerBuyer = $activeBuyers > 0 ? $totalSpend / $activeBuyers : 0.0;
    $buyerConversionRate = $totalUsers > 0 ? round(($activeBuyers / $totalUsers) * 100, 1) : 0;

    // 2. Query filters & search
    $search = trim($_GET['search'] ?? '');
    $filter = trim($_GET['filter'] ?? 'all'); // 'all', 'buyers', 'inactive'

    $where = [];
    $params = [];

    if ($search !== '') {
        list($searchClause, $searchParams) = buildAdminSearchClause($search, 'u.id', ['u.full_name', 'u.email', 'u.address', 'u.role'], 4);
        if ($searchClause !== '') {
            $where[] = $searchClause;
            $params = array_merge($params, $searchParams);
        }
    }

    $sql = "
        SELECT 
            u.*,
            COUNT(CASE WHEN o.status != 'Cancelled' THEN o.id END) as valid_order_count,
            COALESCE(SUM(CASE WHEN o.status != 'Cancelled' THEN o.total_amount ELSE 0 END), 0) as lifetime_spent,
            MAX(o.created_at) as latest_order_date
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
    ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " GROUP BY u.id";

    if ($filter === 'buyers') {
        $sql .= " HAVING valid_order_count > 0";
    } elseif ($filter === 'inactive') {
        $sql .= " HAVING valid_order_count = 0";
    }

    $sql .= " ORDER BY u.created_at DESC";

    $stmtUsers = $pdo->prepare($sql);
    $stmtUsers->execute($params);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="admin-view-header">
        <div class="admin-view-header-main">
            <span class="admin-kicker">Client Accounts &amp; Profiles</span>
            <div class="admin-view-heading-group">
                <h1 class="admin-view-title">Customers</h1>
                <span class="admin-count-badge"><?= $search !== '' ? number_format(count($users)) . ' Found' : number_format($totalUsers) . ' Profiles' ?></span>
            </div>
            <p class="admin-view-subtitle">Verified customer accounts, transaction histories, and lifetime store value.</p>
        </div>
        <div class="admin-view-header-actions">
            <button type="button" class="admin-header-btn" onclick="loadView('users')" title="Refresh Customer Directory" aria-label="Refresh Customer Directory">
                <i class="bi bi-arrow-clockwise"></i>
                <span>Refresh Directory</span>
            </button>
        </div>
    </div>

    <!-- 1. Operational Telemetry Strip -->
    <div class="admin-card p-0 mb-4 overflow-hidden">
        <div class="row g-0">
            <!-- Col 1: Total Registered Accounts -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Total Accounts</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;"><?= number_format($totalUsers) ?> Profiles</span>
                    <span class="text-muted small">Registered</span>
                </div>
                <div class="text-muted small">
                    <span class="text-dark fw-semibold"><?= number_format($totalUsers) ?></span> client records
                </div>
            </div>

            <!-- Col 2: Active Buyers -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Active Buyers</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;"><?= number_format($activeBuyers) ?> Buyers</span>
                    <span class="text-muted small"><?= $buyerConversionRate ?>% Rate</span>
                </div>
                <div class="text-muted small">
                    <span class="text-success fw-semibold"><i class="bi bi-bag-check me-1"></i>Purchased</span> &bull; Placed &ge;1 order
                </div>
            </div>

            <!-- Col 3: Customer Spend -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-sm-bottom-0">
                <div class="admin-kpi-label mb-1">Customer Spend</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-success fs-5" style="font-variant-numeric: tabular-nums;">₱<?= number_format($totalSpend, 2) ?></span>
                    <span class="text-muted small">Gross Total</span>
                </div>
                <div class="text-muted small">
                    <span class="text-dark fw-semibold"><?= number_format($totalOrdersCount) ?></span> orders fulfilled
                </div>
            </div>

            <!-- Col 4: Average Spend per Buyer -->
            <div class="col-sm-6 col-xl-3 p-3 px-4">
                <div class="admin-kpi-label mb-1">Avg. Value / Buyer</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;">₱<?= number_format($avgSpendPerBuyer, 2) ?></span>
                    <span class="text-muted small">Per Buyer</span>
                </div>
                <div class="text-muted small">
                    <span class="text-secondary fw-semibold">Lifetime AOV</span> &bull; Active accounts
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Search & Filter Bar -->
    <div class="admin-card admin-toolbar-card mb-4">
        <!-- Mobile 1-Line Search & Filter Bar -->
        <div class="d-md-none">
            <?php
            $filterTabs = [
                'all' => ['label' => 'All Accounts', 'count' => $totalUsers],
                'buyers' => ['label' => 'Active Buyers', 'count' => $activeBuyers],
                'inactive' => ['label' => 'No Orders Yet', 'count' => max(0, $totalUsers - $activeBuyers)]
            ];
            ?>
            <form onsubmit="event.preventDefault(); loadView('users<?= ($filter !== 'all') ? '&filter=' . urlencode($filter) : '' ?>&search=' + encodeURIComponent(this.search.value.trim()));" class="admin-search-filter-row">
                <div class="admin-search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="search" placeholder="Search customer name, email, Account #ID, address..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                </div>
                <button type="submit" class="admin-search-submit-btn" title="Search Accounts" aria-label="Search">
                    <i class="bi bi-search"></i>
                </button>
                <div class="dropdown">
                    <button class="btn admin-filter-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by Account Type">
                        <span class="filter-text"><?= htmlspecialchars($filterTabs[$filter]['label'] ?? 'All Accounts') ?></span>
                        <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown-menu shadow">
                        <li class="admin-dropdown-header">Customer Segments</li>
                        <?php foreach ($filterTabs as $fKey => $fData):
                            $isActive = ($filter === $fKey);
                            $param = "users&filter=$fKey" . ($search !== '' ? "&search=" . urlencode($search) : '');
                        ?>
                            <li>
                                <button type="button" onclick="loadView('<?= $param ?>')" class="admin-dropdown-item <?= $isActive ? 'active' : '' ?>">
                                    <span class="item-label">
                                        <?php if ($isActive): ?><i class="bi bi-check2 text-success"></i><?php endif; ?>
                                        <?= $fData['label'] ?>
                                    </span>
                                    <span class="badge"><?= $fData['count'] ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </form>
            <?php if ($search !== ''): ?>
                <div class="mt-2 text-end">
                    <button type="button" onclick="loadView('users<?= ($filter !== 'all') ? '&filter=' . urlencode($filter) : '' ?>')" class="admin-search-clear-btn">
                        <i class="bi bi-x-circle me-1"></i>Clear search "<?= htmlspecialchars($search) ?>"
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Desktop Search & Expanded Chip Tabs Bar -->
        <div class="d-none d-md-flex align-items-center justify-content-between gap-3 admin-desktop-toolbar">
            <div class="admin-chip-rail" role="group" aria-label="Customer Filter">
                <?php foreach ($filterTabs as $fKey => $fData):
                    $isActive = ($filter === $fKey);
                    $activeClass = $isActive ? 'active' : '';
                    $param = "users&filter=$fKey" . ($search !== '' ? "&search=" . urlencode($search) : '');
                ?>
                    <button type="button" onclick="loadView('<?= $param ?>')" class="admin-chip-btn <?= $activeClass ?>">
                        <span><?= $fData['label'] ?></span> <span class="badge <?= $isActive ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary' ?> ms-1" style="font-size: 0.68rem;"><?= $fData['count'] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <form onsubmit="event.preventDefault(); loadView('users<?= ($filter !== 'all') ? '&filter=' . urlencode($filter) : '' ?>&search=' + encodeURIComponent(this.search.value.trim()));" class="admin-desktop-search-form">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search customer name, email, Account #ID, address..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                </div>

                <button type="submit" class="admin-desktop-search-btn">Search</button>
                <?php if ($search !== ''): ?>
                    <button type="button" onclick="loadView('users<?= ($filter !== 'all') ? '&filter=' . urlencode($filter) : '' ?>')" class="admin-desktop-clear-btn" title="Clear search">Clear</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 3. Customer Directory Data Table -->
    <div class="admin-card p-0 overflow-hidden">
        <!-- Desktop Table View -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom">
                    <tr>
                        <th class="ps-3 py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Customer Profile</th>
                        <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Account ID</th>
                        <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Joined Date</th>
                        <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Orders</th>
                        <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Lifetime Spend</th>
                        <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Status</th>
                        <th class="pe-3 py-3 text-end text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u): 
                            $orderCount = (int)($u['valid_order_count'] ?? 0);
                            $spend = (float)($u['lifetime_spent'] ?? 0);
                            $isBuyer = ($orderCount > 0);
                        ?>
                            <tr class="border-bottom">
                                <td class="ps-3 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="admin-avatar-initial" style="width: 38px; height: 38px; font-size: 0.95rem; flex-shrink: 0; background-color: #0f172a; color: #ffffff; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">
                                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold text-dark text-truncate small" title="<?= htmlspecialchars($u['full_name']) ?>">
                                                <?= htmlspecialchars($u['full_name']) ?>
                                            </div>
                                            <div class="text-muted text-truncate" style="font-size: 0.75rem;" title="<?= htmlspecialchars($u['email']) ?>">
                                                <?= htmlspecialchars($u['email']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-light text-dark border" style="font-family: monospace; font-size: 0.75rem;">
                                        #<?= str_pad($u['id'], 4, '0', STR_PAD_LEFT) ?>
                                    </span>
                                </td>
                                <td class="py-3 text-muted small">
                                    <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="py-3">
                                    <?php if ($orderCount > 0): ?>
                                        <span class="badge bg-dark-subtle text-dark border fw-semibold" style="font-size: 0.75rem;">
                                            <?= $orderCount ?> <?= $orderCount === 1 ? 'Order' : 'Orders' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">0 Orders</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <span class="fw-semibold <?= $spend > 0 ? 'text-success' : 'text-muted' ?>" style="font-variant-numeric: tabular-nums; font-size: 0.88rem;">
                                        ₱<?= number_format($spend, 2) ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <?php if ($isBuyer): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-check-circle me-1"></i>Active Buyer
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="font-size: 0.72rem;">
                                            New Member
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 py-3 text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" onclick="loadView('customer_details&id=<?= $u['id'] ?>')" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.78rem;" title="View Customer Profile &amp; Orders">
                                            <i class="bi bi-eye"></i> <span>View</span>
                                        </button>
                                        <a href="actions/user_delete.php?id=<?= $u['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.78rem;" onclick="return confirm('Delete user <?= htmlspecialchars(addslashes($u['full_name'])) ?>? This cannot be undone.');" title="Delete Account">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div class="py-4">
                                    <i class="bi bi-people text-muted opacity-50 fs-2 d-block mb-2"></i>
                                    <div class="fw-semibold text-dark">No customers found</div>
                                    <div class="small text-muted mb-2">No registered customer accounts match the current filter or search criteria.</div>
                                    <?php if ($filter !== 'all' || $search !== ''): ?>
                                        <button type="button" onclick="loadView('users')" class="btn btn-sm btn-link text-decoration-none">
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

        <!-- Mobile Touch Card Feed -->
        <div class="d-md-none admin-touch-feed p-2 p-sm-3">
            <?php if (!empty($users)): ?>
                <?php foreach ($users as $u): 
                    $orderCount = (int)($u['valid_order_count'] ?? 0);
                    $spend = (float)($u['lifetime_spent'] ?? 0);
                    $isBuyer = ($orderCount > 0);
                ?>
                    <div class="admin-touch-card">
                        <div class="admin-touch-card-header">
                            <div class="d-flex align-items-center gap-2">
                                <div class="admin-avatar-initial" style="width: 36px; height: 36px; font-size: 0.9rem; background-color: #0f172a; color: #ffffff; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">
                                    <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 0.92rem;"><?= htmlspecialchars($u['full_name']) ?></div>
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;">#<?= str_pad($u['id'], 4, '0', STR_PAD_LEFT) ?></span>
                                </div>
                            </div>
                            <div>
                                <?php if ($isBuyer): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold px-2 py-1" style="font-size: 0.72rem;">
                                        Active Buyer
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="font-size: 0.72rem;">
                                        New Member
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="admin-touch-card-body">
                            <div class="text-muted small mb-2 text-truncate">
                                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($u['email']) ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center small py-1 bg-light rounded px-2">
                                <span class="text-muted"><?= $orderCount ?> <?= $orderCount === 1 ? 'Order' : 'Orders' ?> &bull; Joined <?= date('M Y', strtotime($u['created_at'])) ?></span>
                                <span class="fw-bold <?= $spend > 0 ? 'text-success' : 'text-muted' ?>" style="font-variant-numeric: tabular-nums;">
                                    ₱<?= number_format($spend, 2) ?>
                                </span>
                            </div>
                        </div>
                        <div class="admin-touch-card-footer">
                            <button type="button" onclick="loadView('customer_details&id=<?= $u['id'] ?>')" class="btn btn-sm btn-outline-secondary flex-grow-1 admin-touch-action-btn d-flex align-items-center justify-content-center gap-1">
                                <i class="bi bi-eye me-1"></i> <span>View Profile &amp; Orders</span>
                            </button>
                            <a href="actions/user_delete.php?id=<?= $u['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-sm btn-outline-danger admin-touch-action-btn d-flex align-items-center justify-content-center px-3" onclick="return confirm('Delete user <?= htmlspecialchars(addslashes($u['full_name'])) ?>?');" title="Delete Account" aria-label="Delete">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people text-muted opacity-50 fs-2 d-block mb-2"></i>
                    <div>No customers found matching criteria.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// =========================================================================
// VIEW 4: REVIEWS
// =========================================================================
elseif ($view == 'reviews') {
    // 1. Overall telemetry across all reviews
    $stmtStats = $pdo->query("
        SELECT 
            COUNT(*) as total_reviews,
            COALESCE(AVG(rating), 0) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as low_star
        FROM reviews
    ");
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalReviews = (int)($stats['total_reviews'] ?? 0);
    $avgRating = $totalReviews > 0 ? number_format((float)$stats['avg_rating'], 1) : '5.0';
    $fiveStarCount = (int)($stats['five_star'] ?? 0);
    $fourStarCount = (int)($stats['four_star'] ?? 0);
    $threeStarCount = (int)($stats['three_star'] ?? 0);
    $lowStarCount = (int)($stats['low_star'] ?? 0);
    $fiveStarRatio = $totalReviews > 0 ? round(($fiveStarCount / $totalReviews) * 100) : 100;

    // 2. Query filters, search & view mode
    $search = trim($_GET['search'] ?? '');
    $ratingFilter = trim($_GET['rating'] ?? 'all'); // 'all', '5', '4', '3', 'low'
    $mode = trim($_GET['mode'] ?? 'table'); // 'table' or 'grid'

    $where = [];
    $params = [];

    if ($search !== '') {
        list($searchClause, $searchParams) = buildAdminSearchClause($search, 'r.id', ['u.full_name', 'u.email', 'p.name', 'r.comment', 'CAST(r.rating AS CHAR)'], 4);
        if ($searchClause !== '') {
            $where[] = $searchClause;
            $params = array_merge($params, $searchParams);
        }
    }

    if ($ratingFilter === '5') {
        $where[] = "r.rating = 5";
    } elseif ($ratingFilter === '4') {
        $where[] = "r.rating = 4";
    } elseif ($ratingFilter === '3') {
        $where[] = "r.rating = 3";
    } elseif ($ratingFilter === 'low') {
        $where[] = "r.rating <= 2";
    }

    $sql = "
        SELECT r.*, u.full_name, u.email as user_email, p.name as product_name, p.image as product_image 
        FROM reviews r 
        LEFT JOIN users u ON r.user_id = u.id 
        LEFT JOIN products p ON r.product_id = p.id
    ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $filteredCount = count($reviews);
    ?>
    <div class="admin-view-header">
        <div class="admin-view-header-main">
            <span class="admin-kicker">Shopper Feedback &amp; Ratings</span>
            <div class="admin-view-heading-group">
                <h1 class="admin-view-title">Reviews</h1>
                <span class="admin-count-badge"><?= $search !== '' ? number_format($filteredCount) . ' Found' : number_format($totalReviews) . ' Reviews' ?></span>
            </div>
            <p class="admin-view-subtitle">Verified customer product feedback, star ratings, and sentiment records.</p>
        </div>
        <div class="admin-view-header-actions">
            <div class="admin-view-mode-toggle" role="group" aria-label="Display Mode">
                <?php
                $tableParam = "reviews&mode=table" . ($ratingFilter !== 'all' ? "&rating=$ratingFilter" : '') . ($search !== '' ? "&search=" . urlencode($search) : '');
                $gridParam = "reviews&mode=grid" . ($ratingFilter !== 'all' ? "&rating=$ratingFilter" : '') . ($search !== '' ? "&search=" . urlencode($search) : '');
                ?>
                <button type="button" onclick="loadView('<?= $tableParam ?>')" class="admin-mode-btn <?= $mode === 'table' ? 'active' : '' ?>" title="Table View" aria-label="Table View">
                    <i class="bi bi-table"></i> Table
                </button>
                <button type="button" onclick="loadView('<?= $gridParam ?>')" class="admin-mode-btn <?= $mode === 'grid' ? 'active' : '' ?>" title="Card Gallery View" aria-label="Card Gallery View">
                    <i class="bi bi-grid-fill"></i> Cards
                </button>
            </div>
            <button type="button" class="admin-header-btn admin-header-btn-icon" onclick="loadView('reviews')" title="Refresh Reviews" aria-label="Refresh Reviews">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- 1. Operational Telemetry Strip -->
    <div class="admin-card p-0 mb-4 overflow-hidden">
        <div class="row g-0">
            <!-- Col 1: Total Reviews -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Total Reviews</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;"><?= number_format($totalReviews) ?> Submissions</span>
                    <span class="text-muted small">Verified</span>
                </div>
                <div class="text-muted small">
                    <span class="text-dark fw-semibold"><?= number_format($totalReviews) ?></span> customer entries
                </div>
            </div>

            <!-- Col 2: Average Score -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Average Rating</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;">
                        <i class="bi bi-star-fill text-warning me-1"></i><?= $avgRating ?> <span class="text-muted fs-6 fw-normal">/ 5.0</span>
                    </span>
                    <span class="text-muted small">Storewide</span>
                </div>
                <div class="text-muted small">
                    <span class="text-success fw-semibold">Overall Sentiment</span> &bull; Verified orders
                </div>
            </div>

            <!-- Col 3: 5-Star Reviews -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-sm-bottom-0">
                <div class="admin-kpi-label mb-1">5-Star Feedback</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-success fs-5" style="font-variant-numeric: tabular-nums;"><?= number_format($fiveStarCount) ?> Praise</span>
                    <span class="text-muted small"><?= $fiveStarRatio ?>% Ratio</span>
                </div>
                <div class="text-muted small">
                    <span class="text-success fw-semibold"><i class="bi bi-hand-thumbs-up me-1"></i>Top Rated</span> &bull; Perfect scores
                </div>
            </div>

            <!-- Col 4: Critical Attention -->
            <div class="col-sm-6 col-xl-3 p-3 px-4">
                <div class="admin-kpi-label mb-1">Flagged / Low Stars</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold <?= $lowStarCount > 0 ? 'text-danger' : 'text-dark' ?> fs-5" style="font-variant-numeric: tabular-nums;"><?= number_format($lowStarCount) ?> Ratings</span>
                    <span class="text-muted small">&le; 2 Stars</span>
                </div>
                <div class="text-muted small">
                    <?php if ($lowStarCount > 0): ?>
                        <span class="text-danger fw-semibold"><i class="bi bi-exclamation-circle me-1"></i>Needs follow-up</span>
                    <?php else: ?>
                        <span class="text-success fw-semibold"><i class="bi bi-check2-circle me-1"></i>Zero critical issues</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Search & Rating Filter Bar -->
    <div class="admin-card admin-toolbar-card mb-4">
        <!-- Mobile 1-Line Search & Filter Bar -->
        <div class="d-md-none">
            <?php
            $ratingTabs = [
                'all' => ['label' => 'All Reviews', 'count' => $totalReviews],
                '5' => ['label' => '5 Stars', 'count' => $fiveStarCount],
                '4' => ['label' => '4 Stars', 'count' => $fourStarCount],
                '3' => ['label' => '3 Stars', 'count' => $threeStarCount],
                'low' => ['label' => '1-2 Stars', 'count' => $lowStarCount]
            ];
            ?>
            <form onsubmit="event.preventDefault(); loadView('reviews<?= ($ratingFilter !== 'all') ? '&rating=' . urlencode($ratingFilter) : '' ?><?= ($mode !== 'table') ? '&mode=' . urlencode($mode) : '' ?>&search=' + encodeURIComponent(this.search.value.trim()));" class="admin-search-filter-row">
                <div class="admin-search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="search" placeholder="Search customer name, product, comment, Review #ID..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                </div>
                <button type="submit" class="admin-search-submit-btn" title="Search Reviews" aria-label="Search">
                    <i class="bi bi-search"></i>
                </button>
                <div class="dropdown">
                    <button class="btn admin-filter-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by Rating">
                        <span class="filter-text"><?= htmlspecialchars($ratingTabs[$ratingFilter]['label'] ?? 'All Reviews') ?></span>
                        <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown-menu shadow">
                        <li class="admin-dropdown-header">Filter by Star Rating</li>
                        <?php foreach ($ratingTabs as $rKey => $rData):
                            $isActive = ($ratingFilter === $rKey);
                            $param = "reviews&rating=$rKey" . ($mode !== 'table' ? "&mode=$mode" : '') . ($search !== '' ? "&search=" . urlencode($search) : '');
                        ?>
                            <li>
                                <button type="button" onclick="loadView('<?= $param ?>')" class="admin-dropdown-item <?= $isActive ? 'active' : '' ?>">
                                    <span class="item-label">
                                        <?php if ($isActive): ?><i class="bi bi-check2 text-success"></i><?php endif; ?>
                                        <?= $rData['label'] ?>
                                    </span>
                                    <span class="badge"><?= $rData['count'] ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </form>
            <?php if ($search !== ''): ?>
                <div class="mt-2 text-end">
                    <button type="button" onclick="loadView('reviews<?= ($ratingFilter !== 'all') ? '&rating=' . urlencode($ratingFilter) : '' ?><?= ($mode !== 'table') ? '&mode=' . urlencode($mode) : '' ?>')" class="admin-search-clear-btn">
                        <i class="bi bi-x-circle me-1"></i>Clear search "<?= htmlspecialchars($search) ?>"
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Desktop Search & Expanded Chip Tabs Bar -->
        <div class="d-none d-md-flex align-items-center justify-content-between gap-3 admin-desktop-toolbar">
            <div class="admin-chip-rail" role="group" aria-label="Rating Filter">
                <?php foreach ($ratingTabs as $rKey => $rData):
                    $isActive = ($ratingFilter === $rKey);
                    $activeClass = $isActive ? 'active' : '';
                    $param = "reviews&rating=$rKey" . ($mode !== 'table' ? "&mode=$mode" : '') . ($search !== '' ? "&search=" . urlencode($search) : '');
                ?>
                    <button type="button" onclick="loadView('<?= $param ?>')" class="admin-chip-btn <?= $activeClass ?>">
                        <span><?= $rData['label'] ?></span> <span class="badge <?= $isActive ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary' ?> ms-1" style="font-size: 0.68rem;"><?= $rData['count'] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <form onsubmit="event.preventDefault(); loadView('reviews<?= ($ratingFilter !== 'all') ? '&rating=' . urlencode($ratingFilter) : '' ?><?= ($mode !== 'table') ? '&mode=' . urlencode($mode) : '' ?>&search=' + encodeURIComponent(this.search.value.trim()));" class="admin-desktop-search-form">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search customer name, product, comment, Review #ID..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                </div>

                <button type="submit" class="admin-desktop-search-btn">Search</button>
                <?php if ($search !== ''): ?>
                    <button type="button" onclick="loadView('reviews<?= ($ratingFilter !== 'all') ? '&rating=' . urlencode($ratingFilter) : '' ?><?= ($mode !== 'table') ? '&mode=' . urlencode($mode) : '' ?>')" class="admin-desktop-clear-btn" title="Clear search">Clear</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 3. Reviews Display: Table or Card Gallery -->
    <?php if ($mode === 'grid'): ?>
        <!-- Card Gallery View -->
        <div class="row g-3">
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="admin-card p-3 d-flex flex-column justify-content-between h-100">
                            <div>
                                <!-- Review Header: Customer & Rating -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="admin-avatar-initial" style="width: 36px; height: 36px; font-size: 0.85rem; flex-shrink: 0; background-color: #0f172a; color: #ffffff; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">
                                            <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold text-dark text-truncate small" title="<?= htmlspecialchars($r['full_name']) ?>"><?= htmlspecialchars($r['full_name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 text-warning" style="color: #f59e0b !important;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?= $i <= $r['rating'] ? 'bi-star-fill' : 'bi-star' ?>" style="font-size: 0.8rem; color: <?= $i <= $r['rating'] ? '#f59e0b' : '#cbd5e1' ?>;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <!-- Product Info Strip -->
                                <div class="d-flex align-items-center gap-2 py-2 px-2 mb-3 bg-light rounded border" style="font-size: 0.78rem;">
                                    <img src="../assets/images/<?= htmlspecialchars($r['product_image'] ?: 'default.jpg') ?>" onerror="this.src='../assets/images/default.jpg'" style="width: 28px; height: 28px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; flex-shrink: 0;" alt="prod">
                                    <span class="fw-semibold text-dark text-truncate"><?= htmlspecialchars($r['product_name']) ?></span>
                                </div>

                                <!-- Comment Body -->
                                <p class="text-dark small mb-0" style="line-height: 1.5; font-size: 0.82rem;">
                                    &ldquo;<?= htmlspecialchars($r['comment']) ?>&rdquo;
                                </p>
                            </div>

                            <!-- Footer Actions -->
                            <div class="border-top pt-2 mt-3 d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-muted border" style="font-family: monospace; font-size: 0.68rem;">
                                    #<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?>
                                </span>
                                <a href="actions/review_delete.php?id=<?= $r['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-sm btn-outline-danger admin-touch-action-btn" onclick="return confirm('Delete this review by <?= htmlspecialchars(addslashes($r['full_name'])) ?>?');" title="Delete review">
                                    <i class="bi bi-trash me-1"></i>Remove
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="admin-card text-center py-5 text-muted">
                        <i class="bi bi-chat-square-text text-muted opacity-50 fs-2 d-block mb-2"></i>
                        <div class="fw-semibold text-dark">No customer reviews found</div>
                        <div class="small text-muted">No reviews match the selected filter or search criteria.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Default High-Density Table View -->
        <div class="admin-card p-0 overflow-hidden">
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-3 py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Review ID</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Customer</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Product</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Rating</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em; min-width: 260px;">Feedback Comment</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Date</th>
                            <th class="pe-3 py-3 text-end text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($reviews)): ?>
                            <?php foreach ($reviews as $r): ?>
                                <tr class="border-bottom">
                                    <td class="ps-3 py-3">
                                        <span class="badge bg-light text-dark border" style="font-family: monospace; font-size: 0.75rem;">
                                            #<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar-initial" style="width: 34px; height: 34px; font-size: 0.85rem; flex-shrink: 0; background-color: #0f172a; color: #ffffff; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">
                                                <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                                            </div>
                                            <div class="overflow-hidden">
                                                <div class="fw-semibold text-dark text-truncate small" title="<?= htmlspecialchars($r['full_name']) ?>">
                                                    <?= htmlspecialchars($r['full_name']) ?>
                                                </div>
                                                <div class="text-muted text-truncate" style="font-size: 0.75rem;" title="<?= htmlspecialchars($r['user_email'] ?? '') ?>">
                                                    <?= htmlspecialchars($r['user_email'] ?? '') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="../assets/images/<?= htmlspecialchars($r['product_image'] ?: 'default.jpg') ?>" onerror="this.src='../assets/images/default.jpg'" style="width: 32px; height: 32px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; flex-shrink: 0;" alt="prod">
                                            <span class="fw-semibold text-dark text-truncate small" style="max-width: 160px;" title="<?= htmlspecialchars($r['product_name']) ?>">
                                                <?= htmlspecialchars($r['product_name']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <div class="d-inline-flex" style="color: #f59e0b;">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi <?= $i <= $r['rating'] ? 'bi-star-fill' : 'bi-star' ?>" style="font-size: 0.78rem; color: <?= $i <= $r['rating'] ? '#f59e0b' : '#cbd5e1' ?>;"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="badge bg-light text-dark border ms-1 fw-bold" style="font-size: 0.7rem; font-variant-numeric: tabular-nums;">
                                                <?= number_format((float)$r['rating'], 1) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="text-dark small" style="line-height: 1.45; max-width: 440px;">
                                            &ldquo;<?= htmlspecialchars($r['comment']) ?>&rdquo;
                                        </div>
                                    </td>
                                    <td class="py-3 text-muted small text-nowrap">
                                        <?= date('M d, Y', strtotime($r['created_at'])) ?>
                                    </td>
                                    <td class="pe-3 py-3 text-end text-nowrap">
                                        <a href="actions/review_delete.php?id=<?= $r['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-sm btn-outline-danger py-1 px-2 d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;" onclick="return confirm('Permanently remove this review by <?= htmlspecialchars(addslashes($r['full_name'])) ?>?');" title="Remove Review">
                                            <i class="bi bi-trash"></i> <span>Remove</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <i class="bi bi-chat-square-text text-muted opacity-50 fs-2 d-block mb-2"></i>
                                        <div class="fw-semibold text-dark">No customer reviews found</div>
                                        <div class="small text-muted">No reviews match the selected filter or search criteria.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Touch Card Feed -->
            <div class="d-md-none admin-touch-feed p-2 p-sm-3">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="admin-touch-card">
                            <div class="admin-touch-card-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="admin-avatar-initial" style="width: 34px; height: 34px; font-size: 0.85rem; flex-shrink: 0; background-color: #0f172a; color: #ffffff; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">
                                        <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 140px;"><?= htmlspecialchars($r['full_name']) ?></div>
                                        <span class="text-muted" style="font-size: 0.7rem;"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="d-inline-flex align-items-center gap-1">
                                    <div class="d-inline-flex" style="color: #f59e0b;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?= $i <= $r['rating'] ? 'bi-star-fill' : 'bi-star' ?>" style="font-size: 0.75rem; color: <?= $i <= $r['rating'] ? '#f59e0b' : '#cbd5e1' ?>;"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="badge bg-light text-dark border ms-1 fw-bold" style="font-size: 0.7rem;"><?= number_format((float)$r['rating'], 1) ?></span>
                                </div>
                            </div>
                            <div class="admin-touch-card-body">
                                <div class="d-flex align-items-center gap-2 py-1 px-2 mb-2 bg-light rounded border" style="font-size: 0.78rem;">
                                    <img src="../assets/images/<?= htmlspecialchars($r['product_image'] ?: 'default.jpg') ?>" onerror="this.src='../assets/images/default.jpg'" style="width: 24px; height: 24px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; flex-shrink: 0;" alt="prod">
                                    <span class="fw-semibold text-dark text-truncate"><?= htmlspecialchars($r['product_name']) ?></span>
                                </div>
                                <p class="text-dark small mb-0" style="line-height: 1.45; font-size: 0.82rem;">
                                    &ldquo;<?= htmlspecialchars($r['comment']) ?>&rdquo;
                                </p>
                            </div>
                            <div class="admin-touch-card-footer">
                                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">
                                    #<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?>
                                </span>
                                <a href="actions/review_delete.php?id=<?= $r['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-sm btn-outline-danger admin-touch-action-btn d-flex align-items-center gap-1" onclick="return confirm('Permanently remove this review?');" title="Remove Review">
                                    <i class="bi bi-trash"></i> <span>Remove</span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-chat-square-text text-muted opacity-50 fs-2 d-block mb-2"></i>
                        <div>No reviews found matching criteria.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
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
        echo '<div class="alert alert-danger p-4 text-center my-4 admin-card"><i class="bi bi-exclamation-triangle me-2"></i>Customer account not found. <button onclick="loadView(\'users\')" class="btn btn-sm btn-dark ms-3">Back to Directory</button></div>';
        exit;
    }

    $stmtOrders = $pdo->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
        FROM orders o 
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmtOrders->execute([$userId]);
    $userOrders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    try {
        $stmtFn = $pdo->prepare("SELECT fn_get_total_spent(?)");
        $stmtFn->execute([$userId]);
        $lifetimeSpend = (float)($stmtFn->fetchColumn() ?: 0.00);
    } catch (PDOException $e) {
        $stmtAlloc = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE user_id = ? AND status != 'Cancelled'");
        $stmtAlloc->execute([$userId]);
        $lifetimeSpend = (float)($stmtAlloc->fetchColumn() ?: 0.00);
    }

    $totalOrders = count($userOrders);
    $deliveredOrders = 0;
    foreach ($userOrders as $ord) {
        if ($ord['status'] === 'Delivered') {
            $deliveredOrders++;
        }
    }
    $avgOrder = $totalOrders > 0 ? $lifetimeSpend / $totalOrders : 0.0;
    ?>
    <!-- View Header -->
    <div class="admin-view-header">
        <div class="admin-view-header-main">
            <span class="admin-kicker">Client Record &bull; ID #<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></span>
            <div class="admin-view-heading-group">
                <h1 class="admin-view-title"><?= htmlspecialchars($user['full_name'] ?? 'Customer Profile') ?></h1>
            </div>
            <p class="admin-view-subtitle"><?= htmlspecialchars($user['email'] ?? '') ?> &bull; Registered <?= date('F d, Y', strtotime($user['created_at'])) ?></p>
        </div>
        <div class="admin-view-header-actions">
            <button type="button" class="admin-header-btn" onclick="loadView('users')" title="Back to Customer Directory" aria-label="Back to Customer Directory">
                <i class="bi bi-arrow-left"></i>
                <span>Customers</span>
            </button>
            <a href="actions/user_delete.php?id=<?= $user['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="admin-header-btn admin-header-btn-danger" onclick="return confirm('Delete user account <?= htmlspecialchars(addslashes($user['full_name'] ?? '')) ?>? This cannot be undone.');" title="Delete User Account">
                <i class="bi bi-trash"></i>
                <span>Delete Account</span>
            </a>
        </div>
    </div>

    <!-- Customer Telemetry Strip -->
    <div class="admin-card p-0 mb-4 overflow-hidden">
        <div class="row g-0">
            <!-- Metric 1: Lifetime Spend -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Lifetime Spend</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-success fs-5" style="font-variant-numeric: tabular-nums;">₱<?= number_format($lifetimeSpend, 2) ?></span>
                    <span class="text-muted small">Total Spent</span>
                </div>
                <div class="text-muted small">
                    <span class="text-success fw-semibold">ADS Stored Routine</span> &bull; Verified
                </div>
            </div>

            <!-- Metric 2: Total Orders -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-xl-bottom-0">
                <div class="admin-kpi-label mb-1">Orders Placed</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;"><?= $totalOrders ?> Orders</span>
                    <span class="text-muted small"><?= $deliveredOrders ?> Delivered</span>
                </div>
                <div class="text-muted small">
                    <span class="text-dark fw-semibold"><?= $totalOrders > 0 ? 'Active Customer' : 'No Transactions' ?></span>
                </div>
            </div>

            <!-- Metric 3: Average Order Value -->
            <div class="col-sm-6 col-xl-3 p-3 px-4 border-end border-bottom border-sm-bottom-0">
                <div class="admin-kpi-label mb-1">Average Order Value</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;">₱<?= number_format($avgOrder, 2) ?></span>
                    <span class="text-muted small">Per Order</span>
                </div>
                <div class="text-muted small">
                    <span class="text-secondary fw-semibold">AOV</span> &bull; Lifetime performance
                </div>
            </div>

            <!-- Metric 4: Account Status -->
            <div class="col-sm-6 col-xl-3 p-3 px-4">
                <div class="admin-kpi-label mb-1">Account Standing</div>
                <div class="d-flex align-items-baseline justify-content-between mb-1">
                    <span class="fw-bold <?= $totalOrders > 0 ? 'text-success' : 'text-secondary' ?> fs-5">
                        <?= $totalOrders > 0 ? 'Active Buyer' : 'New Member' ?>
                    </span>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($user['role'] ?? 'customer') ?></span>
                </div>
                <div class="text-muted small">
                    Joined <?= date('M Y', strtotime($user['created_at'])) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Profile Information Card -->
    <div class="admin-card p-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="admin-avatar-initial" style="width: 48px; height: 48px; font-size: 1.25rem; flex-shrink: 0; background-color: #0f172a; color: #ffffff; border-radius: 10px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">
                <?= strtoupper(substr($user['full_name'] ?? '?', 0, 1)) ?>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h2 class="fs-6 fw-bold text-dark mb-0"><?= htmlspecialchars($user['full_name'] ?? 'Unknown') ?></h2>
                        <div class="text-muted small"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                    </div>
                    <?php if (!empty($user['address'])): ?>
                        <div class="text-muted small d-flex align-items-center gap-1">
                            <i class="bi bi-geo-alt text-secondary"></i>
                            <span><?= htmlspecialchars($user['address']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Order History Section -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="admin-card-heading">Order History</h2>
        <span class="text-muted small"><?= $totalOrders ?> Recorded Transactions</span>
    </div>

    <?php if (!empty($userOrders)): ?>
        <div class="admin-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-3 py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Order ID</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Date Placed</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Items</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Total Amount</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Status</th>
                            <th class="pe-3 py-3 text-end text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userOrders as $order): 
                            $status = $order['status'];
                            $pillClass = 'status-pending';
                            if ($status === 'Delivered') $pillClass = 'status-delivered';
                            elseif ($status === 'Shipped') $pillClass = 'status-shipped';
                            elseif ($status === 'Cancelled') $pillClass = 'status-cancelled';
                        ?>
                            <tr class="border-bottom">
                                <td class="ps-3 py-3 fw-semibold" style="font-family: monospace;">
                                    #<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td class="py-3 small text-muted">
                                    <?= date('M d, Y &bull; h:i A', strtotime($order['created_at'])) ?>
                                </td>
                                <td class="py-3 small text-muted">
                                    <?= (int)($order['item_count'] ?? 0) ?> items
                                </td>
                                <td class="py-3 fw-semibold text-dark" style="font-variant-numeric: tabular-nums;">
                                    ₱<?= number_format((float)$order['total_amount'], 2) ?>
                                </td>
                                <td class="py-3">
                                    <span class="admin-status-pill <?= $pillClass ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                                <td class="pe-3 py-3 text-end">
                                    <a href="order_details.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-2 d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;">
                                        <i class="bi bi-receipt"></i> <span>View Order</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-card text-center py-5 text-muted">
            <i class="bi bi-bag-x text-muted opacity-50 fs-2 d-block mb-2"></i>
            <div class="fw-semibold text-dark">No orders found</div>
            <div class="small text-muted">This customer has not placed any orders yet.</div>
        </div>
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

    // 2. Query filtered orders with item counts & customer user details
    $statusFilter = $_GET['status'] ?? 'All';
    $search = trim($_GET['search'] ?? '');
    
    $sql = "SELECT o.*, u.full_name AS user_full_name, u.email AS user_email, 
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id";
    $where = [];
    $params = [];

    if ($statusFilter != 'All' && in_array($statusFilter, ['Pending', 'Shipped', 'Delivered', 'Cancelled'], true)) {
        $where[] = "o.status = ?";
        $params[] = $statusFilter;
    }

    if ($search !== '') {
        list($searchClause, $searchParams) = buildAdminSearchClause($search, 'o.id', ['o.customer_name', 'u.full_name', 'u.email', 'o.address'], 5);
        if ($searchClause !== '') {
            $where[] = $searchClause;
            $params = array_merge($params, $searchParams);
        }
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
        <div class="admin-view-header-main">
            <span class="admin-kicker">Fulfillment &amp; Logistics</span>
            <div class="admin-view-heading-group">
                <h1 class="admin-view-title">Order Management</h1>
                <span class="admin-count-badge"><?= $search !== '' ? number_format(count($allOrders)) . ' Found' : number_format($statusCounts['All']) . ' Orders' ?></span>
            </div>
            <p class="admin-view-subtitle">Track customer orders, fulfillment pipelines, and delivery records.</p>
        </div>
        <div class="admin-view-header-actions">
            <button type="button" class="admin-header-btn admin-header-btn-icon" onclick="loadView('orders')" title="Refresh Orders" aria-label="Refresh Orders">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <a href="export_orders.php" class="admin-header-btn" title="Export Orders (CSV)">
                <i class="bi bi-download"></i>
                <span>Export CSV</span>
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
                    <span class="fw-bold text-dark fs-5" style="font-variant-numeric: tabular-nums;">₱<?= number_format($totalOrderRevenue, 2) ?></span>
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
    <div class="admin-card admin-toolbar-card mb-4">
        <!-- Mobile 1-Line Search & Filter Bar -->
        <div class="d-lg-none">
            <form onsubmit="event.preventDefault(); loadView('orders&status=<?= urlencode($statusFilter) ?>&search=' + encodeURIComponent(this.search.value.trim()));" class="admin-search-filter-row">
                <div class="admin-search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="search" placeholder="Search customer name, Order #ID, address..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                </div>
                <button type="submit" class="admin-search-submit-btn" title="Search Orders" aria-label="Search">
                    <i class="bi bi-search"></i>
                </button>
                <div class="dropdown">
                    <button class="btn admin-filter-dropdown-btn dropdown-toggle <?= ($statusFilter !== 'All') ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="filter-text">Status: <strong><?= htmlspecialchars($statusFilter) ?></strong></span>
                        <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown-menu shadow">
                        <li class="admin-dropdown-header">Filter by Status</li>
                        <?php
                        $statuses = ['All', 'Pending', 'Shipped', 'Delivered', 'Cancelled'];
                        foreach ($statuses as $s):
                            $isActive = ($statusFilter === $s);
                            $countLabel = $statusCounts[$s] ?? 0;
                            $searchParam = ($search !== '') ? '&search=' . urlencode($search) : '';
                            $param = ($s == 'All') ? 'orders' . $searchParam : "orders&status=$s" . $searchParam;
                        ?>
                            <li>
                                <button type="button" onclick="loadView('<?= $param ?>')" class="admin-dropdown-item <?= $isActive ? 'active' : '' ?>">
                                    <span class="item-label">
                                        <?php if ($isActive): ?>
                                            <i class="bi bi-check2 text-success"></i>
                                        <?php endif; ?>
                                        <?= $s ?>
                                    </span>
                                    <span class="badge"><?= $countLabel ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </form>
            <?php if ($search !== ''): ?>
                <div class="mt-2 text-end">
                    <button type="button" onclick="loadView('orders&status=<?= urlencode($statusFilter) ?>')" class="admin-search-clear-btn">
                        <i class="bi bi-x-circle me-1"></i>Clear search "<?= htmlspecialchars($search) ?>"
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Desktop Search & Expanded Chip Tabs Bar -->
        <div class="d-none d-lg-flex align-items-center justify-content-between gap-3 admin-desktop-toolbar">
            <div class="admin-chip-rail" role="group" aria-label="Order Status Filter">
                <?php
                foreach ($statuses as $s):
                    $isActive = ($statusFilter === $s);
                    $activeClass = $isActive ? 'active' : '';
                    $countLabel = $statusCounts[$s] ?? 0;
                    $searchParam = ($search !== '') ? '&search=' . urlencode($search) : '';
                    $param = ($s == 'All') ? 'orders' . $searchParam : "orders&status=$s" . $searchParam;
                ?>
                    <button type="button" onclick="loadView('<?= $param ?>')" class="admin-chip-btn <?= $activeClass ?>">
                        <span><?= $s ?></span> <span class="badge <?= $isActive ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary' ?> ms-1" style="font-size: 0.68rem;"><?= $countLabel ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <form onsubmit="event.preventDefault(); loadView('orders&status=<?= urlencode($statusFilter) ?>&search=' + encodeURIComponent(this.search.value.trim()));" class="admin-desktop-search-form">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search customer name, Order #ID, address..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                </div>

                <button type="submit" class="admin-desktop-search-btn">Search</button>
                <?php if ($search !== ''): ?>
                    <button type="button" onclick="loadView('orders&status=<?= urlencode($statusFilter) ?>')" class="admin-desktop-clear-btn" title="Clear search">Clear</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 3. Orders Master Table -->
    <div class="admin-card p-0 overflow-hidden">
        <!-- Desktop Table View -->
        <div class="table-responsive d-none d-lg-block">
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
                                    <div class="fw-semibold text-dark small"><?= htmlspecialchars($order['customer_name'] ?: ($order['user_full_name'] ?: 'Customer')) ?></div>
                                    <?php if (!empty($order['user_email'])): ?>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($order['user_email']) ?></div>
                                    <?php endif; ?>
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
                                    ₱<?= number_format((float)$order['total_amount'], 2) ?>
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

        <!-- Mobile Touch Card Feed -->
        <div class="d-lg-none admin-touch-feed p-2 p-sm-3">
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
                    <div class="admin-touch-card">
                        <div class="admin-touch-card-header">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.78rem;">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                <span class="fw-bold text-dark text-truncate" style="font-size: 0.92rem; max-width: 150px;"><?= htmlspecialchars($order['customer_name'] ?: ($order['user_full_name'] ?: 'Customer')) ?></span>
                            </div>
                            <span class="admin-status-pill <?= $pillClass ?>"><?= $s ?></span>
                        </div>
                        <div class="admin-touch-card-body">
                            <div class="text-muted small d-flex align-items-center gap-1 mb-2">
                                <i class="bi bi-geo-alt text-secondary flex-shrink-0" style="font-size: 0.75rem;"></i>
                                <span class="text-truncate"><?= htmlspecialchars($order['address']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1 px-2 bg-light rounded small">
                                <span class="text-muted"><?= date('M d, Y', strtotime($order['created_at'])) ?> &bull; <?= $itemCount ?> <?= $itemCount === 1 ? 'item' : 'items' ?></span>
                                <span class="fw-bold text-dark fs-6" style="font-variant-numeric: tabular-nums;">
                                    ₱<?= number_format((float)$order['total_amount'], 2) ?>
                                </span>
                            </div>
                        </div>
                        <div class="admin-touch-card-footer">
                            <a href="order_details.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-secondary w-100 admin-touch-action-btn d-flex align-items-center justify-content-center gap-1">
                                <span>Manage Order</span> <i class="bi bi-chevron-right small"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox text-muted opacity-50 fs-2 d-block mb-2"></i>
                    <div>No orders found matching criteria.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
