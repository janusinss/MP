<?php
require_once __DIR__ . '/config/db.php';

// Check if a session is already active before starting one
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// User & Admin Role Status
$isAdmin = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// --- FORCE LOGOUT CHECK (Customer accounts only) ---
if (isset($_SESSION['user_id']) && !$isAdmin) {
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmtCheck->execute([$_SESSION['user_id']]);
    if (!$stmtCheck->fetch()) {
        session_destroy();
        header("Location: ./");
        exit;
    }
}

$isLoggedIn = isset($_SESSION['user_id']) || $isAdmin;
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// --- PAGINATION & FILTER LOGIC ---
$limit = $isLoggedIn ? 24 : 12; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? '';

// Build Query
$sqlWhere = "WHERE 1=1";
$params = [];

if ($search) {
    $sqlWhere .= " AND name LIKE ?";
    $params[] = "%$search%";
}
if ($category) {
    $sqlWhere .= " AND category = ?";
    $params[] = $category;
}

// Count Total
$countSql = "SELECT COUNT(*) FROM products $sqlWhere";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalItems = $stmtCount->fetchColumn();
$totalPages = ceil($totalItems / $limit);

// Fetch Items
$sql = "SELECT * FROM products $sqlWhere";
if ($sort === 'price_asc') {
    $sql .= " ORDER BY price ASC";
} elseif ($sort === 'price_desc') {
    $sql .= " ORDER BY price DESC";
} elseif ($sort === 'alpha') {
    $sql .= " ORDER BY name ASC";
} else {
    $sql .= " ORDER BY id DESC";
}

$sql .= " LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Categories for Filter
$catStmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate Live Cart Subtotal
$cartSubtotal = 0;
if (!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    if (!empty($productIds)) {
        $inQuery = implode(',', array_fill(0, count($productIds), '?'));
        $stmtCartPrices = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($inQuery)");
        $stmtCartPrices->execute($productIds);
        $prices = $stmtCartPrices->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (isset($prices[$pid])) {
                $cartSubtotal += $prices[$pid] * $qty;
            }
        }
    }
}

// Category Counts and Total Food Count
$categoryCounts = [];
$stmtCatCounts = $pdo->query("SELECT category, COUNT(*) as cnt FROM products GROUP BY category");
while ($row = $stmtCatCounts->fetch(PDO::FETCH_ASSOC)) {
    $categoryCounts[$row['category']] = (int)$row['cnt'];
}
$totalFoodCount = array_sum($categoryCounts);

// Category Icons Mapping
$categoryIcons = [
    'Fruits' => 'bi-apple',
    'Vegetables' => 'bi-flower1',
    'Dairy' => 'bi-egg-fried',
    'Bakery' => 'bi-cake2',
    'Meat' => 'bi-egg',
    'Pantry' => 'bi-box-seam',
    'Snacks' => 'bi-cup-hot',
    'Beverages' => 'bi-cup-straw'
];

