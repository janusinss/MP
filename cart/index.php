<?php
// cart/index.php
// Customer Seasonal Shopping Bag & Promo Discounts
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Handle Coupon Logic & Cart Mutation Actions (CSRF-hardened per Phase 2.4)
$coupon_msg = '';
$coupon_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply_coupon'])) {
        if (!verify_csrf_token()) {
            http_response_code(403);
            die("Security validation failed. Please refresh.");
        } else {
            $code = trim($_POST['coupon_code'] ?? '');
            $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'Active' AND expiry_date >= CURDATE()");
            $stmt->execute([$code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($coupon) {
                $_SESSION['discount'] = [
                    'code' => $coupon['code'],
                    'percent' => (int)$coupon['discount_percent']
                ];
                $coupon_msg = "Coupon '{$coupon['code']}' applied! You saved {$coupon['discount_percent']}%.";
            } else {
                $coupon_error = "Invalid or expired coupon code.";
                unset($_SESSION['discount']);
            }
        }
    } elseif (isset($_POST['remove_coupon'])) {
        if (!verify_csrf_token()) {
            http_response_code(403);
            die("Security validation failed. Please refresh.");
        }
        unset($_SESSION['discount']);
        header("Location: ./");
        exit;
    } elseif (isset($_POST['clear_cart'])) {
        if (!verify_csrf_token()) {
            http_response_code(403);
            die("Security validation failed. Please refresh.");
        }
        unset($_SESSION['cart']);
        unset($_SESSION['discount']);
        header("Location: ./");
        exit;
    }
}

// 2. Fetch Cart Items
$cartItems = [];
$subTotal = 0;
$totalQty = 0;

if (!empty($_SESSION['cart'])) {
    $cartKeys = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($cartKeys), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($cartKeys);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        $qty = (int)$_SESSION['cart'][$product['id']];
        $lineTotal = (float)$product['price'] * $qty;
        $totalQty += $qty;
        
        $cartItems[] = [
            'id' => (int)$product['id'],
            'name' => $product['name'], 
            'price' => (float)$product['price'],
            'image' => $product['image'],
            'category' => $product['category'],
            'stock_qty' => (int)($product['stock_qty'] ?? 99),
            'qty' => $qty,
            'subtotal' => $lineTotal
        ];
        $subTotal += $lineTotal;
    }
}

// 3. Calculate Totals
$discountAmount = 0;
$finalTotal = $subTotal;

if (isset($_SESSION['discount'])) {
    $discountAmount = ($subTotal * (int)$_SESSION['discount']['percent']) / 100;
    $finalTotal = max(0, $subTotal - $discountAmount);
}

// Free Cold-Chain Delivery threshold (₱500.00)
$freeShippingThreshold = 500.00;
$freeShippingUnlocked = ($subTotal >= $freeShippingThreshold);
$shippingNeeded = max(0, $freeShippingThreshold - $subTotal);
$shippingProgress = ($subTotal > 0) ? min(100, round(($subTotal / $freeShippingThreshold) * 100)) : 0;

// 4. Fetch popular products for empty state quick-adds
$quickAddProducts = [];
if (empty($cartItems)) {
    try {
        $stmtQuick = $pdo->prepare("SELECT id, name, price, image, category, stock_qty FROM products WHERE stock_qty > 0 ORDER BY id ASC LIMIT 4");
        $stmtQuick->execute();
        $quickAddProducts = $stmtQuick->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $quickAddProducts = [];
    }
}

$pageTitle = "Your Seasonal Market Bag | FreshCart";
require_once __DIR__ . '/../includes/header.php';
?>

