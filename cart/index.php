<?php
// cart/index.php
// Customer Seasonal Shopping Bag & Promo Discounts
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Handle Coupon Logic
$coupon_msg = '';
$coupon_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_coupon'])) {
    $code = trim($_POST['coupon_code'] ?? '');
    
    // Check DB for valid coupon
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

// Remove Coupon
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['discount']);
    header("Location: ./");
    exit;
}

// Clear Entire Cart
if (isset($_GET['clear'])) {
    unset($_SESSION['cart']);
    unset($_SESSION['discount']);
    header("Location: ./");
    exit;
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

// Free Cold-Chain Delivery threshold ($50.00)
$freeShippingThreshold = 50.00;
$freeShippingUnlocked = ($subTotal >= $freeShippingThreshold);
$shippingNeeded = max(0, $freeShippingThreshold - $subTotal);
$shippingProgress = ($subTotal > 0) ? min(100, round(($subTotal / $freeShippingThreshold) * 100)) : 0;

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
            <!-- Clean Farmstead Empty Cart State -->
            <div class="cart-empty-panel">
                <div class="cart-empty-icon" aria-hidden="true">
                    <i class="bi bi-basket3"></i>
                </div>
                <h2 class="cart-empty-title">Your harvest bag is empty</h2>
                <p class="cart-empty-text">
                    Explore freshly picked seasonal produce, farmstead dairy, and artisanal pantry goods sourced directly from certified organic growers.
                </p>
                <a href="<?= $rootPath ?>" class="btn-cart-checkout d-inline-flex w-auto px-4 py-2">
                    <i class="bi bi-shop me-1"></i>
                    <span>Explore Fresh Market</span>
                </a>
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
                                    <span>Complimentary Cold-Chain Eco Delivery Unlocked!</span>
                                <?php else: ?>
                                    <span>Add $<?= number_format($shippingNeeded, 2) ?> more for Complimentary Cold-Chain Transit</span>
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
                            <span>Harvest Item</span>
                            <span>Unit Rate</span>
                            <span class="text-center">Quantity</span>
                            <span class="text-end">Subtotal</span>
                            <span></span>
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
                                            <div class="cart-unit-rate-mobile d-md-none text-muted small">$<?= number_format($item['price'], 2) ?> / unit</div>
                                            <?php if ($item['stock_qty'] <= 5): ?>
                                                <span class="cart-stock-hint">Only <?= $item['stock_qty'] ?> remaining in harvest</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="cart-price-cell d-none d-md-block">
                                        $<?= number_format($item['price'], 2) ?>
                                    </div>

                                    <div class="cart-row-controls">
                                        <div class="cart-stepper-control">
                                            <form action="<?= $rootPath ?>cart/update" method="POST" class="m-0 p-0">
                                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="action" value="decrease">
                                                <button type="submit" class="cart-step-btn" title="Decrease quantity" aria-label="Decrease quantity">
                                                    <i class="bi bi-dash"></i>
                                                </button>
                                            </form>
                                            
                                            <span class="cart-step-count"><?= $item['qty'] ?></span>
                                            
                                            <form action="<?= $rootPath ?>cart/update" method="POST" class="m-0 p-0">
                                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="action" value="increase">
                                                <button type="submit" class="cart-step-btn" <?= ($item['qty'] >= $item['stock_qty']) ? 'disabled title="Stock limit reached"' : 'title="Increase quantity"' ?> aria-label="Increase quantity">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </form>
                                        </div>

                                        <div class="cart-subtotal-cell text-end">
                                            <span class="cart-subtotal-label d-md-none text-muted small me-1">Subtotal:</span>
                                            <span class="cart-subtotal-val font-monospace fw-bold">$<?= number_format($item['subtotal'], 2) ?></span>
                                        </div>
                                    </div>

                                    <div class="cart-remove-cell text-end">
                                        <form action="<?= $rootPath ?>cart/remove" method="POST" class="m-0 p-0">
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
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-patch-check-fill text-success" aria-hidden="true"></i>
                                <span>Packed in 100% biodegradable temperature-preserving plant fiber.</span>
                            </div>
                            <a href="<?= $rootPath ?>cart/?clear=true" onclick="return confirm('Empty your harvest basket?');" class="cart-clear-link">
                                Empty Basket
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sticky Summary Panel -->
                <div class="col-lg-5 col-xl-4">
                    <div class="cart-summary-card">
                        <h2 class="cart-summary-title">Order Summary</h2>
                        <p class="cart-summary-sub">Transparent calculation with applied vouchers and courier rates.</p>

                        <!-- Promo Code Form -->
                        <form method="POST" action="<?= $rootPath ?>cart/" class="cart-promo-form" id="cartCouponForm">
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
                            <span class="cart-calc-val">$<?= number_format($subTotal, 2) ?></span>
                        </div>

                        <?php if (isset($_SESSION['discount'])): ?>
                            <div class="cart-calc-row text-success">
                                <div class="d-flex align-items-center gap-1">
                                    <span>Voucher Discount (<?= $_SESSION['discount']['percent'] ?>%)</span>
                                    <a href="<?= $rootPath ?>cart/?remove_coupon=true" class="text-danger small text-decoration-none ms-1" title="Remove coupon">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </a>
                                </div>
                                <span class="cart-calc-val text-success">-$<?= number_format($discountAmount, 2) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="cart-calc-row">
                            <span>Cold-Chain Courier Transit</span>
                            <span class="cart-calc-val text-success">Complimentary (Free)</span>
                        </div>

                        <!-- Grand Total -->
                        <div class="cart-grand-total-row">
                            <div>
                                <div class="cart-grand-label">Estimated Total</div>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">Includes all farm taxes &amp; packaging</small>
                            </div>
                            <div class="cart-grand-amount">$<?= number_format($finalTotal, 2) ?></div>
                        </div>

                        <!-- Proceed to Checkout Button -->
                        <a href="<?= $rootPath ?>checkout" class="btn-cart-checkout">
                            <span>Proceed to Checkout</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>

                        <!-- Trust Bar -->
                        <div class="cart-trust-bar">
                            <div><i class="bi bi-shield-check text-success me-1"></i> Temperature-Guaranteed 4°C Courier Transit</div>
                            <div>100% Crisp Harvest Quality Guarantee</div>
                        </div>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
