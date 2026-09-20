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
            <div>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0" style="font-size: 0.82rem;">
                        <li class="breadcrumb-item"><a href="<?= $rootPath ?>" class="text-decoration-none text-muted">Marketplace</a></li>
                        <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Shopping Bag</li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center gap-3">
                    <h1 class="cart-header-title">Your Seasonal Market Bag</h1>
                    <span class="cart-count-chip"><?= $totalQty ?> <?= $totalQty === 1 ? 'item' : 'items' ?></span>
                </div>
            </div>
            <div>
                <a href="<?= $rootPath ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2" style="font-size: 0.88rem; font-weight: 600;">
                    <i class="bi bi-arrow-left me-2"></i> Continue Browsing
                </a>
            </div>
        </div>

        <?php if (empty($cartItems)): ?>
            <!-- Elevated Empty Cart State -->
            <div class="cart-empty-state-v2 animate-fade-in">
                <div class="cart-empty-icon-box">
                    <i class="bi bi-basket3"></i>
                </div>
                <h2 class="font-serif fw-bold mb-3" style="color: var(--text-main, #14281d); font-size: 2rem;">
                    Your harvest bag is empty
                </h2>
                <p class="text-muted mb-4 mx-auto" style="max-width: 480px; font-size: 1.05rem; line-height: 1.6;">
                    Discover freshly picked organic heirloom produce, small-batch farmstead cheeses, and stone-ground pantry staples direct from regional growers.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3 mb-5">
                    <a href="<?= $rootPath ?>" class="btn btn-primary rounded-pill px-5 py-3 shadow-sm fw-bold" style="letter-spacing: 0.04em;">
                        <i class="bi bi-shop me-2"></i> Explore Fresh Market
                    </a>
                </div>

                <div class="row g-3 justify-content-center text-start border-top pt-4 mt-2">
                    <div class="col-sm-4 col-12">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-patch-check-fill text-success fs-4"></i>
                            <div>
                                <div class="fw-bold small text-dark">100% Pesticide-Free</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Directly certified growers</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-snow text-primary fs-4"></i>
                            <div>
                                <div class="fw-bold small text-dark">Cold-Chain 4°C</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Climate-controlled couriers</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-shield-check text-success fs-4"></i>
                            <div>
                                <div class="fw-bold small text-dark">Freshness Guarantee</div>
                                <div class="text-muted" style="font-size: 0.75rem;">100% satisfaction promise</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <div class="row g-4 g-xl-5 align-items-start animate-fade-in">
                
                <!-- Left Column: Items List & Progress Bar -->
                <div class="col-lg-7 col-xl-8">
                    
                    <!-- Cold Chain Delivery Progress Bar -->
                    <div class="delivery-progress-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-truck fs-4 <?= $freeShippingUnlocked ? 'text-success' : 'text-primary' ?>"></i>
                                <div>
                                    <?php if ($freeShippingUnlocked): ?>
                                        <span class="fw-bold text-success small">Free Cold-Chain Eco Delivery Unlocked!</span>
                                        <p class="text-muted mb-0" style="font-size: 0.78rem;">Your cart qualifies for climate-guaranteed 4°C refrigerated delivery.</p>
                                    <?php else: ?>
                                        <span class="fw-bold text-dark small">Add <strong class="text-success">$<?= number_format($shippingNeeded, 2) ?></strong> more to unlock FREE Cold-Chain Delivery</span>
                                        <p class="text-muted mb-0" style="font-size: 0.78rem;">Orders over $50 receive complimentary refrigerated courier dispatch.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="fw-bold small <?= $freeShippingUnlocked ? 'text-success' : 'text-muted' ?>"><?= $shippingProgress ?>%</span>
                        </div>
                        <div class="delivery-progress-track">
                            <div class="delivery-progress-bar-fill" style="width: <?= $shippingProgress ?>%;"></div>
                        </div>
                    </div>

                    <!-- Items Container -->
                    <div class="cart-items-wrapper">
                        <?php foreach ($cartItems as $item): 
                            $imgName = !empty($item['image']) ? $item['image'] : 'default.jpg';
                            $productSlug = function_exists('slugify') ? slugify($item['name']) : urlencode(strtolower(str_replace(' ', '-', $item['name'])));
                        ?>
                            <div class="cart-item-card-v2">
                                <div class="cart-item-img-v2">
                                    <img src="<?= $rootPath ?>assets/images/<?= htmlspecialchars($imgName) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                                </div>

                                <div class="cart-item-info-v2">
                                    <span class="badge bg-light text-success border mb-1" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                                        <?= htmlspecialchars($item['category']) ?>
                                    </span>
                                    <div>
                                        <a href="<?= $rootPath ?>product/<?= $productSlug ?>" class="cart-item-name-v2">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="text-muted small">$<?= number_format($item['price'], 2) ?> / unit</span>
                                        <?php if ($item['stock_qty'] <= 5): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">
                                                Only <?= $item['stock_qty'] ?> left
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Quantity Stepper -->
                                <div class="cart-stepper-v2">
                                    <form action="<?= $rootPath ?>cart/update" method="POST" class="m-0 p-0">
                                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="action" value="decrease">
                                        <button type="submit" class="cart-step-btn-v2" title="Decrease quantity" aria-label="Decrease quantity">
                                            <i class="bi bi-dash"></i>
                                        </button>
                                    </form>
                                    
                                    <span class="cart-step-val-v2"><?= $item['qty'] ?></span>
                                    
                                    <form action="<?= $rootPath ?>cart/update" method="POST" class="m-0 p-0">
                                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="action" value="increase">
                                        <button type="submit" class="cart-step-btn-v2" <?= ($item['qty'] >= $item['stock_qty']) ? 'disabled title="Stock limit reached"' : 'title="Increase quantity"' ?> aria-label="Increase quantity">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Line Total -->
                                <div class="cart-item-total-v2">
                                    $<?= number_format($item['subtotal'], 2) ?>
                                </div>

                                <!-- Remove Action -->
                                <form action="<?= $rootPath ?>cart/remove" method="POST" class="m-0 p-0">
                                    <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="cart-item-remove-v2" title="Remove <?= htmlspecialchars($item['name']) ?>" aria-label="Remove item">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-3 mt-3 rounded-3 bg-white border border-light shadow-sm">
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <i class="bi bi-box-seam text-success"></i>
                            <span>All items packed in 100% biodegradable refrigerated insulation.</span>
                        </div>
                        <a href="<?= $rootPath ?>cart/?clear=true" onclick="return confirm('Empty your shopping cart?');" class="text-danger small text-decoration-none fw-semibold">
                            Empty Cart
                        </a>
                    </div>
                </div>

                <!-- Right Column: Sticky Summary Panel -->
                <div class="col-lg-5 col-xl-4">
                    <div class="cart-summary-card-v2">
                        <h2 class="font-serif fw-bold mb-1" style="color: var(--text-main, #14281d); font-size: 1.5rem;">Order Summary</h2>
                        <p class="text-muted small mb-4">Review fees, promotional vouchers, and estimated total.</p>

                        <!-- Promo Code Form -->
                        <form method="POST" action="<?= $rootPath ?>cart/" class="mb-4" id="cartCouponForm">
                            <label class="small text-muted mb-2 text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08em;">
                                Promotional Voucher
                            </label>
                            <div class="input-group">
                                <input type="text" name="coupon_code" id="couponField" class="form-control rounded-start-3" placeholder="Enter coupon code" value="<?= htmlspecialchars($_SESSION['discount']['code'] ?? '') ?>" style="font-family: var(--font-mono, monospace); text-transform: uppercase;">
                                <button type="submit" name="apply_coupon" value="1" class="btn btn-dark rounded-end-3 px-4 fw-semibold" style="font-size: 0.85rem;">
                                    Apply
                                </button>
                            </div>

                            <!-- One-click coupon chips -->
                            <div class="mt-3">
                                <div class="text-muted fw-bold mb-1" style="font-size: 0.68rem; letter-spacing: 0.05em; text-transform: uppercase;">
                                    Instant Seasonal Vouchers:
                                </div>
                                <div class="coupon-chip-v2" onclick="applyPromoChip('WELCOME20')" role="button" tabindex="0" title="Click to apply WELCOME20">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-tag-fill text-success"></i>
                                        <div>
                                            <span class="fw-bold text-dark d-block" style="font-size: 0.82rem;">WELCOME20</span>
                                            <span class="text-muted d-block" style="font-size: 0.72rem;">20% off your first seasonal order</span>
                                        </div>
                                    </div>
                                    <span class="badge bg-success text-white rounded-pill px-2 py-1" style="font-size: 0.7rem;">TAP</span>
                                </div>

                                <div class="coupon-chip-v2" onclick="applyPromoChip('FRESH50')" role="button" tabindex="0" title="Click to apply FRESH50">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-lightning-charge-fill text-warning"></i>
                                        <div>
                                            <span class="fw-bold text-dark d-block" style="font-size: 0.82rem;">FRESH50</span>
                                            <span class="text-muted d-block" style="font-size: 0.72rem;">50% off farm celebration</span>
                                        </div>
                                    </div>
                                    <span class="badge bg-success text-white rounded-pill px-2 py-1" style="font-size: 0.7rem;">TAP</span>
                                </div>
                            </div>

                            <?php if ($coupon_msg): ?>
                                <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mt-3 mb-0 rounded-3 small">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span><?= htmlspecialchars($coupon_msg) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($coupon_error): ?>
                                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mt-3 mb-0 rounded-3 small">
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
                        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size: 0.95rem;">
                            <span class="text-muted">Subtotal (<?= $totalQty ?> items)</span>
                            <span class="fw-bold text-dark">$<?= number_format($subTotal, 2) ?></span>
                        </div>

                        <?php if (isset($_SESSION['discount'])): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 text-success" style="font-size: 0.95rem;">
                                <div class="d-flex align-items-center gap-1">
                                    <span>Discount (<?= $_SESSION['discount']['percent'] ?>%)</span>
                                    <a href="<?= $rootPath ?>cart/?remove_coupon=true" class="text-danger small text-decoration-none ms-1" title="Remove coupon">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </a>
                                </div>
                                <span class="fw-bold">-$<?= number_format($discountAmount, 2) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mb-3" style="font-size: 0.95rem;">
                            <div class="d-flex align-items-center gap-1 text-muted">
                                <span>Cold-Chain Delivery</span>
                                <i class="bi bi-info-circle" style="font-size: 0.75rem;" title="Temperature-guaranteed refrigerated transport"></i>
                            </div>
                            <span class="badge bg-success-subtle text-success fw-bold px-2 py-1">FREE</span>
                        </div>

                        <!-- Grand Total -->
                        <div class="border-top pt-3 mt-3 d-flex justify-content-between align-items-baseline">
                            <div>
                                <div class="fw-bold text-dark fs-5">Estimated Total</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Includes all farm taxes</div>
                            </div>
                            <div class="text-end">
                                <span class="font-serif fw-bold text-success" style="font-size: 2rem;">
                                    $<?= number_format($finalTotal, 2) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Checkout Button -->
                        <a href="<?= $rootPath ?>checkout" class="btn btn-primary w-100 py-3 mt-4 rounded-pill fw-bold shadow d-flex align-items-center justify-content-center gap-2 text-uppercase" style="letter-spacing: 0.08em; font-size: 0.92rem;">
                            <span>Proceed to Checkout</span>
                            <i class="bi bi-arrow-right fs-5"></i>
                        </a>

                        <!-- Security & Trust Indicators -->
                        <div class="mt-4 pt-3 border-top text-center">
                            <div class="d-flex align-items-center justify-content-center gap-3 text-muted small mb-2">
                                <span><i class="bi bi-shield-lock-fill text-success me-1"></i> 256-Bit SSL</span>
                                <span>•</span>
                                <span><i class="bi bi-snow text-primary me-1"></i> Cold-Chain 4°C</span>
                                <span>•</span>
                                <span><i class="bi bi-arrow-counterclockwise text-success me-1"></i> Easy Returns</span>
                            </div>
                            <small class="text-muted" style="font-size: 0.72rem;">
                                Guaranteed Freshness: If not 100% satisfied, instant store credit.
                            </small>
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