<main class="cart-page-section">
    <div class="container">
        
        <!-- Cart Header -->
        <div class="cart-header-row">
            <div class="cart-header-lead">
                <nav class="cart-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= $rootPath ?>"><i class="bi bi-house-door"></i> Marketplace</a>
                    <i class="bi bi-chevron-right" style="font-size: 0.72rem;"></i>
                    <span class="text-dark fw-medium">Shopping Bag</span>
                </nav>
                <h1 class="cart-header-title">Your Harvest Bag</h1>
                <p class="cart-header-meta"><?= $totalQty ?> <?= $totalQty === 1 ? 'item' : 'items' ?> currently reserved in your basket</p>
            </div>
            <div>
                <a href="<?= $rootPath ?>" class="btn-continue-browsing">
                    <i class="bi bi-arrow-left"></i>
                    <span>Continue Browsing</span>
                </a>
            </div>
        </div>

        <?php if (empty($cartItems)): ?>
            <!-- Farmstead Web Empty Basket State (Anti-Slop, High-Density) -->
            <div class="cart-empty-web-container">
                <div class="cart-empty-hero-panel">
                    <div class="cart-empty-hero-lead">
                        <span class="cart-empty-eyebrow-text">Farm-Fresh Marketplace</span>
                        <h2 class="cart-empty-main-title">Your basket is waiting for the morning harvest</h2>
                        <p class="cart-empty-subtitle">
                            Every item is picked to order from local family farms. Browse our organic produce, artisan bakery, and pasture-raised dairy to build your seasonal delivery.
                        </p>
                        <div class="cart-empty-cta-row">
                            <a href="<?= $rootPath ?>#harvest-catalog" class="btn-cart-browse-catalog">
                                <span>Browse All Departments</span>
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Department Fast-Links Bento -->
                    <div class="cart-empty-departments-grid">
                        <a href="<?= $rootPath ?>?category=Fruits#harvest-catalog" class="cart-department-tile">
                            <div class="cart-department-tile-body">
                                <span class="cart-dept-icon"><i class="bi bi-apple" aria-hidden="true"></i></span>
                                <div class="cart-dept-text">
                                    <span class="cart-dept-title">Fresh Fruits</span>
                                    <span class="cart-dept-note">Heirloom apples, berries &amp; citrus</span>
                                </div>
                            </div>
                            <i class="bi bi-arrow-right cart-dept-arrow" aria-hidden="true"></i>
                        </a>
                        <a href="<?= $rootPath ?>?category=Vegetables#harvest-catalog" class="cart-department-tile">
                            <div class="cart-department-tile-body">
                                <span class="cart-dept-icon"><i class="bi bi-flower1" aria-hidden="true"></i></span>
                                <div class="cart-dept-text">
                                    <span class="cart-dept-title">Field Vegetables</span>
                                    <span class="cart-dept-note">Crisp greens, carrots &amp; seasonal squash</span>
                                </div>
                            </div>
                            <i class="bi bi-arrow-right cart-dept-arrow" aria-hidden="true"></i>
                        </a>
                        <a href="<?= $rootPath ?>?category=Dairy#harvest-catalog" class="cart-department-tile">
                            <div class="cart-department-tile-body">
                                <span class="cart-dept-icon"><i class="bi bi-cup-hot" aria-hidden="true"></i></span>
                                <div class="cart-dept-text">
                                    <span class="cart-dept-title">Dairy &amp; Pasture Eggs</span>
                                    <span class="cart-dept-note">Grass-fed milk, farm butter &amp; raw cheeses</span>
                                </div>
                            </div>
                            <i class="bi bi-arrow-right cart-dept-arrow" aria-hidden="true"></i>
                        </a>
                        <a href="<?= $rootPath ?>?category=Bakery#harvest-catalog" class="cart-department-tile">
                            <div class="cart-department-tile-body">
                                <span class="cart-dept-icon"><i class="bi bi-basket2" aria-hidden="true"></i></span>
                                <div class="cart-dept-text">
                                    <span class="cart-dept-title">Artisan Bakery</span>
                                    <span class="cart-dept-note">Wild yeast sourdough &amp; fresh croissants</span>
                                </div>
                            </div>
                            <i class="bi bi-arrow-right cart-dept-arrow" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick-Add Seasonal Essentials Grid -->
                <?php if (!empty($quickAddProducts)): ?>
                    <div class="cart-empty-quick-add-wrap">
                        <div class="cart-quick-add-header">
                            <div>
                                <h3 class="cart-quick-add-title">Seasonal Farmstead Essentials</h3>
                                <p class="cart-quick-add-sub">Frequently reserved morning staples ready to add in one click.</p>
                            </div>
                            <a href="<?= $rootPath ?>#harvest-catalog" class="cart-quick-view-catalog">
                                <span>See full catalog</span>
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>

                        <div class="cart-quick-products-row">
                            <?php foreach ($quickAddProducts as $qp): 
                                $qpImg = !empty($qp['image']) ? $qp['image'] : 'placeholder.jpg';
                                $qpSlug = function_exists('slugify') ? slugify($qp['name']) : urlencode(strtolower(str_replace(' ', '-', $qp['name'])));
                            ?>
                                <div class="cart-quick-item-card">
                                    <a href="<?= $rootPath ?>product/<?= $qpSlug ?>" class="cart-quick-item-media" title="<?= htmlspecialchars($qp['name']) ?>">
                                        <img src="<?= $rootPath ?>assets/images/<?= htmlspecialchars($qpImg) ?>" alt="<?= htmlspecialchars($qp['name']) ?>" loading="lazy" onerror="this.onerror=null; this.src='<?= $rootPath ?>assets/images/placeholder.jpg';">
                                        <span class="cart-quick-item-aisle"><?= htmlspecialchars($qp['category']) ?></span>
                                    </a>
                                    <div class="cart-quick-item-content">
                                        <a href="<?= $rootPath ?>product/<?= $qpSlug ?>" class="cart-quick-item-title" title="<?= htmlspecialchars($qp['name']) ?>">
                                            <?= htmlspecialchars($qp['name']) ?>
                                        </a>
                                        <div class="cart-quick-item-actions">
                                            <span class="cart-quick-item-price">₱<?= number_format($qp['price'], 2) ?></span>
                                            <button type="button" class="btn-quick-add-basket" onclick="quickAddToCart(<?= (int)$qp['id'] ?>, this)" aria-label="Add <?= htmlspecialchars($qp['name']) ?> to basket">
                                                <i class="bi bi-plus" aria-hidden="true"></i>
                                                <span>Add</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="row g-4 g-xl-5 align-items-start">
                
                <!-- Left Column: Delivery Progress & Cart Items -->
                <div class="col-lg-7 col-xl-8">
                    
                    <!-- Dispatch Progress Bar -->
                    <div class="cart-threshold-card">
                        <div class="cart-threshold-info">
                            <div class="cart-threshold-msg">
                                <i class="bi bi-truck" aria-hidden="true"></i>
                                <?php if ($freeShippingUnlocked): ?>
                                    <span>Free refrigerated farm delivery unlocked</span>
                                <?php else: ?>
                                    <span>Add <strong>₱<?= number_format($shippingNeeded, 2) ?></strong> more for free refrigerated delivery</span>
                                <?php endif; ?>
                            </div>
                            <span class="cart-threshold-pct"><?= $shippingProgress ?>%</span>
                        </div>
                        <div class="cart-threshold-track" aria-hidden="true">
                            <div class="cart-threshold-fill" style="width: <?= $shippingProgress ?>%;"></div>
                        </div>
                    </div>

                    <!-- Items Container Panel -->
                    <div class="cart-items-panel">
                        <div class="cart-table-header">
                            <span>Product</span>
                            <span>Unit Price</span>
                            <span class="text-center">Quantity</span>
                            <span class="text-end">Subtotal</span>
                            <span aria-hidden="true"></span>
                        </div>

                        <div class="cart-rows-list">
                            <?php foreach ($cartItems as $item): 
                                $imgFile = !empty($item['image']) ? $item['image'] : 'placeholder.jpg';
                                $productSlug = function_exists('slugify') ? slugify($item['name']) : urlencode(strtolower(str_replace(' ', '-', $item['name'])));
                            ?>
                                <div class="cart-row">
                                    <div class="cart-product-cell">
                                        <img src="<?= $rootPath ?>assets/images/<?= htmlspecialchars($imgFile) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-thumb-img" onerror="this.onerror=null; this.src='<?= $rootPath ?>assets/images/placeholder.jpg';" loading="lazy">
                                        <div class="cart-product-meta">
                                            <span class="cart-product-aisle"><?= htmlspecialchars($item['category']) ?></span>
                                            <a href="<?= $rootPath ?>product/<?= $productSlug ?>" class="cart-product-title" title="<?= htmlspecialchars($item['name']) ?>">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                            <div class="cart-unit-rate-mobile d-md-none">₱<?= number_format($item['price'], 2) ?> <span class="text-muted">/ unit</span></div>
                                            <?php if ($item['stock_qty'] <= 5): ?>
                                                <span class="cart-stock-hint"><i class="bi bi-exclamation-circle-fill me-1"></i>Only <?= $item['stock_qty'] ?> remaining</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="cart-price-cell d-none d-md-block">
                                        ₱<?= number_format($item['price'], 2) ?>
                                    </div>

                                    <div class="cart-row-controls">
                                        <div class="cart-stepper-control">
                                            <form action="<?= $rootPath ?>cart/update" method="POST" class="m-0 p-0">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="action" value="decrease">
                                                <button type="submit" class="cart-step-btn" title="Decrease quantity" aria-label="Decrease quantity">
                                                    <i class="bi bi-dash"></i>
                                                </button>
                                            </form>
                                            
                                            <span class="cart-step-count"><?= $item['qty'] ?></span>
                                            
                                            <form action="<?= $rootPath ?>cart/update" method="POST" class="m-0 p-0">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="action" value="increase">
                                                <button type="submit" class="cart-step-btn" <?= ($item['qty'] >= $item['stock_qty']) ? 'disabled title="Stock limit reached"' : 'title="Increase quantity"' ?> aria-label="Increase quantity">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </form>
                                        </div>

                                        <div class="cart-subtotal-cell text-end">
                                            <span class="cart-subtotal-label d-md-none">Subtotal</span>
                                            <span class="cart-subtotal-val">₱<?= number_format($item['subtotal'], 2) ?></span>
                                        </div>
                                    </div>

                                    <div class="cart-remove-cell text-end">
                                        <form action="<?= $rootPath ?>cart/remove" method="POST" class="m-0 p-0">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="cart-remove-btn" title="Remove <?= htmlspecialchars($item['name']) ?>" aria-label="Remove item">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="cart-items-footer">
                            <div class="cart-eco-banner">
                                <div class="cart-eco-icon" aria-hidden="true">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div class="cart-eco-content">
                                    <span class="cart-eco-title">Cold-Chain Eco Packaging</span>
                                    <span class="cart-eco-text">Packed in 100% biodegradable temperature-preserving plant fiber.</span>
                                </div>
                            </div>
                            <div class="cart-clear-wrap">
                                <form method="POST" action="<?= $rootPath ?>cart/" class="d-inline m-0 p-0" onsubmit="return confirm('Empty your harvest basket? All reserved items will be removed.');">
                                    <?= csrf_input() ?>
                                    <button type="submit" name="clear_cart" value="1" class="btn-clear-basket border-0 bg-transparent" aria-label="Empty Entire Basket">
                                        <i class="bi bi-trash3 me-1"></i>
                                        <span>Empty Entire Basket</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sticky Summary Panel -->
                <div class="col-lg-5 col-xl-4">
                    <div class="cart-summary-card">
                        <h2 class="cart-summary-title">Order Summary</h2>
                        <p class="cart-summary-sub">All organic produce certified, packed in recyclable insulation.</p>

                        <!-- Promo Code Form -->
                        <form method="POST" action="<?= $rootPath ?>cart/" class="cart-promo-form" id="cartCouponForm">
                            <?= csrf_input() ?>
                            <label for="couponField" class="cart-promo-label">Promotional Voucher</label>
                            <div class="cart-promo-input-group">
                                <input type="text" name="coupon_code" id="couponField" class="cart-promo-input" placeholder="e.g. FRESH50" value="<?= htmlspecialchars($_SESSION['discount']['code'] ?? '') ?>">
                                <button type="submit" name="apply_coupon" value="1" class="btn-apply-promo">
                                    Apply
                                </button>
                            </div>

                            <!-- Seasonal Vouchers -->
                            <div class="cart-promo-hints">
                                <div class="cart-promo-hint-row" onclick="applyPromoChip('WELCOME20')" role="button" tabindex="0" title="Apply 20% WELCOME20 coupon">
                                    <div>
                                        <span class="cart-promo-hint-code">WELCOME20</span>
                                        <span class="text-muted ms-1">• 20% Off First Harvest</span>
                                    </div>
                                    <span class="cart-promo-hint-apply">Use</span>
                                </div>
                                <div class="cart-promo-hint-row" onclick="applyPromoChip('FRESH50')" role="button" tabindex="0" title="Apply 50% FRESH50 coupon">
                                    <div>
                                        <span class="cart-promo-hint-code">FRESH50</span>
                                        <span class="text-muted ms-1">• 50% Farm Celebration</span>
                                    </div>
                                    <span class="cart-promo-hint-apply">Use</span>
                                </div>
                            </div>

                            <?php if ($coupon_msg): ?>
                                <div class="alert alert-success py-2 px-3 mt-3 mb-0 rounded-3 small d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span><?= htmlspecialchars($coupon_msg) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($coupon_error): ?>
                                <div class="alert alert-danger py-2 px-3 mt-3 mb-0 rounded-3 small d-flex align-items-center gap-2">
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                    <span><?= htmlspecialchars($coupon_error) ?></span>
                                </div>
                            <?php endif; ?>
                        </form>

                        <script>
                            function applyPromoChip(code) {
                                const input = document.getElementById('couponField');
                                if (input) {
                                    input.value = code;
                                    document.getElementById('cartCouponForm').submit();
                                }
                            }
                        </script>

                        <!-- Cost Breakdown Rows -->
                        <div class="cart-calc-row">
                            <span>Produce Subtotal (<?= $totalQty ?> items)</span>
                            <span class="cart-calc-val">₱<?= number_format($subTotal, 2) ?></span>
                        </div>

                        <?php if (isset($_SESSION['discount'])): ?>
                            <div class="cart-calc-row text-success">
                                <div class="d-flex align-items-center gap-1">
                                    <span>Voucher Discount (<?= $_SESSION['discount']['percent'] ?>%)</span>
                                    <form method="POST" action="<?= $rootPath ?>cart/" class="d-inline m-0 p-0">
                                        <?= csrf_input() ?>
                                        <button type="submit" name="remove_coupon" value="1" class="text-danger small border-0 bg-transparent p-0 ms-1" title="Remove coupon" aria-label="Remove coupon">
                                            <i class="bi bi-x-circle-fill"></i>
                                        </button>
                                    </form>
                                </div>
                                <span class="cart-calc-val text-success">-₱<?= number_format($discountAmount, 2) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="cart-calc-row">
                            <span>Farmstead Delivery</span>
                            <span class="cart-calc-val text-success">Complimentary (Free)</span>
                        </div>

                        <!-- Grand Total -->
                        <div class="cart-grand-total-row">
                            <div>
                                <div class="cart-grand-label">Estimated Total</div>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">Includes all farm taxes &amp; packaging</small>
                            </div>
                            <div class="cart-grand-amount">₱<?= number_format($finalTotal, 2) ?></div>
                        </div>

                        <!-- Proceed to Checkout Button -->
                        <a href="<?= $rootPath ?>checkout" class="btn-cart-checkout">
                            <span>Proceed to Checkout</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>

                        <!-- Trust Bar -->
                        <div class="cart-trust-bar">
                            <div><i class="bi bi-shield-check text-success me-1"></i> Refrigerated 4°C delivery guarantee</div>
                            <div>100% Crisp harvest satisfaction guarantee</div>
                        </div>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>

    <script>
    function quickAddToCart(productId, btn) {
        if (!btn || btn.disabled) return;
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width: 14px; height: 14px;"></span>';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', 1);
        formData.append('csrf_token', csrfToken);

        fetch('<?= $rootPath ?>cart/add.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                btn.innerHTML = '<i class="bi bi-check2"></i> Added';
                btn.classList.add('is-added');
                setTimeout(() => {
                    window.location.reload();
                }, 350);
            } else if (data.status === 'login_required') {
                window.location.href = '<?= $rootPath ?>login';
            } else {
                if (window.FreshToast) {
                    FreshToast.error(data.message || 'Could not add product to cart.', 'Basket Notice');
                } else {
                    alert(data.message || 'Could not add product to cart.');
                }
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        })
        .catch(err => {
            console.error(err);
            window.location.reload();
        });
    }
    </script>
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