// Spotlight Items for Logged-In Food Hall
$spotlightItems = [];
if ($isLoggedIn) {
    $spotlightIds = [4, 10, 3];
    $inSpot = implode(',', array_fill(0, count($spotlightIds), '?'));
    $stmtSpot = $pdo->prepare("SELECT * FROM products WHERE id IN ($inSpot)");
    $stmtSpot->execute($spotlightIds);
    $spotlightItems = $stmtSpot->fetchAll(PDO::FETCH_ASSOC);
    if (count($spotlightItems) < 3) {
        $stmtSpotFallback = $pdo->query("SELECT * FROM products ORDER BY id DESC LIMIT 3");
        $spotlightItems = $stmtSpotFallback->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Farm Origins & Culinary Metadata Mapping
$farmOrigins = [
    'Fruits' => ['farm' => 'Highland Valley Orchards', 'telemetry' => 'Picked < 18h ago', 'badge' => 'Peak Sugar Brix 14°', 'notes' => 'Sun-ripened tree fruit picked at natural maturity.'],
    'Vegetables' => ['farm' => 'Sun Valley Organics', 'telemetry' => 'Dawn Harvest', 'badge' => 'Mineral Soil Grown', 'notes' => 'Cultivated in pesticide-free mineral soil and washed in pure spring water.'],
    'Dairy' => ['farm' => 'Greenfield Pastures', 'telemetry' => 'Cold-Chain 4°C', 'badge' => '100% Grass-Fed', 'notes' => 'Pure unhomogenized cream and milk from pasture-roamed heritage cows.'],
    'Bakery' => ['farm' => 'Heritage Grain Bakehouse', 'telemetry' => 'Hearth Baked 5 AM', 'badge' => 'Stone-Ground Heirloom', 'notes' => 'Natural 36-hour slow levain fermentation with organic stone-ground flour.'],
    'Meat' => ['farm' => 'Valley Meats & Ranch', 'telemetry' => 'Free-Range Certified', 'badge' => 'Pasture-Raised', 'notes' => 'Ethically raised on open pastures with zero hormones or preventative antibiotics.'],
    'Pantry' => ['farm' => 'Valley Apiaries & Provisions', 'telemetry' => 'Small Batch Raw', 'badge' => 'Cold-Pressed Raw', 'notes' => 'Pure wildflower unheated honey and artisan pantry staples.'],
    'Snacks' => ['farm' => 'Homestead Kitchens', 'telemetry' => 'Dehydrated Raw', 'badge' => 'No Added Refined Sugar', 'notes' => 'Wholesome pantry crunch made without preservatives or hydrogenated fats.'],
    'Beverages' => ['farm' => 'Cold Spring Orchards', 'telemetry' => 'Cold-Pressed', 'badge' => 'Unpasteurized & Fresh', 'notes' => 'Freshly extracted raw botanical and orchard juices chilled instantly to 3.5°C.']
];

// Clean Catalog URL Builder
if (!function_exists('catalog_url')) {
    function catalog_url($p = null, $s = null, $c = null, $sort = null) {
        global $isLoggedIn;
        $params = [];
        $pageVal = ($p !== null) ? $p : ($_GET['page'] ?? 1);
        $searchVal = ($s !== null) ? $s : ($_GET['search'] ?? '');
        $catVal = ($c !== null) ? $c : ($_GET['category'] ?? '');
        $sortVal = ($sort !== null) ? $sort : ($_GET['sort'] ?? '');

        if ($pageVal > 1) $params['page'] = (int)$pageVal;
        if (!empty($searchVal)) $params['search'] = trim($searchVal);
        if (!empty($catVal)) $params['category'] = trim($catVal);
        if (!empty($sortVal)) $params['sort'] = trim($sortVal);

        $targetHash = $isLoggedIn ? '' : '#catalog';
        $query = !empty($params) ? '?' . http_build_query($params) : './';
        return htmlspecialchars($query . $targetHash);
    }
}



// Aisle Departments for Interactive Visual Aisle Explorer
$aisleDepartments = [
    'Fruits' => [
        'name' => 'Fruits',
        'label' => 'Fresh Orchard',
        'badge' => 'Certified Organic Orchard',
        'title' => 'Sun-Ripened Orchard Harvest',
        'desc' => 'Tree-ripened apples, seasonal berries, and citrus picked at peak natural sweetness.',
        'farm' => 'Highland Valley Orchards',
        'telemetry' => 'Picked < 18h ago',
        'icon' => 'bi-apple'
    ],
    'Vegetables' => [
        'name' => 'Vegetables',
        'label' => 'Field Vegetables',
        'badge' => 'Regenerative Soil Certified',
        'title' => 'Crisp Field Vegetables & Greens',
        'desc' => 'Cultivated in pesticide-free mineral soil and harvested at dawn for crisp texture.',
        'farm' => 'Sun Valley Organics',
        'telemetry' => 'Harvested at Dawn',
        'icon' => 'bi-flower1'
    ],
    'Dairy' => [
        'name' => 'Dairy',
        'label' => 'Pasture Dairy',
        'badge' => '100% Grass-Fed Pastures',
        'title' => 'Pasture-Raised Dairy & Farm Eggs',
        'desc' => 'Pure whole milk, artisan churned butter, and pasture-roamed organic eggs.',
        'farm' => 'Greenfield Family Pastures',
        'telemetry' => 'Cold-Chain 4°C',
        'icon' => 'bi-egg-fried'
    ],
    'Bakery' => [
        'name' => 'Bakery',
        'label' => 'Artisan Bakery',
        'badge' => 'Daily Stone-Ground',
        'title' => 'Artisan Hearth Loaves & Breads',
        'desc' => 'Slow-fermented sourdoughs and morning-baked loaves made from heirloom stone-ground wheat.',
        'farm' => 'Heritage Grain Bakehouse',
        'telemetry' => 'Baked 5:00 AM',
        'icon' => 'bi-cake2'
    ],
    'Pantry' => [
        'name' => 'Pantry',
        'label' => 'Farmstead Pantry',
        'badge' => 'Small-Batch Producers',
        'title' => 'Farmstead Pantry & Raw Preserves',
        'desc' => 'Wildflower honey, whole-fruit conserves, and unrefined cold-pressed oils.',
        'farm' => 'Valley Apiaries & Provisions',
        'telemetry' => 'Pure Raw Sourced',
        'icon' => 'bi-box-seam'
    ]
];

$aisleReels = [];
$stmtReel = $pdo->prepare("SELECT * FROM products WHERE category = ? ORDER BY id DESC LIMIT 8");
foreach ($aisleDepartments as $catKey => $meta) {
    $stmtReel->execute([$catKey]);
    $items = $stmtReel->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($items)) {
        $aisleReels[$catKey] = [
            'meta' => $meta,
            'items' => $items
        ];
    }
}
$firstKey = array_key_first($aisleReels);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>FreshCart Market | Clean Organic Sourcing</title>
    <meta name="description" content="Certified organic produce, local dairy, and pantry staples direct from family farms.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
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
<body>

    <?php if (!$isLoggedIn): ?>
    <!-- Welcome Offer Lightbox Pop-up Modal -->
    <div id="welcomePromoModal" class="promo-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="promoModalTitle" aria-describedby="promoModalDesc">
        <div class="promo-modal-dialog">
            <button type="button" class="promo-modal-close-btn" onclick="dismissPromoModal()" aria-label="Close welcome offer" title="Close (Esc)">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>

            <div class="promo-modal-leaf-icon" aria-hidden="true">
                <i class="bi bi-gift"></i>
            </div>

            <div class="promo-modal-kicker">
                <span>Welcome Offer</span>
            </div>

            <h3 class="promo-modal-heading" id="promoModalTitle">
                Enjoy <strong>50% off</strong> your first seasonal order
            </h3>

            <p class="promo-modal-desc" id="promoModalDesc">
                Taste the crisp difference of farm-fresh harvests, sustainably grown by local producers and delivered directly to your kitchen table.
            </p>

            <div class="promo-modal-code-wrapper">
                <span class="promo-code-label">Code:</span>
                <span class="promo-code-val" id="promoCodeVal">FRESH50</span>
                <button type="button" class="promo-code-copy-btn" id="promoModalCopyBtn" onclick="copyPromoCode(this, 'FRESH50')" aria-label="Copy promo code FRESH50" title="Click to copy code FRESH50">
                    <i class="bi bi-copy promo-icon-copy" aria-hidden="true"></i>
                    <i class="bi bi-check2 promo-icon-check" aria-hidden="true"></i>
                    <span class="promo-copy-text">Copy</span>
                </button>
                <span class="promo-copied-feedback" role="status" aria-live="polite">Copied!</span>
            </div>

            <div class="promo-modal-actions">
                <a href="#harvest-catalog" class="btn btn-primary promo-claim-btn" onclick="dismissPromoModal()">
                    Claim Offer &amp; Start Shopping
                </a>
                <button type="button" class="promo-modal-dismiss-link" onclick="dismissPromoModal()">
                    No thanks, continue browsing
                </button>
            </div>
        </div>
    </div>

    <script>
        function openPromoModal() {
            const modal = document.getElementById('welcomePromoModal');
            if (modal) {
                modal.classList.add('is-open');
                document.body.classList.add('modal-open-freeze');
            }
        }

        function dismissPromoModal() {
            const modal = document.getElementById('welcomePromoModal');
            if (modal) {
                modal.classList.remove('is-open');
                document.body.classList.remove('modal-open-freeze');
                try {
                    sessionStorage.setItem('promo_popup_dismissed', '1');
                } catch (e) {}
            }
        }

        function copyPromoCode(btn, code) {
            if (!code) code = 'FRESH50';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).catch(() => {});
            }
            if (btn) {
                btn.classList.add('is-copied');
                const textSpan = btn.querySelector('.promo-copy-text');
                if (textSpan) textSpan.textContent = 'Copied';
                setTimeout(() => {
                    btn.classList.remove('is-copied');
                    if (textSpan) textSpan.textContent = 'Copy';
                }, 2000);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            let isDismissed = false;
            try {
                isDismissed = sessionStorage.getItem('promo_popup_dismissed') === '1';
            } catch (e) {}

            if (!isDismissed && window.innerWidth > 768) {
                setTimeout(openPromoModal, 600);
            }

            const modal = document.getElementById('welcomePromoModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        dismissPromoModal();
                    }
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' || e.key === 'Esc') {
                    dismissPromoModal();
                }
            });
        });
    </script>
    <?php endif; ?>

    <nav class="navbar navbar-expand-lg navbar-glass sticky-top">
        <div class="container">
            <a class="navbar-brand" href="./">
                <h3 class="m-0" style="font-family: var(--font-serif); letter-spacing: -0.05em; font-weight: 800;">
                    FreshCart<span style="color: var(--accent-color)">.</span>
                </h3>
            </a>
            
            <div class="mobile-nav-actions d-flex align-items-center gap-2 d-lg-none">
                <?php if ($isLoggedIn): ?>
                    <a href="cart" class="mobile-header-action-btn mobile-header-cart" aria-label="Shopping Cart">
                        <i class="bi bi-bag"></i>
                        <span class="mobile-header-cart-badge <?= ($cartCount > 0) ? '' : 'd-none' ?>"><?= $cartCount ?></span>
                    </a>
                    <a href="<?= $isAdmin ? 'admin/' : 'profile' ?>" class="mobile-header-action-btn mobile-header-avatar" aria-label="Account">
                        <span><?= $isAdmin ? '<i class="bi bi-shield-check"></i>' : strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></span>
                    </a>
                <?php endif; ?>

                <?php if (!$isLoggedIn): ?>
                <button class="navbar-toggler freshcart-toggler collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navContent" aria-controls="navContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="toggler-bars" aria-hidden="true">
                        <span class="toggler-bar bar-top"></span>
                        <span class="toggler-bar bar-mid"></span>
                        <span class="toggler-bar bar-bot"></span>
                    </span>
                </button>
                <?php endif; ?>
            </div>

            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav mx-auto align-items-center gap-1">
                    <?php if ($isAdmin): ?>
                        <li class="nav-item"><a href="#food-aisles" class="nav-link-custom">Food Aisles</a></li>
                        <li class="nav-item"><a href="#peak-harvest" class="nav-link-custom">Peak Harvest</a></li>
                        <li class="nav-item"><a href="#all-foods" class="nav-link-custom">All Foods</a></li>
                        <li class="nav-item"><a href="admin/index.php?view=products" class="nav-link-custom"><i class="bi bi-box-seam text-success me-1"></i>Stock Control</a></li>
                    <?php elseif ($isLoggedIn): ?>
                        <li class="nav-item"><a href="#food-aisles" class="nav-link-custom">Food Aisles</a></li>
                        <li class="nav-item"><a href="#peak-harvest" class="nav-link-custom">Peak Harvest</a></li>
                        <li class="nav-item"><a href="#all-foods" class="nav-link-custom">All Foods</a></li>
                        <li class="nav-item"><a href="orders" class="nav-link-custom">My Orders</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a href="#catalog" class="nav-link-custom">Community Reviews</a></li>
                        <li class="nav-item"><a href="#categories" class="nav-link-custom">Aisles</a></li>
                        <li class="nav-item"><a href="#story" class="nav-link-custom">Our Growers</a></li>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <?php if ($isAdmin): ?>
                        <li class="nav-item dropdown">
                            <div class="storefront-admin-pill">
                                <a href="admin/" class="storefront-admin-pill-link" title="Return to Admin Console">
                                    <i class="bi bi-arrow-left storefront-admin-pill-icon"></i>
                                    <span>Return to Admin</span>
                                </a>
                                <button class="storefront-admin-pill-toggle dropdown-toggle" type="button" id="landingAdminDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Admin options">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-2 mt-2 storefront-admin-dropdown" aria-labelledby="landingAdminDropdown">
                                    <li class="px-3 py-2 border-bottom mb-1" style="background-color: #fafbf9;">
                                        <div class="text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em; font-weight: 700;">Signed in as</div>
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?></div>
                                    </li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="admin/"><i class="bi bi-speedometer2 text-success"></i> <span>Operations Console</span></a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="admin/index.php?view=products"><i class="bi bi-box-seam text-success"></i> <span>Manage Inventory</span></a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="admin/index.php?view=orders"><i class="bi bi-receipt text-success"></i> <span>Manage Orders</span></a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="admin/index.php?view=users"><i class="bi bi-people text-success"></i> <span>Manage Customers</span></a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="admin/index.php?view=reviews"><i class="bi bi-star text-success"></i> <span>Manage Reviews</span></a></li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 text-danger" href="admin/logout.php"><i class="bi bi-box-arrow-right"></i> <span>Sign Out</span></a></li>
                                </ul>
                            </div>
                        </li>
                    <?php elseif (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <button class="nav-link-custom d-flex align-items-center gap-2 dropdown-toggle bg-transparent border-0 p-0 text-decoration-none" id="landingUserDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 34px; height: 34px; font-size: 0.85rem; background: var(--color-primary, #15803d);">
                                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </div>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-2 mt-2" aria-labelledby="landingUserDropdown" style="border-radius: 14px; min-width: 220px; border-color: rgba(0,0,0,0.08);">
                                <li class="px-3 py-2 border-bottom mb-1" style="background-color: #fafbf9;">
                                    <div class="text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em; font-weight: 700;">Signed in as</div>
                                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                                </li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="profile"><i class="bi bi-person-gear text-success"></i> <span>Account Settings</span></a></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="orders"><i class="bi bi-receipt text-success"></i> <span>My Orders</span></a></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="cart"><i class="bi bi-bag-check text-success"></i> <span>View Cart (<?= $cartCount ?>)</span></a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 text-danger" href="logout"><i class="bi bi-box-arrow-right"></i> <span>Sign Out</span></a></li>
                            </ul>
                        </li>

                        <li class="nav-item position-relative">
                            <a href="cart" class="btn btn-outline-secondary border-0 position-relative p-2" aria-label="Shopping Cart">
                                <i class="bi bi-bag fs-5"></i>
                                <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light <?= ($cartCount > 0) ? '' : 'd-none' ?>" style="font-size: 0.65rem;">
                                    <?= $cartCount ?>
                                </span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a href="login" class="nav-link-custom nav-btn-login">Login</a></li>
                        <li class="nav-item"><a href="register" class="btn btn-primary nav-btn-signup rounded-pill px-4 shadow-sm">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($isLoggedIn): ?>
    <!-- Sleek Mobile Bottom Navigation Bar for Logged-In Shoppers -->
    <nav class="fc-mobile-bottom-bar d-lg-none" aria-label="Quick Mobile Navigation">
        <a href="./" class="fc-bottom-tab active">
            <i class="bi bi-shop" aria-hidden="true"></i>
            <span>Market</span>
        </a>
        <a href="#food-aisles" class="fc-bottom-tab">
            <i class="bi bi-grid" aria-hidden="true"></i>
            <span>Aisles</span>
        </a>
        <a href="orders" class="fc-bottom-tab">
            <i class="bi bi-receipt" aria-hidden="true"></i>
            <span>Orders</span>
        </a>
        <a href="cart" class="fc-bottom-tab fc-bottom-tab-cart">
            <div class="position-relative d-inline-block">
                <i class="bi bi-bag" aria-hidden="true"></i>
                <span class="fc-bottom-badge <?= ($cartCount > 0) ? '' : 'd-none' ?>"><?= $cartCount ?></span>
            </div>
            <span>Harvest Bag</span>
        </a>
        <a href="<?= $isAdmin ? 'admin/' : 'profile' ?>" class="fc-bottom-tab">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span>Account</span>
        </a>
    </nav>
    <?php endif; ?>

    <?php if ($isLoggedIn): ?>
        <!-- =========================================================
             SIGNED-IN SHOPPER VIEW: FRESH FOOD MARKET HALL
             Strictly foods, culinary departments, tasting notes, and quick basket
             ========================================================= -->
        <main class="site-main-content food-market-main">
            <div class="food-market-wrapper">
                
                <!-- 1. Interactive Food Aisle Departments Rail & Search -->
                <section class="food-aisles-section" id="food-aisles">
                    <div class="container">
                        <!-- Dedicated Mobile Search Bar -->
                        <div class="mobile-food-search-wrap d-lg-none mb-3">
                            <form action="./" method="GET" class="mobile-food-search-form">
                                <div class="mobile-food-search-box">
                                    <i class="bi bi-search search-icon" aria-hidden="true"></i>
                                    <input type="text" name="search" class="mobile-food-search-input" placeholder="Search fresh produce, dairy, bakery..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                                    <?php if (!empty($category)): ?>
                                        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                                    <?php endif; ?>
                                    <?php if (!empty($sort)): ?>
                                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                                    <?php endif; ?>
                                    <?php if (!empty($search)): ?>
                                        <a href="<?= catalog_url(1, '', $category, $sort) ?>" class="mobile-search-clear-btn" aria-label="Clear search" title="Clear search">
                                            <i class="bi bi-x-circle-fill"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <!-- Mobile Department Custom Dropdown Selector (Same Tactile Menu as Landing Page) -->
                        <div class="mobile-department-select-wrap d-lg-none mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2" id="mobileDeptHeader">
                                <span class="text-uppercase text-muted m-0" style="font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em;">
                                    Browse Department
                                </span>
                                <?php if (!empty($category) || !empty($search)): ?>
                                    <a href="./" class="small text-success text-decoration-none fw-bold" id="showAllFoodsBtnMobile">Show All (<?= $totalFoodCount ?>)</a>
                                <?php endif; ?>
                            </div>
                            <div class="aisle-custom-dropdown" id="foodHallMobileDropdown">
                                <?php 
                                $curLabel = empty($category) ? ('All Departments (' . $totalFoodCount . ' items)') : (htmlspecialchars($category) . ' (' . ($categoryCounts[$category] ?? 0) . ')');
                                $curIcon = empty($category) ? 'bi-grid-fill' : ($categoryIcons[$category] ?? 'bi-basket2');
                                ?>
                                <button type="button" 
                                        class="aisle-select-pill" 
                                        id="foodHallDropdownTrigger" 
                                        aria-haspopup="listbox" 
                                        aria-expanded="false" 
                                        aria-controls="foodHallDropdownMenu"
                                        onclick="toggleFoodHallDropdown()" 
                                        aria-label="Select harvest department">
                                    <span class="aisle-select-icon"><i class="bi <?= $curIcon ?>" id="foodHallSelectActiveIcon" aria-hidden="true"></i></span>
                                    <span class="aisle-select-label" id="foodHallSelectActiveLabel"><?= $curLabel ?></span>
                                    <i class="bi bi-chevron-down aisle-select-chevron" aria-hidden="true"></i>
                                </button>

                                <div class="aisle-dropdown-menu" id="foodHallDropdownMenu" role="listbox" aria-label="Harvest departments">
                                    <button type="button" 
                                            role="option" 
                                            aria-selected="<?= empty($category) ? 'true' : 'false' ?>" 
                                            class="aisle-dropdown-item <?= empty($category) ? 'selected' : '' ?>" 
                                            data-url="./" 
                                            data-name="All Departments (<?= $totalFoodCount ?> items)"
                                            data-icon="bi-grid-fill"
                                            onclick="chooseFoodHallAisle('./', 'All Departments (<?= $totalFoodCount ?> items)', 'bi-grid-fill')">
                                        <span class="aisle-item-icon"><i class="bi bi-grid-fill" aria-hidden="true"></i></span>
                                        <span class="aisle-item-text">All Departments</span>
                                        <span class="aisle-item-count"><?= $totalFoodCount ?></span>
                                        <i class="bi bi-check2 aisle-item-check" aria-hidden="true"></i>
                                    </button>
                                    <?php foreach ($categories as $catName): 
                                        $isSelected = ($category === $catName);
                                        $catCount = $categoryCounts[$catName] ?? 0;
                                        $catIcon = $categoryIcons[$catName] ?? 'bi-basket2';
                                        $catUrl = "?category=" . urlencode($catName);
                                        $catDisplay = htmlspecialchars($catName) . ' (' . $catCount . ')';
                                    ?>
                                        <button type="button" 
                                                role="option" 
                                                aria-selected="<?= $isSelected ? 'true' : 'false' ?>" 
                                                class="aisle-dropdown-item <?= $isSelected ? 'selected' : '' ?>" 
                                                data-url="<?= $catUrl ?>" 
                                                data-name="<?= $catDisplay ?>"
                                                data-icon="<?= $catIcon ?>"
                                                onclick="chooseFoodHallAisle('<?= $catUrl ?>', '<?= $catDisplay ?>', '<?= $catIcon ?>')">
                                            <span class="aisle-item-icon"><i class="bi <?= $catIcon ?>" aria-hidden="true"></i></span>
                                            <span class="aisle-item-text"><?= htmlspecialchars($catName) ?></span>
                                            <span class="aisle-item-count"><?= $catCount ?></span>
                                            <i class="bi bi-check2 aisle-item-check" aria-hidden="true"></i>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Department Rail (Hidden on Mobile) -->
                        <div class="d-none d-lg-block">
                            <div class="d-flex align-items-center justify-content-between mb-2" id="desktopDeptHeader">
                                <div class="text-uppercase text-muted" style="font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em;">
                                    Browse by Food Department
                                </div>
                                <?php if (!empty($category) || !empty($search)): ?>
                                    <a href="./" class="small text-success text-decoration-none fw-bold" id="showAllFoodsBtn">Show All (<?= $totalFoodCount ?>)</a>
                                <?php endif; ?>
                            </div>

                            <div class="food-aisles-scroller">
                                <a href="./" class="food-aisle-pill <?= (empty($category) && empty($search)) ? 'active' : '' ?>" data-category="">
                                    <i class="bi bi-grid-fill"></i>
                                    <span>All Departments</span>
                                    <span class="aisle-count"><?= $totalFoodCount ?></span>
                                </a>

                                <!-- Desktop Search Pill beside All Departments -->
                                <form action="./" method="GET" class="food-aisle-search-form">
                                    <div class="food-aisle-search-pill <?= !empty($search) ? 'has-value' : '' ?>">
                                        <i class="bi bi-search search-icon" aria-hidden="true"></i>
                                        <input type="text" name="search" class="food-aisle-search-input" placeholder="Search foods..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                                        <?php if (!empty($category)): ?>
                                            <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                                        <?php endif; ?>
                                        <?php if (!empty($sort)): ?>
                                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                                        <?php endif; ?>
                                        <?php if (!empty($search)): ?>
                                            <a href="<?= catalog_url(1, '', $category, $sort) ?>" class="search-clear-pill-btn" aria-label="Clear search" title="Clear search">
                                                <i class="bi bi-x"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>

                                <?php foreach ($categories as $catName): 
                                    $catCount = $categoryCounts[$catName] ?? 0;
                                    $iconClass = $categoryIcons[$catName] ?? 'bi-basket2';
                                    $isCatActive = ($category === $catName);
                                ?>
                                    <a href="?category=<?= urlencode($catName) ?>" class="food-aisle-pill <?= $isCatActive ? 'active' : '' ?>" data-category="<?= htmlspecialchars($catName) ?>">
                                        <i class="bi <?= $iconClass ?>"></i>
                                        <span><?= htmlspecialchars($catName) ?></span>
                                        <span class="aisle-count"><?= $catCount ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 3. Peak Harvest Culinary Spotlight (Featured 3-Card Showcase) -->
                <?php if (empty($category) && empty($search) && !empty($spotlightItems)): ?>
                <section class="harvest-spotlight-section" id="peak-harvest">
                    <div class="container">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-uppercase text-success" style="font-size: 0.72rem; font-weight: 800; letter-spacing: 0.08em;">Daily Chef &amp; Grower Selection</div>
                                <h2 style="font-family: var(--font-serif); font-size: 1.45rem; font-weight: 700; color: #1c1917; margin: 0;">Today's Peak Harvest Spotlights</h2>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="spotlight-counter badge rounded-pill bg-white text-success border border-success-subtle px-2 py-1 shadow-sm d-lg-none" id="spotlightCounter" style="font-size: 0.75rem; font-weight: 800;">1 / <?= count($spotlightItems) ?></span>
                                <span class="text-muted small d-none d-lg-inline"><i class="bi bi-stars text-warning me-1"></i> Harvested at optimal nutrient density</span>
                            </div>
                        </div>

                        <div class="spotlight-carousel-stage">
                            <div class="spotlight-grid" id="spotlightGrid">
                            <?php foreach ($spotlightItems as $idx => $sItem): 
                                $sCat = $sItem['category'] ?? 'Pantry';
                                $sMeta = $farmOrigins[$sCat] ?? [
                                    'farm' => 'Local Organic Producer',
                                    'telemetry' => 'Harvested Fresh',
                                    'badge' => 'Certified Organic',
                                    'notes' => 'Selected for peak seasonal flavor.'
                                ];
                                $isMain = ($idx === 0);
                            ?>
                                <div class="spotlight-food-card <?= $isMain ? 'featured-spotlight' : '' ?>" data-index="<?= $idx ?>">
                                    <div>
                                        <div class="spotlight-badge-top">
                                            <i class="bi bi-patch-check-fill"></i>
                                            <span><?= htmlspecialchars($sMeta['badge']) ?></span>
                                        </div>

                                        <div class="spotlight-img-wrap">
                                            <?php 
                                            $sHasLocal = !empty($sItem['image']) && file_exists(__DIR__ . '/assets/images/' . $sItem['image']);
                                            $sHasUrl = !empty($sItem['image_url']);
                                            $sImgSrc = $sHasUrl ? $sItem['image_url'] : ($sHasLocal ? 'assets/images/' . $sItem['image'] : '');
                                            if (!empty($sImgSrc)):
                                            ?>
                                                <img src="<?= htmlspecialchars($sImgSrc) ?>" alt="<?= htmlspecialchars($sItem['name']) ?>" loading="lazy">
                                            <?php else: ?>
                                                <div class="food-card-fallback-icon" aria-hidden="true">
                                                    <i class="bi <?= $categoryIcons[$sCat] ?? 'bi-basket2' ?>"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <h3 class="spotlight-item-name"><?= htmlspecialchars($sItem['name']) ?></h3>
                                        <p class="spotlight-tasting-notes"><?= htmlspecialchars($sMeta['notes']) ?></p>
                                        <div class="spotlight-farm-origin">
                                            <i class="bi bi-geo-alt-fill text-success"></i>
                                            <span><?= htmlspecialchars($sMeta['farm']) ?> &bull; <?= htmlspecialchars($sMeta['telemetry']) ?></span>
                                        </div>
                                    </div>

                                    <div class="spotlight-card-footer">
                                        <div class="spotlight-price">
                                            $<?= number_format($sItem['price'], 2) ?>
                                            <small>/ unit</small>
                                        </div>

                                        <form action="cart/add" method="POST" class="add-cart-form d-inline m-0">
                                            <input type="hidden" name="product_id" value="<?= $sItem['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                                                <i class="bi bi-plus-lg"></i> Add
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Mobile Swipe Indicator Dots & Navigation Controller -->
                        <div class="spotlight-dots-wrap d-flex d-lg-none justify-content-center align-items-center gap-3 mt-3" id="spotlightDots">
                            <button type="button" class="spotlight-dot-nav-btn" id="spotlightBottomPrev" onclick="navigateSpotlight(-1)" aria-label="Previous spotlight card">
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </button>
                            <div class="spotlight-dots-track d-flex align-items-center gap-2">
                                <?php foreach ($spotlightItems as $idx => $sItem): ?>
                                    <button type="button" class="spotlight-dot <?= ($idx === 0) ? 'active' : '' ?>" data-index="<?= $idx ?>" onclick="goToSpotlight(<?= $idx ?>)" aria-label="Go to spotlight slide <?= $idx + 1 ?>"></button>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="spotlight-dot-nav-btn" id="spotlightBottomNext" onclick="navigateSpotlight(1)" aria-label="Next spotlight card">
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <!-- 4. Strictly Foods Catalog Grid -->
                <section class="market-catalog-section" id="all-foods">
                    <div class="container">
                        <div class="market-catalog-header">
                            <div>
                                <h2 class="catalog-section-title">
                                    <?= !empty($category) ? htmlspecialchars($category) . ' Aisle' : (!empty($search) ? 'Search results for &ldquo;' . htmlspecialchars($search) . '&rdquo;' : 'All Harvest Provisions') ?>
                                </h2>
                                <span class="text-muted small">Showing <?= count($products) ?> of <?= $totalItems ?> items</span>
                            </div>

                            <!-- Quick Sort Toolbar -->
                            <div class="market-catalog-sort-wrap d-flex align-items-center gap-2">
                                <span class="text-muted small fw-semibold">Sort:</span>
                                <div class="btn-group btn-group-sm rounded-pill p-1 bg-white border">
                                    <a href="<?= catalog_url(1, $search, $category, '') ?>" class="btn btn-sm <?= empty($sort) ? 'btn-success text-white' : 'btn-light border-0' ?> rounded-pill px-3">Featured</a>
                                    <a href="<?= catalog_url(1, $search, $category, 'price_asc') ?>" class="btn btn-sm <?= ($sort === 'price_asc') ? 'btn-success text-white' : 'btn-light border-0' ?> rounded-pill px-3">Price &uarr;</a>
                                    <a href="<?= catalog_url(1, $search, $category, 'price_desc') ?>" class="btn btn-sm <?= ($sort === 'price_desc') ? 'btn-success text-white' : 'btn-light border-0' ?> rounded-pill px-3">Price &darr;</a>
                                    <a href="<?= catalog_url(1, $search, $category, 'alpha') ?>" class="btn btn-sm <?= ($sort === 'alpha') ? 'btn-success text-white' : 'btn-light border-0' ?> rounded-pill px-3">A-Z</a>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($products)): ?>
                            <div class="text-center py-5 bg-white border rounded-4">
                                <i class="bi bi-basket text-muted" style="font-size: 3rem;"></i>
                                <h4 class="mt-3 fw-bold">No foods found in this aisle</h4>
                                <p class="text-muted small">Try selecting another department or clearing your search term.</p>
                                <a href="./" class="btn btn-outline-success rounded-pill px-4 mt-2">Browse All Foods</a>
                            </div>
                        <?php else: ?>
                            <div class="food-grid">
                                <?php foreach ($products as $prod): 
                                    $pCat = $prod['category'] ?? 'Pantry';
                                    $pMeta = $farmOrigins[$pCat] ?? [
                                        'farm' => 'Local Organic Producer',
                                        'telemetry' => 'Fresh Sourced'
                                    ];
                                    $hasLocalImg = !empty($prod['image']) && file_exists(__DIR__ . '/assets/images/' . $prod['image']);
                                    $hasUrlImg = !empty($prod['image_url']);
                                    $imgSrc = $hasUrlImg ? $prod['image_url'] : ($hasLocalImg ? 'assets/images/' . $prod['image'] : '');
                                ?>
                                    <div class="food-card">
                                        <div>
                                            <div class="food-card-top-tags">
                                                <span class="food-card-cat-tag"><?= htmlspecialchars($prod['category']) ?></span>
                                                <span class="food-card-stock"><i class="bi bi-check-circle-fill me-1"></i>In Stock</span>
                                            </div>

                                            <div class="food-card-img-wrap">
                                                <?php if (!empty($imgSrc)): ?>
                                                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" loading="lazy">
                                                <?php else: ?>
                                                    <div class="food-card-fallback-icon" aria-hidden="true">
                                                        <i class="bi <?= $categoryIcons[$pCat] ?? 'bi-basket2' ?>"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <h3 class="food-card-title"><?= htmlspecialchars($prod['name']) ?></h3>
                                            
                                            <div class="food-card-origin-meta">
                                                <i class="bi bi-geo-alt-fill text-success" aria-hidden="true"></i>
                                                <span><?= htmlspecialchars($pMeta['farm']) ?></span>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="food-card-price-row">
                                                <div>
                                                    <span class="food-card-price">$<?= number_format($prod['price'], 2) ?></span>
                                                    <span class="food-card-unit">/ unit</span>
                                                </div>
                                                <span class="small text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($pMeta['telemetry']) ?></span>
                                            </div>

                                            <form action="cart/add" method="POST" class="add-cart-form m-0">
                                                <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                <button type="submit" class="food-card-btn-add">
                                                    <i class="bi bi-plus-lg"></i> Add to Basket
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4 mt-md-5">
                                    <!-- Mobile Pagination: Clean single-row bar, no awkward line wrap -->
                                    <div class="mobile-pagination-bar d-flex d-md-none align-items-center justify-content-between">
                                        <a class="mobile-page-btn <?= ($page <= 1) ? 'disabled' : '' ?>" href="<?= ($page > 1) ? catalog_url($page - 1, $search, $category, $sort) : '#' ?>" aria-label="Previous page">
                                            <i class="bi bi-chevron-left me-1"></i> Prev
                                        </a>
                                        <div class="mobile-page-status">
                                            <span class="mobile-page-current">Page <?= $page ?></span>
                                            <span class="mobile-page-total">of <?= $totalPages ?></span>
                                            <div class="mobile-page-count text-muted"><?= $totalItems ?> items</div>
                                        </div>
                                        <a class="mobile-page-btn <?= ($page >= $totalPages) ? 'disabled' : '' ?>" href="<?= ($page < $totalPages) ? catalog_url($page + 1, $search, $category, $sort) : '#' ?>" aria-label="Next page">
                                            Next <i class="bi bi-chevron-right ms-1"></i>
                                        </a>
                                    </div>

                                    <!-- Desktop Pagination: Custom green styling -->
                                    <ul class="pagination custom-pagination justify-content-center align-items-center gap-1 d-none d-md-flex m-0">
                                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link rounded-pill px-3" href="<?= catalog_url($page - 1, $search, $category, $sort) ?>">
                                                <i class="bi bi-chevron-left me-1"></i> Previous
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                                <a class="page-link rounded-circle mx-1 text-center" href="<?= catalog_url($i, $search, $category, $sort) ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link rounded-pill px-3" href="<?= catalog_url($page + 1, $search, $category, $sort) ?>">
                                                Next <i class="bi bi-chevron-right ms-1"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>

            </div>
        </main>

        <!-- 5. Floating Persistent Quick Cart Bar (Bottom Right) -->
        <div id="floatingCartTray" class="floating-cart-tray <?= ($cartCount > 0) ? '' : 'is-hidden' ?>">
            <div class="floating-cart-info">
                <div class="floating-cart-icon-badge">
                    <i class="bi bi-basket2-fill"></i>
                    <span id="floatCartBadge" class="floating-cart-badge-val"><?= $cartCount ?></span>
                </div>
                <div>
                    <div class="text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;">Your Basket</div>
                    <div id="floatCartTotalText" class="floating-cart-total-text">$<?= number_format($cartSubtotal, 2) ?></div>
                </div>
            </div>
            <div class="floating-cart-actions">
                <a href="cart" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">View Cart</a>
                <a href="checkout" class="floating-cart-checkout-btn">
                    <span>Checkout</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

    <?php else: ?>
    <header class="hero-overdrive" id="hero-overdrive">
        <div class="hero-scrim"></div>

        <div class="container hero-content-overdrive">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-kicker"><i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>100% Certified Organic &bull; Local Harvest</div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="hero-member-kicker mb-2">
                            <span class="member-live-dot" aria-hidden="true"></span>
                            <span>MEMBER ACCESS &bull; WELCOME BACK, <?= strtoupper(htmlspecialchars($_SESSION['user_name'])) ?></span>
                        </div>
                        <h1 class="hero-title-od">Restock your kitchen directly from local growers.</h1>
                        <p class="hero-lead-od">Seasonal produce, artisan cheeses, and morning-baked loaves picked at peak flavor and delivered to your doorstep.</p>
                    <?php else: ?>
                        <h1 class="hero-title-od">Clean food from certified local growers.</h1>
                        <p class="hero-lead-od">Harvested within 24 hours. Certified organic produce, pasture-raised dairy, and artisan baked goods delivered directly to your door.</p>
                    <?php endif; ?>

                    <div class="hero-cta-group">
                        <a href="#catalog" class="hero-btn-primary">
                            <span>Start Shopping</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="orders" class="hero-btn-secondary">
                                <i class="bi bi-receipt" aria-hidden="true"></i>
                                <span>My Orders</span>
                            </a>
                        <?php else: ?>
                            <a href="#categories" class="hero-btn-secondary">
                                <i class="bi bi-compass" aria-hidden="true"></i>
                                <span>Browse Aisles</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="hero-trust-rail">
                        <div class="trust-item">
                            <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                            <span>Certified Organic</span>
                        </div>
                        <div class="trust-item">
                            <i class="bi bi-clock-history" aria-hidden="true"></i>
                            <span>24h Farm to Door</span>
                        </div>
                        <div class="trust-item">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            <span>100% Pesticide Free</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mt-5 mt-lg-0">
                    <div class="hero-showcase-card">
                        <img src="assets/images/gulay.jpg" id="dynamic-hero-img" class="hero-showcase-img" width="520" height="390" alt="Harvested Fresh Produce">
                        <div class="hero-telemetry-badge">
                            <div class="telemetry-item">
                                <span class="telemetry-label">Farm Origin</span>
                                <span class="telemetry-value"><i class="bi bi-geo-alt-fill text-success" aria-hidden="true"></i> Sun Valley Organics</span>
                            </div>
                            <div class="telemetry-item text-end">
                                <span class="telemetry-label">Harvested</span>
                                <span class="telemetry-value"><i class="bi bi-clock-fill text-primary" aria-hidden="true"></i> 3 Hours Ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="site-main-content">

        <!-- =========================================================
             INTERACTIVE VISUAL AISLE EXPLORER (#shop / #categories)
             ========================================================= -->
        <!-- =========================================================
             INTERACTIVE VISUAL AISLE EXPLORER (3D COVERFLOW) (#shop / #categories)
             ========================================================= -->
        <section class="aisle-explorer-section" id="shop">
            <div class="container" id="categories">
                
                <div class="aisle-header">
                    <div class="section-kicker">CURATED AISLES &bull; DIRECT SOURCING</div>
                    <h2 class="aisle-heading">Explore our harvest aisles.</h2>
                    <p class="aisle-subtitle">Connected directly to independent regional family farms. Select an aisle to filter our harvest with 3D coverflow preview and one-click cart additions.</p>
                </div>

                <!-- Aisle Navigation Rail (Desktop & Tablet: Clean Category Chips) -->
                <div class="aisle-nav-container d-none d-md-flex" id="categoryRail">
                    <?php 
                    $firstKey = array_key_first($aisleReels);
                    foreach ($aisleReels as $catKey => $reelData): 
                        $isActive = ($catKey === $firstKey);
                    ?>
                        <button type="button" class="cat-chip <?= $isActive ? 'active' : '' ?>" data-category="<?= htmlspecialchars($catKey) ?>" onclick="selectAisle('<?= htmlspecialchars($catKey) ?>')">
                            <i class="bi <?= $reelData['meta']['icon'] ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($reelData['meta']['label'] ?? $catKey) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Aisle Navigation Dropdown (Mobile Viewport: Custom Tactile Menu) -->
                <div class="aisle-mobile-dropdown-wrap d-md-none" id="aisleMobileDropdownWrap">
                    <div class="aisle-custom-dropdown" id="aisleCustomDropdown">
                        <button type="button" 
                                class="aisle-select-pill" 
                                id="aisleDropdownTrigger" 
                                aria-haspopup="listbox" 
                                aria-expanded="false" 
                                aria-controls="aisleDropdownMenu"
                                onclick="toggleAisleDropdown()" 
                                aria-label="Select harvest aisle">
                            <span class="aisle-select-icon"><i class="bi <?= $aisleReels[$firstKey]['meta']['icon'] ?? 'bi-apple' ?>" id="aisleSelectActiveIcon" aria-hidden="true"></i></span>
                            <span class="aisle-select-label" id="aisleSelectActiveLabel"><?= htmlspecialchars($aisleReels[$firstKey]['meta']['label'] ?? $firstKey) ?></span>
                            <i class="bi bi-chevron-down aisle-select-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="aisle-dropdown-menu" id="aisleDropdownMenu" role="listbox" aria-label="Harvest aisles">
                            <?php foreach ($aisleReels as $catKey => $reelData): 
                                $isSelected = ($catKey === $firstKey);
                            ?>
                                <button type="button" 
                                        role="option" 
                                        aria-selected="<?= $isSelected ? 'true' : 'false' ?>" 
                                        class="aisle-dropdown-item <?= $isSelected ? 'selected' : '' ?>" 
                                        data-category="<?= htmlspecialchars($catKey) ?>" 
                                        onclick="chooseAisleMobile('<?= htmlspecialchars($catKey) ?>')">
                                    <span class="aisle-item-icon"><i class="bi <?= $reelData['meta']['icon'] ?? 'bi-basket2' ?>" aria-hidden="true"></i></span>
                                    <span class="aisle-item-text"><?= htmlspecialchars($reelData['meta']['label'] ?? $catKey) ?></span>
                                    <i class="bi bi-check2 aisle-item-check" aria-hidden="true"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="department-showcase" id="activeAisleShowcase">
                    <!-- 3D Coverflow Stage (21st.dev @ruixen.ui/coverflow-carousel) -->
                    <div class="coverflow-stage-wrapper" id="coverflowStageWrapper">
                        <button type="button" class="coverflow-nav-btn coverflow-prev-btn" onclick="prevCover()" aria-label="Previous harvest item">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        </button>
                        <div class="coverflow-stage" id="coverflowStage">
                            <!-- Injected dynamically via JS with 3D transform rack -->
                        </div>
                        <button type="button" class="coverflow-nav-btn coverflow-next-btn" onclick="nextCover()" aria-label="Next harvest item">
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Dots Navigation Indicator -->
                    <div class="coverflow-dots-bar" id="coverflowDots"></div>
                </div>

            </div>
        </section>

        <!-- =========================================================
             21st.dev EXTRACTED COMPONENT: MARQUEE TESTIMONIAL CARDS
             Real positive verified order responses from FreshCart customers
             ========================================================= -->
        <section class="marquee-testimonials-section" id="catalog">
            <div class="container-fluid px-0">
                
                <div class="marquee-testimonials-header">
                    <div class="marquee-header-kicker">
                        <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                        <span>Verified Customer Experiences &bull; FreshCart Community</span>
                    </div>
                    <h2 class="marquee-header-title">Loved by conscious tables across the countryside.</h2>
                    <p class="marquee-header-sub">Hear directly from home cooks, sourdough bakers, and families who receive their harvest orders fresh from our independent growers every single week.</p>
                </div>

                <div class="marquee-container-wrapper">
                    <!-- Edge gradient blur masks for signature 21st.dev infinite fade -->
                    <div class="marquee-edge-fade-left" aria-hidden="true"></div>
                    <div class="marquee-edge-fade-right" aria-hidden="true"></div>

                    <!-- Row 1: Forward Marquee -->
                    <div class="marquee-row-track" aria-label="Customer Reviews Carousel Row 1">
                        <?php 
                        $testimonialsRow1 = [
                            [
                                'name' => 'Briar Martin',
                                'handle' => '@briar.cooks',
                                'image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Heirloom Apples & Raw Cream',
                                'stars' => 5,
                                'text' => 'The heirloom apples and pasture cream arrived within 18 hours of harvest. The crispness is unlike anything at supermarkets—our Sunday breakfasts are completely transformed!'
                            ],
                            [
                                'name' => 'Avery Johnson',
                                'handle' => '@avery_homestead',
                                'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Weekly Sourdough & Eggs',
                                'stars' => 5,
                                'text' => 'Switching our weekly grocery to FreshCart eliminated our food waste entirely. Produce stays crisp in the crisper for two weeks because it never sat in transit storage.'
                            ],
                            [
                                'name' => 'Jordan Lee',
                                'handle' => '@jordan_bakes',
                                'image' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Stone-Ground Flour & Butter',
                                'stars' => 5,
                                'text' => 'I bake sourdough weekly for neighborhood pop-ups. Getting unpasteurized farm butter and stone-ground grains delivered on a reliable Tuesday schedule has elevated my loaves.'
                            ],
                            [
                                'name' => 'Elena Rostova',
                                'handle' => '@elena_wellness',
                                'image' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Organic Field Greens Crate',
                                'stars' => 5,
                                'text' => 'The salad greens arrived still cold with morning dew. 100% compostable mycelium packaging with zero plastic film. Unbelievable dedication to real sustainability!'
                            ],
                            [
                                'name' => 'Marcus Chen',
                                'handle' => '@marcus_culinary',
                                'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Grass-Fed Butter & Raw Honey',
                                'stars' => 5,
                                'text' => 'Knowing the exact family farm and harvest coordinates of every item gives our kitchen total confidence. FreshCart has set a standard that grocery stores cannot match.'
                            ]
                        ];
                        // Double for infinite seamless marquee loop
                        $doubledRow1 = array_merge($testimonialsRow1, $testimonialsRow1);
                        foreach ($doubledRow1 as $review): 
                        ?>
                            <div class="marquee-testimonial-card">
                                <div>
                                    <div class="marquee-card-header">
                                        <img src="<?= htmlspecialchars($review['image']) ?>" class="marquee-card-avatar" alt="<?= htmlspecialchars($review['name']) ?>" loading="lazy" width="44" height="44">
                                        <div class="marquee-card-user-info">
                                            <div class="marquee-card-name-row">
                                                <h4 class="marquee-card-name"><?= htmlspecialchars($review['name']) ?></h4>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 48 48" class="marquee-card-verify-icon" aria-label="Verified Customer">
                                                    <polygon fill="#4A745B" points="29.62,3 33.053,8.308 39.367,8.624 39.686,14.937 44.997,18.367 42.116,23.995 45,29.62 39.692,33.053 39.376,39.367 33.063,39.686 29.633,44.997 24.005,42.116 18.38,45 14.947,39.692 8.633,39.376 8.314,33.063 3.003,29.633 5.884,24.005 3,18.38 8.308,14.947 8.624,8.633 14.937,8.314 18.367,3.003 23.995,5.884"></polygon>
                                                    <polygon fill="#fff" points="21.396,31.255 14.899,24.76 17.021,22.639 21.428,27.046 30.996,17.772 33.084,19.926"></polygon>
                                                </svg>
                                            </div>
                                            <span class="marquee-card-handle"><?= htmlspecialchars($review['handle']) ?></span>
                                        </div>
                                    </div>
                                    <div class="marquee-card-order-meta">
                                        <span class="marquee-card-item-tag">
                                            <i class="bi bi-bag-check-fill" aria-hidden="true"></i><?= htmlspecialchars($review['item']) ?>
                                        </span>
                                        <div class="marquee-card-stars" aria-label="5 stars rating">
                                            <?php for ($s = 0; $s < 5; $s++): ?>
                                                <i class="bi bi-star-fill" aria-hidden="true"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="marquee-card-review-text">&ldquo;<?= htmlspecialchars($review['text']) ?>&rdquo;</p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Row 2: Reverse Direction Marquee -->
                    <div class="marquee-row-track is-reverse" aria-label="Customer Reviews Carousel Row 2">
                        <?php 
                        $testimonialsRow2 = [
                            [
                                'name' => 'Claire Beauchamp',
                                'handle' => '@claire_table',
                                'image' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Seasonal Harvest Crate',
                                'stars' => 5,
                                'text' => 'My kids actually ask for roasted baby carrots and snap peas now! When vegetables are grown in nutrient-dense mineral soil, the natural sweetness is on another level.'
                            ],
                            [
                                'name' => 'Chef Julian Vance',
                                'handle' => '@julianvance_chef',
                                'image' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Pasture Butter & Heirloom Berries',
                                'stars' => 5,
                                'text' => 'As a private chef, sourcing is everything. FreshCart direct grower dispatch cuts out multiple distributors, giving my kitchen farm-to-table freshness impossible to beat.'
                            ],
                            [
                                'name' => 'Amara Okafor',
                                'handle' => '@amara_nourish',
                                'image' => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Orchard Fruit & Pasture Eggs',
                                'stars' => 5,
                                'text' => 'Ordered on Wednesday morning and had our chilled crate at the door before dinner. Everything was packed with the care of fine botanical art. 10/10!'
                            ],
                            [
                                'name' => 'Liam Gallagher',
                                'handle' => '@liam_homecook',
                                'image' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Field Root Vegetables',
                                'stars' => 5,
                                'text' => 'Zero chemical taste, zero synthetic wax coatings. Just crisp, honest roots and fragrant herbs that make cooking dinner the absolute highlight of our week.'
                            ],
                            [
                                'name' => 'Hannah Lindqvist',
                                'handle' => '@hannah_simplelife',
                                'image' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=150&auto=format&fit=crop&q=80',
                                'item' => 'Farmstead Raw Preserves',
                                'stars' => 5,
                                'text' => 'The raw elderberry conserves and cold-pressed olive oils are divine. You can truly taste the integrity in every single bite. Will be a lifelong subscriber!'
                            ]
                        ];
                        // Double for infinite seamless marquee loop
                        $doubledRow2 = array_merge($testimonialsRow2, $testimonialsRow2);
                        foreach ($doubledRow2 as $review): 
                        ?>
                            <div class="marquee-testimonial-card">
                                <div>
                                    <div class="marquee-card-header">
                                        <img src="<?= htmlspecialchars($review['image']) ?>" class="marquee-card-avatar" alt="<?= htmlspecialchars($review['name']) ?>" loading="lazy" width="44" height="44">
                                        <div class="marquee-card-user-info">
                                            <div class="marquee-card-name-row">
                                                <h4 class="marquee-card-name"><?= htmlspecialchars($review['name']) ?></h4>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 48 48" class="marquee-card-verify-icon" aria-label="Verified Customer">
                                                    <polygon fill="#4A745B" points="29.62,3 33.053,8.308 39.367,8.624 39.686,14.937 44.997,18.367 42.116,23.995 45,29.62 39.692,33.053 39.376,39.367 33.063,39.686 29.633,44.997 24.005,42.116 18.38,45 14.947,39.692 8.633,39.376 8.314,33.063 3.003,29.633 5.884,24.005 3,18.38 8.308,14.947 8.624,8.633 14.937,8.314 18.367,3.003 23.995,5.884"></polygon>
                                                    <polygon fill="#fff" points="21.396,31.255 14.899,24.76 17.021,22.639 21.428,27.046 30.996,17.772 33.084,19.926"></polygon>
                                                </svg>
                                            </div>
                                            <span class="marquee-card-handle"><?= htmlspecialchars($review['handle']) ?></span>
                                        </div>
                                    </div>
                                    <div class="marquee-card-order-meta">
                                        <span class="marquee-card-item-tag">
                                            <i class="bi bi-bag-check-fill" aria-hidden="true"></i><?= htmlspecialchars($review['item']) ?>
                                        </span>
                                        <div class="marquee-card-stars" aria-label="5 stars rating">
                                            <?php for ($s = 0; $s < 5; $s++): ?>
                                                <i class="bi bi-star-fill" aria-hidden="true"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="marquee-card-review-text">&ldquo;<?= htmlspecialchars($review['text']) ?>&rdquo;</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </section>

        <section class="bento-section" id="story">
            <div class="container">
                <div class="bento-header">
                    <div class="section-kicker">
                        <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>
                        <span>Direct Agricultural Sourcing &bull; Soil to Threshold</span>
                    </div>
                    <h2 class="bento-heading">Radical transparency from soil to doorstep.</h2>
                    <p class="bento-subtitle">No multi-week cold storage or speculative broker networks. Every harvest is sourced directly from independent family farms practicing verified regenerative agriculture.</p>
                </div>

                <div class="bento-matrix">
                    <!-- Bento Card 1: 42 Local Farms / Live Farm-to-Door Traceability -->
                    <div class="bento-card bento-col-8 bento-hero-card">
                        <div class="bento-spotlight"></div>
                        <div class="bento-card-content">
                            <div class="bento-metric-large">42 Local Farms</div>
                            <h3 class="bento-card-title">Direct Grower Traceability</h3>
                            <p class="bento-card-desc">Every vegetable and carton of milk links directly to the family farm that harvested it. Scan product QR codes to inspect parcel origin, soil minerals, and morning picking timestamps.</p>
                            
                            <!-- Interactive Live Farm Telemetry Preview -->
                            <div class="bento-live-farm-preview">
                                <img src="assets/images/gulay1.jpg" alt="Misty Morning Organic Farm" class="bento-farm-img" loading="lazy" width="800" height="340">
                                <div class="bento-scrim-overlay"></div>
                                <div class="bento-farm-tag">
                                    <i class="bi bi-patch-check-fill text-brand me-1" aria-hidden="true"></i>
                                    <span>Misty Morning Organic Farm</span>
                                </div>
                                <div class="bento-telemetry-glass-badge">
                                    <div class="telemetry-badge-row">
                                        <div class="telemetry-badge-item">
                                            <span class="badge-item-label">Origin Farm</span>
                                            <strong class="badge-item-value"><i class="bi bi-geo-alt-fill me-1 text-brand"></i>Highland Valley · Parcel 4B</strong>
                                        </div>
                                        <div class="telemetry-badge-divider"></div>
                                        <div class="telemetry-badge-item">
                                            <span class="badge-item-label">Harvest Time</span>
                                            <strong class="badge-item-value"><i class="bi bi-clock-fill me-1 text-brand"></i>5:45 AM Today</strong>
                                        </div>
                                        <div class="telemetry-badge-divider"></div>
                                        <div class="telemetry-badge-item">
                                            <span class="badge-item-label">Soil Quality</span>
                                            <strong class="badge-item-value"><i class="bi bi-moisture me-1 text-brand"></i>Living Loam (pH 6.8)</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bento Card 2: 100% Pesticide Free Living Soil -->
                    <div class="bento-card bento-col-4">
                        <div class="bento-spotlight"></div>
                        <div class="bento-card-content">
                            <div class="bento-metric-large">100%</div>
                            <h3 class="bento-card-title">Pesticide Free Soil</h3>
                            <p class="bento-card-desc">Zero chemical insecticides, petroleum fertilizers, or synthetic waxes. Living microbial soil produces dense root networks with remarkable natural flavor.</p>
                            
                            <!-- Soil Telemetry & Nutrient Density Bars -->
                            <div class="bento-soil-metrics-card">
                                <div class="soil-metric-item">
                                    <div class="soil-metric-header">
                                        <span>Antioxidant &amp; Polyphenol Density</span>
                                        <strong class="text-brand">+340%</strong>
                                    </div>
                                    <div class="soil-progress-bar">
                                        <div class="soil-progress-fill" style="width: 88%;"></div>
                                    </div>
                                </div>
                                <div class="soil-metric-item">
                                    <div class="soil-metric-header">
                                        <span>Soil Organic Matter Content</span>
                                        <strong class="text-brand">8.4%</strong>
                                    </div>
                                    <div class="soil-progress-bar">
                                        <div class="soil-progress-fill" style="width: 76%;"></div>
                                    </div>
                                </div>
                                <div class="soil-metric-item">
                                    <div class="soil-metric-header">
                                        <span>Synthetic Chemical Residue</span>
                                        <strong class="text-brand">0.00 ppm</strong>
                                    </div>
                                    <div class="soil-progress-bar">
                                        <div class="soil-progress-fill bg-brand" style="width: 0%;"></div>
                                    </div>
                                </div>
                                <div class="soil-cert-tag">
                                    <i class="bi bi-patch-check-fill text-brand" aria-hidden="true"></i>
                                    <span>Regenerative Organic Certified™ (ROC) Standards</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bento Card 3: 4°C Continuous Cold-Chain Transit -->
                    <div class="bento-card bento-col-6">
                        <div class="bento-spotlight"></div>
                        <div class="bento-card-content">
                            <div class="bento-metric-large">4&deg;C Guaranteed</div>
                            <h3 class="bento-card-title">Continuous Cold-Chain Transit</h3>
                            <p class="bento-card-desc">Insulated mycelium crates and renewable gel packs maintain refrigeration from field harvest to threshold, ensuring produce never suffers heat degradation.</p>

                            <!-- Live Transit Timeline & Thermal Reading -->
                            <div class="bento-transit-timeline-card">
                                <div class="transit-temp-readout">
                                    <div class="temp-readout-left">
                                        <span class="sensor-pulse-dot" aria-hidden="true"></span>
                                        <span class="temp-readout-label">Real-Time Crate Sensor</span>
                                    </div>
                                    <span class="temp-readout-val">3.8&deg;C <small class="text-brand fw-semibold">&bull; Optimal Freshness</small></span>
                                </div>
                                <div class="transit-step-chain">
                                    <div class="transit-node is-done">
                                        <div class="node-marker"><i class="bi bi-check2"></i></div>
                                        <div class="node-text">
                                            <strong>05:45 AM</strong>
                                            <span>Field Harvest</span>
                                        </div>
                                    </div>
                                    <div class="transit-connector"></div>
                                    <div class="transit-node is-done">
                                        <div class="node-marker"><i class="bi bi-check2"></i></div>
                                        <div class="node-text">
                                            <strong>08:15 AM</strong>
                                            <span>Cold Packed 4°C</span>
                                        </div>
                                    </div>
                                    <div class="transit-connector"></div>
                                    <div class="transit-node is-active">
                                        <div class="node-marker"><i class="bi bi-truck"></i></div>
                                        <div class="node-text">
                                            <strong>&lt; 24h Delivery</strong>
                                            <span>At Your Door</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bento Card 4: 78% Direct Revenue Return to Growers -->
                    <div class="bento-card bento-col-6">
                        <div class="bento-spotlight"></div>
                        <div class="bento-card-content">
                            <div class="bento-metric-large">78% Revenue Return</div>
                            <h3 class="bento-card-title">Fair Price Direct to Growers</h3>
                            <p class="bento-card-desc">Conventional grocery models leave family farmers with less than 15 cents on the dollar. FreshCart delivers 78% directly to producers, securing sustainable farming for the next generation.</p>

                            <!-- Economic Model Comparison & Farmer Voice -->
                            <div class="bento-economics-wrap">
                                <div class="econ-comparison-row">
                                    <div class="econ-bar-label-group">
                                        <span class="econ-brand-label"><i class="bi bi-cart-check-fill text-brand me-1"></i>FreshCart Direct Model</span>
                                        <span class="econ-percentage text-brand">78% to Farmers</span>
                                    </div>
                                    <div class="econ-segmented-bar">
                                        <div class="bar-segment bar-farmer" style="width: 78%;" title="78% Direct to Independent Farmers">78%</div>
                                        <div class="bar-segment bar-logistics" style="width: 12%;" title="12% Cold-Chain Transit">12%</div>
                                        <div class="bar-segment bar-platform" style="width: 10%;" title="10% Operations">10%</div>
                                    </div>
                                    <div class="econ-breakdown-legend">
                                        <span class="legend-item"><span class="legend-dot bg-brand"></span>78% Grower</span>
                                        <span class="legend-item"><span class="legend-dot bg-logistics"></span>12% Transit</span>
                                        <span class="legend-item"><span class="legend-dot bg-platform"></span>10% Platform</span>
                                    </div>
                                </div>
                                <div class="econ-comparison-row">
                                    <div class="econ-bar-label-group">
                                        <span class="econ-brand-label text-muted"><i class="bi bi-building me-1"></i>Conventional Supermarkets</span>
                                        <span class="econ-percentage text-muted">14% to Farmers</span>
                                    </div>
                                    <div class="econ-segmented-bar is-conventional">
                                        <div class="bar-segment bar-comm-farmer" style="width: 14%;" title="14% Farmer Share">14%</div>
                                        <div class="bar-segment bar-comm-middle" style="width: 46%;" title="46% Middlemen & Wholesalers">46%</div>
                                        <div class="bar-segment bar-comm-markup" style="width: 40%;" title="40% Store Markup">40%</div>
                                    </div>
                                    <div class="econ-breakdown-legend is-conventional">
                                        <span class="legend-item"><span class="legend-dot bg-comm-farmer"></span>14% Farmers</span>
                                        <span class="legend-item"><span class="legend-dot bg-comm-middle"></span>46% Brokers</span>
                                        <span class="legend-item"><span class="legend-dot bg-comm-markup"></span>40% Store</span>
                                    </div>
                                </div>

                                <div class="bento-farmer-quote-row">
                                    <img src="https://images.unsplash.com/photo-1595974482597-4b8da8879bc5?w=160&auto=format&fit=crop&q=80" alt="Mateo Alvarez, Independent Organic Farmer" class="farmer-avatar-sm" loading="lazy" width="44" height="44">
                                    <div class="farmer-quote-content">
                                        <div class="farmer-quote-kicker">Mateo Alvarez, Independent Organic Farmer</div>
                                        <p class="farmer-quote-text">&ldquo;FreshCart’s direct revenue guarantee allowed our family to convert 40 additional acres to heirloom varieties without debt.&rdquo;</p>
                                        <span class="farmer-quote-author">Mateo &amp; Clara Alvarez &bull; Alvarez Organic Groves</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>
    <?php endif; ?>

    <footer class="site-footer" id="siteFooter">
        <!-- Top Agricultural Sourcing Trust Strip -->
        <div class="footer-trust-banner">
            <div class="container">
                <div class="footer-trust-grid">
                    <div class="footer-trust-item">
                        <span class="footer-trust-icon" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></span>
                        <div class="footer-trust-text">
                            <strong>Direct Farm Traceability</strong>
                            <span>Every item linked to parcel origin</span>
                        </div>
                    </div>
                    <div class="footer-trust-divider" aria-hidden="true"></div>
                    <div class="footer-trust-item">
                        <span class="footer-trust-icon" aria-hidden="true"><i class="bi bi-snow2"></i></span>
                        <div class="footer-trust-text">
                            <strong>4&deg;C Cold-Chain Transit</strong>
                            <span>Insulated zero-heat degraded packing</span>
                        </div>
                    </div>
                    <div class="footer-trust-divider" aria-hidden="true"></div>
                    <div class="footer-trust-item">
                        <span class="footer-trust-icon" aria-hidden="true"><i class="bi bi-patch-check-fill"></i></span>
                        <div class="footer-trust-text">
                            <strong>78% Direct Grower Revenue</strong>
                            <span>Fair price to independent family farms</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="row g-4 g-lg-5 footer-main-row">
                
                <!-- Col 1: Brand & Sourcing Statement -->
                <div class="col-lg-4 col-md-6 col-12 footer-brand-col">
                    <a href="./" class="footer-brand-anchor" aria-label="FreshCart Home">
                        <span class="footer-brand">FreshCart<span class="footer-brand-dot">.</span></span>
                    </a>
                    <p class="footer-brand-desc">
                        Direct agricultural sourcing from independent family farms practicing certified regenerative agriculture. No multi-week cold storage or speculative broker markups.
                    </p>
                    <div class="footer-social-wrap">
                        <a href="https://www.facebook.com/notagirlgamer69" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="FreshCart on Facebook">
                            <i class="bi bi-facebook" aria-hidden="true"></i>
                        </a>
                        <a href="https://www.instagram.com/janusinss/" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="FreshCart on Instagram">
                            <i class="bi bi-instagram" aria-hidden="true"></i>
                        </a>
                        <a href="https://x.com/Syrupynut" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="FreshCart on Twitter">
                            <i class="bi bi-twitter-x" aria-hidden="true"></i>
                        </a>
                        <a href="https://www.linkedin.com/in/janusdominic/" target="_blank" rel="noopener noreferrer" class="social-icon-link" aria-label="FreshCart on LinkedIn">
                            <i class="bi bi-linkedin" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                <!-- Col 2: Shop Catalog -->
                <div class="col-lg-2 col-md-3 col-6 footer-nav-col">
                    <h6 class="footer-heading">Shop Aisles</h6>
                    <ul class="footer-link-list">
                        <li><a href="#shop" class="footer-link">All Harvests</a></li>
                        <li><a href="#categories" class="footer-link">Fresh Produce</a></li>
                        <li><a href="#categories" class="footer-link">Dairy &amp; Eggs</a></li>
                        <li><a href="#categories" class="footer-link">Artisan Bakery</a></li>
                    </ul>
                </div>

                <!-- Col 3: Company & Integrity -->
                <div class="col-lg-2 col-md-3 col-6 footer-nav-col">
                    <h6 class="footer-heading">Standards</h6>
                    <ul class="footer-link-list">
                        <li><a href="about" class="footer-link">About Us</a></li>
                        <li><a href="sustainability" class="footer-link">Sustainability</a></li>
                        <li><a href="farmers" class="footer-link">Our Farmers</a></li>
                        <li><a href="contact" class="footer-link">Contact</a></li>
                    </ul>
                </div>

                <!-- Col 4: Newsletter & Verification -->
                <div class="col-lg-4 col-md-12 col-12 footer-newsletter-col">
                    <h6 class="footer-heading">Stay Fresh</h6>
                    <p class="footer-newsletter-desc">Morning harvest alerts and seasonal heirloom arrivals. Zero spam.</p>
                    
                    <form id="footerNewsletterForm" class="footer-newsletter-form" onsubmit="handleFooterNewsletter(event)">
                        <label for="footerEmailInput" class="visually-hidden">Email address</label>
                        <div class="footer-newsletter-group">
                            <input type="email" id="footerEmailInput" class="footer-email-input" placeholder="Enter your email" required autocomplete="email">
                            <button class="footer-subscribe-btn" type="submit" id="footerSubscribeBtn">
                                <span>Join</span>
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div id="footerNewsletterMsg" class="footer-newsletter-msg" role="status" aria-live="polite"></div>
                    </form>

                    <div class="footer-payment-section">
                        <span class="footer-payment-title">Guaranteed Secure Checkout</span>
                        <div class="footer-payment-badges">
                            <span class="payment-badge"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i> VISA</span>
                            <span class="payment-badge"><i class="bi bi-credit-card" aria-hidden="true"></i> Mastercard</span>
                            <span class="payment-badge"><i class="bi bi-paypal" aria-hidden="true"></i> PayPal</span>
                            <span class="payment-badge"><i class="bi bi-phone" aria-hidden="true"></i> GCash</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer Bottom Legal & Attribution Bar -->
            <div class="footer-bottom-bar">
                <div class="footer-bottom-left">
                    <span class="footer-copyright">&copy; <?= date('Y') ?> FreshCart Market. Student Project by <strong class="footer-author-name">Janus Dominic</strong>.</span>
                </div>
                <div class="footer-bottom-right">
                    <span class="footer-ssl-badge"><i class="bi bi-shield-check text-brand me-1" aria-hidden="true"></i>256-Bit SSL Encrypted</span>
                    <a href="privacy" class="footer-legal-link">Privacy Policy</a>
                    <span class="footer-legal-sep" aria-hidden="true">&bull;</span>
                    <a href="terms" class="footer-legal-link">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <div class="toast-container position-fixed bottom-0 start-0 p-3" style="z-index: 1060;">
        <div id="liveToast" class="toast align-items-center text-bg-dark border-0 rounded-4 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex p-2">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi bi-bag-check-fill text-success fs-5"></i>
                    <span>Item added to cart!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function handleFooterNewsletter(e) {
        e.preventDefault();
        const input = document.getElementById('footerEmailInput');
        const msg = document.getElementById('footerNewsletterMsg');
        const btn = document.getElementById('footerSubscribeBtn');
        if (input && input.value.trim()) {
            btn.disabled = true;
            btn.innerHTML = '<span>Joined</span> <i class="bi bi-check2" aria-hidden="true"></i>';
            if (msg) {
                msg.textContent = 'Subscribed! Welcome to morning harvest drops.';
                msg.className = 'footer-newsletter-msg is-success';
            }
            setTimeout(function() {
                input.value = '';
                btn.disabled = false;
                btn.innerHTML = '<span>Join</span> <i class="bi bi-arrow-right" aria-hidden="true"></i>';
            }, 3500);
        }
    }
    </script>
    
    <?php if (!$isLoggedIn): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // IMAGES: Make sure these exist in 'assets/images/'
            const heroImages = [
                "assets/images/gulay.jpg",       
                "assets/images/gulay1.jpg", 
                "assets/images/gulay2.jpg",    
                "assets/images/gulay3.jpg"   
            ];

            let currentIndex = 0;
            const imgElement = document.getElementById('dynamic-hero-img');

            if (imgElement && heroImages.length > 1) {
                setInterval(() => {
                    imgElement.classList.add('changing');
                    setTimeout(() => {
                        currentIndex = (currentIndex + 1) % heroImages.length;
                        imgElement.src = heroImages[currentIndex];
                        imgElement.onload = () => { imgElement.classList.remove('changing'); };
                        setTimeout(() => imgElement.classList.remove('changing'), 100);
                    }, 500); 
                }, 3000); 
            }
        });
    </script>

    <script>
        const aisleData = <?= json_encode($aisleReels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let currentAisleKey = '<?= $firstKey ?>';

        // Ruixen Coverflow Carousel Engine Configuration
        const CF_CONFIG = {
            rotate: 42,      // Degrees neighbour tilts
            depth: 0.58,     // How far neighbours recede as fraction of width
            falloff: 0.56,   // Exponent on distance to keep outer cards readable
            fade: 0.12,      // Opacity lost per step from centre
            gap: 0.06,       // Gap between cards as fraction of width
            loop: true       // Infinite seamless wrap
        };

        let pos = 4;         // Fractional card index at centre (starts on 4th pic)
        let targetPos = 4;   // Target index for settle animation
        let selected = 4;    // Nearest whole index
        let rafId = null;
        let drag = null;

        function slugify(text) {
            return text.toString().toLowerCase()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-')
                .replace(/^-+/, '')
                .replace(/-+$/, '');
        }

        function getCardWidth() {
            const card = document.querySelector('.coverflow-card');
            return card ? card.offsetWidth : 250;
        }

        function getCount() {
            const items = aisleData[currentAisleKey] ? aisleData[currentAisleKey].items : [];
            return items.length;
        }

        function indexAt(p) {
            const count = getCount();
            if (count === 0) return 0;
            return ((Math.round(p) % count) + count) % count;
        }

        function clamp(p) {
            const count = getCount();
            if (count === 0) return 0;
            return CF_CONFIG.loop ? p : Math.max(0, Math.min(count - 1, p));
        }

        function paint() {
            const stage = document.getElementById('coverflowStage');
            if (!stage) return;
            const cards = stage.querySelectorAll('.coverflow-card');
            const count = cards.length;
            if (count === 0) return;

            const width = getCardWidth();
            const pitch = width * (1 + CF_CONFIG.gap);

            cards.forEach((card, index) => {
                let offset = index - pos;
                if (CF_CONFIG.loop) {
                    offset = ((offset % count) + count) % count;
                    if (offset > count / 2) offset -= count;
                }

                const distance = Math.abs(offset);
                const ramp = Math.pow(distance, CF_CONFIG.falloff);
                const tilt = Math.min(CF_CONFIG.rotate * ramp, 82) * Math.sign(offset);

                // Ruixen UI reversed perspective 3D transform
                card.style.transform = `translateX(calc(-50% + ${offset * pitch}px)) ` +
                                       `translateZ(${-CF_CONFIG.depth * width * ramp}px) rotateY(${-tilt}deg)`;

                const edge = CF_CONFIG.loop ? Math.min(1, Math.max(0, count / 2 - distance)) : 1;
                const opacity = Math.max(0, 1 - CF_CONFIG.fade * distance) * edge;
                card.style.opacity = String(opacity);
                card.style.zIndex = String(100 - Math.round(distance));

                if (distance < 0.45) {
                    card.classList.add('is-active');
                } else {
                    card.classList.remove('is-active');
                }
            });
        }

        function updateDots() {
            const dots = document.querySelectorAll('.coverflow-dot');
            dots.forEach((dot, idx) => {
                if (idx === selected) dot.classList.add('active');
                else dot.classList.remove('active');
            });
        }

        function settle(target) {
            if (rafId !== null) cancelAnimationFrame(rafId);
            targetPos = target;
            selected = indexAt(target);
            updateDots();

            function step() {
                const remaining = targetPos - pos;
                if (Math.abs(remaining) < 0.0004) {
                    pos = targetPos;
                    paint();
                    rafId = null;
                    return;
                }
                // Ruixen exponential ease-out
                pos += remaining * 0.16;
                paint();
                rafId = requestAnimationFrame(step);
            }
            rafId = requestAnimationFrame(step);
        }

        function goTo(index) {
            const count = getCount();
            if (count === 0) return;
            const target = CF_CONFIG.loop
                ? index + Math.round((targetPos - index) / count) * count
                : index;
            settle(clamp(target));
        }

        function nudge(by) {
            settle(clamp(Math.round(targetPos) + by));
        }

        function prevCover() {
            nudge(-1);
        }

        function nextCover() {
            nudge(1);
        }

        function toggleAisleDropdown(forceState) {
            const trigger = document.getElementById('aisleDropdownTrigger');
            const menu = document.getElementById('aisleDropdownMenu');
            if (!trigger || !menu) return;

            const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
            const nextState = typeof forceState === 'boolean' ? forceState : !isExpanded;

            trigger.setAttribute('aria-expanded', nextState ? 'true' : 'false');
            if (nextState) {
                menu.classList.add('open');
            } else {
                menu.classList.remove('open');
            }
        }

        function chooseAisleMobile(catKey) {
            toggleAisleDropdown(false);
            selectAisle(catKey);
            const trigger = document.getElementById('aisleDropdownTrigger');
            if (trigger) trigger.focus();
        }

        function selectAisle(catKey) {
            if (!aisleData[catKey]) return;
            currentAisleKey = catKey;
            const items = aisleData[catKey].items || [];
            pos = items.length > 4 ? 4 : Math.max(0, items.length - 1);
            targetPos = pos;
            selected = indexAt(pos);

            const chips = document.querySelectorAll('#categoryRail .cat-chip');
            chips.forEach(chip => {
                if (chip.getAttribute('data-category') === catKey) {
                    chip.classList.add('active');
                    try {
                        chip.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                    } catch (e) {}
                } else {
                    chip.classList.remove('active');
                }
            });

            // Sync custom mobile dropdown UI
            const activeIcon = document.getElementById('aisleSelectActiveIcon');
            const activeLabel = document.getElementById('aisleSelectActiveLabel');
            if (aisleData[catKey] && aisleData[catKey].meta) {
                if (activeIcon) activeIcon.className = 'bi ' + (aisleData[catKey].meta.icon || 'bi-basket2');
                if (activeLabel) activeLabel.textContent = aisleData[catKey].meta.label || catKey;
            }

            // Sync menu item selected state
            const menuItems = document.querySelectorAll('#aisleDropdownMenu .aisle-dropdown-item');
            menuItems.forEach(item => {
                const isThis = item.getAttribute('data-category') === catKey;
                item.classList.toggle('selected', isThis);
                item.setAttribute('aria-selected', isThis ? 'true' : 'false');
            });

            renderCoverflow();
        }

        function renderCoverflow() {
            const stage = document.getElementById('coverflowStage');
            const dotsContainer = document.getElementById('coverflowDots');
            if (!stage || !aisleData[currentAisleKey]) return;

            const items = aisleData[currentAisleKey].items || [];
            stage.innerHTML = '';
            if (dotsContainer) dotsContainer.innerHTML = '';

            if (items.length === 0) {
                stage.innerHTML = '<div class="text-muted text-center py-5">No items available in this aisle.</div>';
                return;
            }

            items.forEach((item, index) => {
                const card = document.createElement('div');
                card.className = 'coverflow-card';
                card.setAttribute('data-index', index);
                
                const imgName = item.image ? item.image : 'default.jpg';
                const formattedPrice = '$' + parseFloat(item.price).toFixed(2);
                const safeName = item.name.replace(/"/g, '&quot;');
                const productSlug = slugify(item.name);
                const isOutOfStock = item.stock_qty <= 0;

                card.innerHTML = `
                    <div class="coverflow-card-thumb">
                        <img src="assets/images/${imgName}" width="180" height="180" alt="${safeName}" loading="eager" draggable="false">
                    </div>
                    <div class="coverflow-card-body">
                        <div class="coverflow-card-cat">${item.category}</div>
                        <a href="product/${productSlug}" class="coverflow-card-title" title="${safeName}">${item.name}</a>
                        <div class="coverflow-card-price-row">
                            <div class="coverflow-card-price-group">
                                <span class="coverflow-card-price">${formattedPrice}</span>
                                <span class="coverflow-card-unit">/ unit</span>
                            </div>
                            <form action="cart/add" method="POST" class="add-cart-form m-0">
                                <input type="hidden" name="product_id" value="${item.id}">
                                <button type="submit" class="coverflow-add-btn" aria-label="Add ${safeName} to cart" ${isOutOfStock ? 'disabled title="Out of Stock"' : 'title="Add to cart"'}>
                                    <i class="bi ${isOutOfStock ? 'bi-slash-circle' : 'bi-plus-lg'}" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                `;

                card.addEventListener('click', (e) => {
                    if (drag && drag.hasMoved) {
                        e.preventDefault();
                        return;
                    }
                    if (e.target.closest('button, a, form, input')) {
                        return;
                    }
                    if (index !== selected) {
                        e.preventDefault();
                        goTo(index);
                    }
                });

                stage.appendChild(card);

                if (dotsContainer) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'coverflow-dot' + (index === selected ? ' active' : '');
                    dot.setAttribute('aria-label', `Go to item ${index + 1}`);
                    dot.addEventListener('click', () => goTo(index));
                    dotsContainer.appendChild(dot);
                }
            });

            paint();
            updateDots();
        }

        // Pointer Gestures matching Ruixen UI (Continuous multi-card drag with momentum)
        document.addEventListener('DOMContentLoaded', () => {
            renderCoverflow();

            const wrapper = document.getElementById('coverflowStageWrapper');
            if (wrapper) {
                wrapper.addEventListener('pointerdown', (event) => {
                    if (event.target.closest('.coverflow-nav-btn, button, a, form, input')) {
                        return;
                    }
                    if (rafId !== null) {
                        cancelAnimationFrame(rafId);
                        rafId = null;
                    }
                    try {
                        wrapper.setPointerCapture(event.pointerId);
                    } catch (err) {}

                    wrapper.classList.add('is-dragging');
                    targetPos = pos;
                    drag = {
                        id: event.pointerId,
                        x: event.clientX,
                        pos: pos,
                        v: 0,
                        t: performance.now(),
                        hasMoved: false
                    };
                });

                wrapper.addEventListener('pointermove', (event) => {
                    if (!drag || drag.id !== event.pointerId) return;

                    const dx = event.clientX - drag.x;
                    if (Math.abs(dx) > 3) {
                        drag.hasMoved = true;
                    }

                    const width = getCardWidth();
                    const pitch = width * (1 + CF_CONFIG.gap);
                    if (!pitch) return;

                    const now = performance.now();
                    const previous = pos;
                    pos = clamp(drag.pos - dx / pitch);

                    // Real-time cards per second for throw velocity
                    drag.v = ((pos - previous) / Math.max(now - drag.t, 1)) * 1000;
                    drag.t = now;

                    const idx = indexAt(pos);
                    if (idx !== selected) {
                        selected = idx;
                        updateDots();
                    }
                    paint();
                });

                const endDrag = (event) => {
                    if (!drag || drag.id !== event.pointerId) return;
                    wrapper.classList.remove('is-dragging');
                    try {
                        wrapper.releasePointerCapture(event.pointerId);
                    } catch (err) {}

                    // Flick carry momentum
                    const carried = Math.max(-2.5, Math.min(2.5, drag.v * 0.18));
                    settle(clamp(Math.round(pos + carried)));

                    setTimeout(() => { drag = null; }, 50);
                };

                wrapper.addEventListener('pointerup', endDrag);
                wrapper.addEventListener('pointercancel', endDrag);

                // Keyboard arrow navigation
                wrapper.setAttribute('tabindex', '0');
                wrapper.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowLeft') {
                        event.preventDefault();
                        nudge(-1);
                    } else if (event.key === 'ArrowRight') {
                        event.preventDefault();
                        nudge(1);
                    }
                });
            }

            // Mobile Aisle Custom Dropdown Outside Click & Keyboard Listeners
            const customDropdown = document.getElementById('aisleCustomDropdown');
            if (customDropdown) {
                document.addEventListener('click', (e) => {
                    if (!customDropdown.contains(e.target)) {
                        toggleAisleDropdown(false);
                    }
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        toggleAisleDropdown(false);
                    }
                });

                customDropdown.addEventListener('keydown', (e) => {
                    const menu = document.getElementById('aisleDropdownMenu');
                    const isMenuOpen = menu && menu.classList.contains('open');
                    const items = Array.from(document.querySelectorAll('#aisleDropdownMenu .aisle-dropdown-item'));
                    if (items.length === 0) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (!isMenuOpen) {
                            toggleAisleDropdown(true);
                            items[0].focus();
                        } else {
                            const currentIndex = items.indexOf(document.activeElement);
                            const nextIndex = (currentIndex + 1) % items.length;
                            items[nextIndex].focus();
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (isMenuOpen) {
                            const currentIndex = items.indexOf(document.activeElement);
                            const prevIndex = (currentIndex - 1 + items.length) % items.length;
                            items[prevIndex].focus();
                        }
                    }
                });
            }

            window.addEventListener('resize', () => {
                paint();
            });
        });
    </script>
    <?php endif; ?>

    <script>
        document.addEventListener('submit', function(e) {
            if (e.target && e.target.classList.contains('add-cart-form')) {
                e.preventDefault();
                const formData = new FormData(e.target);
                fetch('cart/add', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update floating quick-cart tray
                        const floatBadge = document.getElementById('floatCartBadge');
                        if (floatBadge) floatBadge.innerText = data.cart_count;
                        
                        const floatTotal = document.getElementById('floatCartTotalText');
                        if (floatTotal && data.cart_subtotal !== undefined) {
                            floatTotal.innerText = '$' + parseFloat(data.cart_subtotal).toFixed(2);
                        }

                        const floatTray = document.getElementById('floatingCartTray');
                        if (floatTray) {
                            floatTray.classList.remove('is-hidden');
                        }

                        const badge = document.getElementById('cart-badge');
                        if (badge) badge.innerText = data.cart_count;
                        
                        const toastEl = document.getElementById('liveToast');
                        if (toastEl) {
                            const toast = new bootstrap.Toast(toastEl);
                            toast.show();
                        }
                    } else if (data.status === 'login_required') {
                        window.location.href = 'login';
                    } else {
                        alert(data.message);
                    }
                });
            }
        });
    </script>

    <!-- Asynchronous Food Category Loader (Seamless in-place updates without scroll jumps) -->
    <script>
    (function() {
        const aislesScroller = document.querySelector('.food-aisles-scroller');
        const catalogSection = document.getElementById('all-foods');
        const spotlightSection = document.getElementById('peak-harvest');
        const headerActionContainer = document.querySelector('.food-aisles-section .d-flex.align-items-center.justify-content-between');
        const searchForm = document.querySelector('.food-aisle-search-form');

        if (!aislesScroller || !catalogSection) return;

        let activeController = null;

        function updateAislePillState(targetUrl) {
            const parsed = new URL(targetUrl, window.location.origin);
            const targetCat = parsed.searchParams.get('category') || '';
            const targetSearch = parsed.searchParams.get('search') || '';

            const pills = aislesScroller.querySelectorAll('.food-aisle-pill');
            pills.forEach(pill => {
                const pillCat = pill.getAttribute('data-category') ?? null;
                if (targetCat) {
                    pill.classList.toggle('active', pillCat === targetCat);
                } else if (!targetSearch) {
                    pill.classList.toggle('active', pillCat === '' || pillCat === null);
                } else {
                    pill.classList.remove('active');
                }
            });
        }

        function loadCategoryAsync(targetUrl, pushState = true) {
            if (activeController) {
                activeController.abort();
            }
            activeController = new AbortController();

            // 1. Optimistically highlight selected pill instantly
            updateAislePillState(targetUrl);

            // 2. Soft in-place loading state (Window scroll remains 100% frozen in place)
            catalogSection.classList.add('is-loading');
            catalogSection.setAttribute('aria-busy', 'true');

            fetch(targetUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: activeController.signal
            })
            .then(res => {
                if (!res.ok) throw new Error('HTTP error ' + res.status);
                return res.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // A. Swap Catalog Grid & Pagination
                const newCatalog = doc.getElementById('all-foods');
                if (newCatalog) {
                    catalogSection.innerHTML = newCatalog.innerHTML;
                }

                // B. Swap Peak Harvest Spotlight (only rendered on All Departments when no search)
                const newSpotlight = doc.getElementById('peak-harvest');
                if (spotlightSection) {
                    if (newSpotlight) {
                        spotlightSection.innerHTML = newSpotlight.innerHTML;
                        spotlightSection.style.display = '';
                        setupSpotlightSwipe();
                    } else {
                        spotlightSection.style.display = 'none';
                    }
                }

                // C. Update "Show All" action row above pills or dropdown
                const newMobileHeader = doc.getElementById('mobileDeptHeader');
                const curMobileHeader = document.getElementById('mobileDeptHeader');
                if (curMobileHeader && newMobileHeader) {
                    curMobileHeader.innerHTML = newMobileHeader.innerHTML;
                }
                const newDesktopHeader = doc.getElementById('desktopDeptHeader');
                const curDesktopHeader = document.getElementById('desktopDeptHeader');
                if (curDesktopHeader && newDesktopHeader) {
                    curDesktopHeader.innerHTML = newDesktopHeader.innerHTML;
                }

                // Sync mobile department custom dropdown
                const newDropdown = doc.getElementById('foodHallMobileDropdown');
                const curDropdown = document.getElementById('foodHallMobileDropdown');
                if (curDropdown && newDropdown) {
                    curDropdown.innerHTML = newDropdown.innerHTML;
                }

                // D. Update Search Pill Form state
                const newSearchForm = doc.querySelector('.food-aisle-search-form');
                if (searchForm && newSearchForm) {
                    searchForm.innerHTML = newSearchForm.innerHTML;
                }

                // E. Clean URL update without scrolling or page reload
                if (pushState) {
                    window.history.pushState({ url: targetUrl }, '', targetUrl);
                }

                catalogSection.classList.remove('is-loading');
                catalogSection.removeAttribute('aria-busy');
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                catalogSection.classList.remove('is-loading');
                catalogSection.removeAttribute('aria-busy');
                // Fallback to standard navigation if AJAX fails
                window.location.href = targetUrl;
            });
        }

        // Delegate click events for pills, sort toolbar, and pagination
        document.addEventListener('click', function(e) {
            // Food Aisle Category Pills
            const pill = e.target.closest('.food-aisle-pill');
            if (pill && pill.closest('.food-aisles-scroller')) {
                e.preventDefault();
                const href = pill.getAttribute('href');
                if (href) loadCategoryAsync(href);
                return;
            }

            // "Show All" button above pills or dropdown
            const showAllBtn = e.target.closest('#showAllFoodsBtn, #showAllFoodsBtnMobile, .food-aisles-section a[href^="./"]');
            if (showAllBtn) {
                e.preventDefault();
                loadCategoryAsync('./');
                return;
            }

            // Search Clear Pill Button (desktop & mobile)
            const clearBtn = e.target.closest('.search-clear-pill-btn, .mobile-search-clear-btn');
            if (clearBtn) {
                e.preventDefault();
                const href = clearBtn.getAttribute('href') || './';
                loadCategoryAsync(href);
                return;
            }

            // In-catalog Sort Buttons, Pagination, Mobile Pagination, and Empty State links
            const catalogLink = e.target.closest('#all-foods .market-catalog-header a, #all-foods .pagination a, #all-foods .mobile-page-btn, #all-foods .btn-outline-success');
            if (catalogLink && !catalogLink.classList.contains('disabled')) {
                const href = catalogLink.getAttribute('href');
                if (href && !href.startsWith('#')) {
                    e.preventDefault();
                    loadCategoryAsync(href);
                    return;
                }
            }
        });

        // Mobile Department Custom Dropdown Handlers (Same Tactile Dropdown as Landing Page)
        window.toggleFoodHallDropdown = function(forceState) {
            const trigger = document.getElementById('foodHallDropdownTrigger');
            const menu = document.getElementById('foodHallDropdownMenu');
            if (!trigger || !menu) return;

            const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
            const nextState = typeof forceState === 'boolean' ? forceState : !isExpanded;

            trigger.setAttribute('aria-expanded', nextState ? 'true' : 'false');
            if (nextState) {
                menu.classList.add('open');
            } else {
                menu.classList.remove('open');
            }
        };

        window.chooseFoodHallAisle = function(targetUrl, displayName, iconClass) {
            window.toggleFoodHallDropdown(false);

            const iconEl = document.getElementById('foodHallSelectActiveIcon');
            const labelEl = document.getElementById('foodHallSelectActiveLabel');
            if (iconEl && iconClass) {
                iconEl.className = 'bi ' + iconClass;
            }
            if (labelEl && displayName) {
                labelEl.textContent = displayName;
            }

            const menu = document.getElementById('foodHallDropdownMenu');
            if (menu) {
                menu.querySelectorAll('.aisle-dropdown-item').forEach(item => {
                    const isMatch = item.getAttribute('data-url') === targetUrl;
                    item.classList.toggle('selected', isMatch);
                    item.setAttribute('aria-selected', isMatch ? 'true' : 'false');
                });
            }

            loadCategoryAsync(targetUrl);

            const trigger = document.getElementById('foodHallDropdownTrigger');
            if (trigger) trigger.focus();
        };

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('foodHallMobileDropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                window.toggleFoodHallDropdown(false);
            }
        });

        // Close dropdown on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.toggleFoodHallDropdown(false);
            }
        });

        // Search Form Submit in Pill Rail or Mobile Search Box
        document.addEventListener('submit', function(e) {
            const form = e.target.closest('.food-aisle-search-form, .mobile-food-search-form');
            if (form) {
                e.preventDefault();
                const searchInput = form.querySelector('.food-aisle-search-input, .mobile-food-search-input');
                const catInput = form.querySelector('input[name="category"]');
                const sortInput = form.querySelector('input[name="sort"]');

                const params = new URLSearchParams();
                if (searchInput && searchInput.value.trim()) params.set('search', searchInput.value.trim());
                if (catInput && catInput.value) params.set('category', catInput.value);
                if (sortInput && sortInput.value) params.set('sort', sortInput.value);

                const url = params.toString() ? ('?' + params.toString()) : './';
                loadCategoryAsync(url);
            }
        });

        // Interactive Peak Harvest Spotlight Carousel (Touch Swipe, Mouse Drag, Dots & Arrows)
        let currentSpotlightIdx = 0;

        function getSpotlightGrid() {
            return document.getElementById('spotlightGrid');
        }

        function getSpotlightCards() {
            const grid = getSpotlightGrid();
            return grid ? grid.querySelectorAll('.spotlight-food-card') : [];
        }

        function updateSpotlightUI(index) {
            const cards = getSpotlightCards();
            const total = cards.length;
            if (total === 0) return;
            currentSpotlightIdx = Math.max(0, Math.min(index, total - 1));

            // Update header counter
            const counter = document.getElementById('spotlightCounter');
            if (counter) {
                counter.textContent = (currentSpotlightIdx + 1) + ' / ' + total;
            }

            // Update dots
            const dots = document.querySelectorAll('#spotlightDots .spotlight-dot');
            dots.forEach((dot, idx) => {
                dot.classList.toggle('active', idx === currentSpotlightIdx);
            });

            // Update arrow disabled states
            const prevBtns = [document.getElementById('spotlightBottomPrev'), document.getElementById('spotlightPrevBtn')];
            const nextBtns = [document.getElementById('spotlightBottomNext'), document.getElementById('spotlightNextBtn')];
            prevBtns.forEach(btn => { if (btn) btn.disabled = (currentSpotlightIdx === 0); });
            nextBtns.forEach(btn => { if (btn) btn.disabled = (currentSpotlightIdx === total - 1); });
        }

        window.goToSpotlight = function(index) {
            const grid = getSpotlightGrid();
            const cards = getSpotlightCards();
            if (!grid || !cards.length) return;

            const targetIdx = Math.max(0, Math.min(index, cards.length - 1));
            const targetCard = cards[targetIdx];
            if (targetCard) {
                const scrollDest = targetCard.offsetLeft - grid.offsetLeft;
                grid.scrollTo({
                    left: scrollDest,
                    behavior: 'smooth'
                });
            }
            updateSpotlightUI(targetIdx);
        };

        window.navigateSpotlight = function(direction) {
            window.goToSpotlight(currentSpotlightIdx + direction);
        };

        function setupSpotlightSwipe() {
            const grid = getSpotlightGrid();
            if (!grid) return;

            // Sync on scroll (e.g. native scroll-snap)
            let scrollTimeout;
            grid.addEventListener('scroll', function() {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    const cards = getSpotlightCards();
                    if (!cards.length) return;
                    const scrollLeft = grid.scrollLeft;
                    const cardW = cards[0].offsetWidth || 300;
                    const active = Math.min(cards.length - 1, Math.max(0, Math.round(scrollLeft / cardW)));
                    updateSpotlightUI(active);
                }, 50);
            }, { passive: true });

            // Touch swipe gesture engine
            let touchStartX = 0;
            let touchStartY = 0;
            let touchStartTime = 0;
            let isHorizontalSwipe = null;

            grid.addEventListener('touchstart', function(e) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                touchStartTime = Date.now();
                isHorizontalSwipe = null;
            }, { passive: true });

            grid.addEventListener('touchmove', function(e) {
                if (isHorizontalSwipe === false) return;
                const diffX = e.touches[0].clientX - touchStartX;
                const diffY = e.touches[0].clientY - touchStartY;
                if (isHorizontalSwipe === null) {
                    if (Math.abs(diffX) > 8 || Math.abs(diffY) > 8) {
                        isHorizontalSwipe = Math.abs(diffX) > Math.abs(diffY);
                    }
                }
            }, { passive: true });

            grid.addEventListener('touchend', function(e) {
                if (!isHorizontalSwipe) return;
                const diffX = e.changedTouches[0].clientX - touchStartX;
                const elapsed = Date.now() - touchStartTime;
                if (Math.abs(diffX) > 35 || (Math.abs(diffX) > 20 && elapsed < 250)) {
                    if (diffX < 0) {
                        window.navigateSpotlight(1);
                    } else {
                        window.navigateSpotlight(-1);
                    }
                }
            }, { passive: true });

            // Mouse drag gesture engine (for desktop responsive test and trackpad)
            let isMouseDown = false;
            let mouseStartX = 0;
            let mouseScrollStart = 0;

            grid.addEventListener('mousedown', function(e) {
                if (e.button !== 0) return;
                if (e.target.closest('button, a, input, form')) return;
                isMouseDown = true;
                mouseStartX = e.pageX;
                mouseScrollStart = grid.scrollLeft;
                grid.classList.add('is-dragging');
            });

            window.addEventListener('mousemove', function(e) {
                if (!isMouseDown) return;
                e.preventDefault();
                const walk = (e.pageX - mouseStartX) * 1.2;
                grid.scrollLeft = mouseScrollStart - walk;
            });

            window.addEventListener('mouseup', function(e) {
                if (!isMouseDown) return;
                isMouseDown = false;
                grid.classList.remove('is-dragging');
                const cards = getSpotlightCards();
                if (!cards.length) return;
                const scrollLeft = grid.scrollLeft;
                const cardW = cards[0].offsetWidth || 300;
                const active = Math.min(cards.length - 1, Math.max(0, Math.round(scrollLeft / cardW)));
                window.goToSpotlight(active);
            });

            updateSpotlightUI(0);
        }

        window.setupSpotlightSwipe = setupSpotlightSwipe;
        setupSpotlightSwipe();

        // Popstate handler for Browser Back/Forward buttons without scroll jumps
        window.addEventListener('popstate', function() {
            loadCategoryAsync(window.location.href, false);
        });
    })();
    </script>

</body>
</html>