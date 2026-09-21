<?php
// orders/checkout.php
// Shipping Address & Order Summary Checkout
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$pos = strpos($scriptName, '/grocery_app');
if ($pos !== false) {
    $rootPath = substr($scriptName, 0, $pos + strlen('/grocery_app')) . '/';
} else {
    $rootPath = '/';
}

// If cart is empty, redirect back to shop
if (empty($_SESSION['cart'])) {
    header("Location: " . $rootPath);
    exit;
}

// 1. AUTO-FILL LOGIC
$pre_name = "";
$pre_address = "";

if (isset($_SESSION['user_id'])) {
    $stmtUser = $pdo->prepare("SELECT full_name, address FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $pre_name = $user['full_name'];
        $pre_address = $user['address'];
    }
}

// 2. FETCH CART ITEMS & CALCULATE TOTAL
$cartItems = [];
$cartKeys = array_map('intval', array_keys($_SESSION['cart']));
$placeholders = implode(',', array_fill(0, count($cartKeys), '?'));
$stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
$stmt->execute($cartKeys);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subTotal = 0;
foreach ($products as $p) {
    $qty = $_SESSION['cart'][$p['id']];
    $lineTotal = $p['price'] * $qty;
    $cartItems[] = [
        'name' => $p['name'],
        'image' => $p['image'],
        'price' => $p['price'],
        'qty' => $qty,
        'line_total' => $lineTotal
    ];
    $subTotal += $lineTotal;
}

// Apply Discount if exists
$finalTotal = $subTotal;
$discountAmount = 0;
if (isset($_SESSION['discount'])) {
    $discountAmount = ($subTotal * $_SESSION['discount']['percent']) / 100;
    $finalTotal = $subTotal - $discountAmount;
}

$checkoutError = $_GET['error'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<main class="checkout-page-wrapper" id="checkout-main">
    <div class="container">
        
        <!-- Header & Breadcrumb -->
        <div class="checkout-header-bar">
            <a href="<?= $rootPath ?>cart" class="checkout-back-link" aria-label="Return to Basket">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <span>Return to Basket</span>
            </a>
            <div class="checkout-header-content">
                <span class="checkout-kicker">Direct Farm Fulfillment</span>
                <h1 class="checkout-title">Secure Checkout</h1>
                <p class="checkout-subtitle">Verify your delivery location and payment preference to schedule your fresh harvest dispatch.</p>
                <div class="checkout-progress-steps">
                    <span class="checkout-step-chip active"><i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i> Destination</span>
                    <i class="bi bi-arrow-right checkout-step-arrow" aria-hidden="true"></i>
                    <span class="checkout-step-chip active"><i class="bi bi-credit-card-2-front-fill me-1" aria-hidden="true"></i> Payment</span>
                    <i class="bi bi-arrow-right checkout-step-arrow" aria-hidden="true"></i>
                    <span class="checkout-step-chip"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Dispatch</span>
                </div>
            </div>
        </div>

        <?php if ($checkoutError === 'missing_fields'): ?>
            <div class="checkout-alert checkout-alert-danger" role="alert">
                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                <span>Please provide both your full name and delivery address to confirm this order.</span>
            </div>
        <?php endif; ?>

        <form action="<?= $rootPath ?>orders/place" method="POST" id="checkoutForm" novalidate>
            <?= csrf_input() ?>

            <!-- Mobile Collapsible Order Summary Accordion -->
            <div class="checkout-mobile-summary-card d-lg-none mb-3">
                <button type="button" class="checkout-mobile-summary-toggle" id="mobileSummaryToggle" aria-expanded="false" aria-controls="checkoutMobileSummaryBody">
                    <div class="checkout-mobile-summary-left">
                        <i class="bi bi-bag-check-fill text-brand" aria-hidden="true"></i>
                        <span>Order Summary (<?= count($cartItems) ?> <?= count($cartItems) === 1 ? 'item' : 'items' ?>)</span>
                        <i class="bi bi-chevron-down toggle-chevron" id="mobileSummaryChevron" aria-hidden="true"></i>
                    </div>
                    <div class="checkout-mobile-summary-right">
                        <span class="checkout-mobile-summary-total">$<?= number_format($finalTotal, 2) ?></span>
                    </div>
                </button>
                <div class="checkout-mobile-summary-body" id="checkoutMobileSummaryBody" style="display: none;">
                    <div class="checkout-mobile-items-list">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="checkout-mobile-item-row">
                                <div class="checkout-mobile-item-thumb-wrap">
                                    <img src="<?= $rootPath ?>assets/images/<?= htmlspecialchars($item['image'] ?: 'default.jpg') ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                         class="checkout-mobile-item-thumb" 
                                         width="44" height="44" loading="lazy"
                                         onerror="this.onerror=null; this.src='<?= $rootPath ?>assets/images/default.jpg';">
                                </div>
                                <div class="checkout-mobile-item-info">
                                    <div class="checkout-mobile-item-name" title="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="checkout-mobile-item-meta">
                                        <span>Qty: <?= (int)$item['qty'] ?></span>
                                        <span>&times; $<?= number_format($item['price'], 2) ?></span>
                                    </div>
                                </div>
                                <div class="checkout-mobile-item-total">
                                    $<?= number_format($item['line_total'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="checkout-mobile-calc-breakdown">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Produce Subtotal</span>
                            <span class="fw-semibold small">$<?= number_format($subTotal, 2) ?></span>
                        </div>
                        <?php if ($discountAmount > 0): ?>
                            <div class="d-flex justify-content-between mb-1 text-success">
                                <span class="small"><i class="bi bi-tag-fill me-1" aria-hidden="true"></i>Coupon Discount</span>
                                <span class="fw-semibold small">-$<?= number_format($discountAmount, 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Farm Route Delivery</span>
                            <span class="text-success fw-semibold small">Free</span>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <span class="fw-bold">Total Due</span>
                            <span class="fw-bold text-brand fs-6">$<?= number_format($finalTotal, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 g-lg-5 align-items-start">
                
                <!-- Left: Order Fulfillment & Payment -->
                <div class="col-lg-7">
                    
                    <!-- Section 1: Delivery Details -->
                    <div class="checkout-section-card mb-4">
                        <div class="checkout-section-heading">
                            <span class="checkout-step-tag">Step 1</span>
                            <div>
                                <h2 class="checkout-section-title">Delivery Destination</h2>
                                <p class="checkout-section-desc">Where should our courier drop off your fresh produce?</p>
                            </div>
                        </div>
                        
                        <div class="checkout-field-group mb-3">
                            <label for="customer_name" class="checkout-label">
                                Full Name <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <div class="checkout-input-wrap">
                                <i class="bi bi-person checkout-input-icon" aria-hidden="true"></i>
                                <input type="text" id="customer_name" name="customer_name" class="checkout-input" 
                                       value="<?= htmlspecialchars($pre_name) ?>" placeholder="e.g. Eleanor Vance" 
                                       required autocomplete="name">
                            </div>
                        </div>

                        <div class="checkout-field-group mb-2">
                            <label for="address" class="checkout-label">
                                Delivery Address &amp; Instructions <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <div class="checkout-input-wrap">
                                <i class="bi bi-geo-alt checkout-input-icon checkout-input-icon-top" aria-hidden="true"></i>
                                <textarea id="address" name="address" class="checkout-textarea" rows="3" required 
                                          placeholder="Street name, house/apartment number, gate code, or delivery drop notes..." 
                                          autocomplete="street-address"><?= htmlspecialchars($pre_address) ?></textarea>
                            </div>
                        </div>

                        <p class="checkout-hint">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            Delivered in temperature-controlled, recyclable crates to maintain crisp peak flavor.
                        </p>
                    </div>

                    <!-- Section 2: Payment Method -->
                    <div class="checkout-section-card">
                        <div class="checkout-section-heading">
                            <span class="checkout-step-tag">Step 2</span>
                            <div>
                                <h2 class="checkout-section-title">Payment Method</h2>
                                <p class="checkout-section-desc">Choose your settlement method upon fulfillment.</p>
                            </div>
                        </div>

                        <div class="checkout-payment-methods">
                            <!-- Option 1: COD (Active) -->
                            <label class="checkout-payment-card is-selected" for="pay_cod">
                                <div class="checkout-payment-radio-wrap">
                                    <input type="radio" id="pay_cod" name="payment_method" value="COD" checked class="checkout-payment-radio">
                                </div>
                                <div class="checkout-payment-details">
                                    <div class="checkout-payment-header">
                                        <span class="checkout-payment-name">Cash on Delivery (COD)</span>
                                        <span class="checkout-payment-badge badge-active">Active</span>
                                    </div>
                                    <p class="checkout-payment-text">Inspect your harvest at your door before paying cash or local instant QR transfer to your courier.</p>
                                </div>
                                <div class="checkout-payment-icon" aria-hidden="true">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                            </label>
                            
                            <!-- Option 2: Card / Digital Wallet (Coming Soon) -->
                            <label class="checkout-payment-card is-disabled" for="pay_card">
                                <div class="checkout-payment-radio-wrap">
                                    <input type="radio" id="pay_card" name="payment_method_disabled" value="CARD" disabled class="checkout-payment-radio">
                                </div>
                                <div class="checkout-payment-details">
                                    <div class="checkout-payment-header">
                                        <span class="checkout-payment-name">Card &amp; Digital Wallet</span>
                                        <span class="checkout-payment-badge badge-muted">Rolling Out Soon</span>
                                    </div>
                                    <p class="checkout-payment-text">Visa, Mastercard, Apple Pay, and Google Pay integrations are currently undergoing security auditing.</p>
                                </div>
                                <div class="checkout-payment-icon" aria-hidden="true">
                                    <i class="bi bi-credit-card-2-front"></i>
                                </div>
                            </label>
                        </div>

                    </div>

                </div>

                <!-- Right: Sticky Order Summary -->
                <div class="col-lg-5">
                    <div class="checkout-summary-card">
                        <div class="checkout-summary-header">
                            <h2 class="checkout-summary-title">Harvest Basket</h2>
                            <span class="checkout-summary-count"><?= count($cartItems) ?> <?= count($cartItems) === 1 ? 'item' : 'items' ?></span>
                        </div>
                        
                        <!-- Line Items Scroll -->
                        <div class="checkout-items-scroll" tabindex="0" aria-label="Review items in your order">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="checkout-item-row">
                                    <div class="checkout-item-thumb-wrap">
                                        <img src="<?= $rootPath ?>assets/images/<?= htmlspecialchars($item['image'] ?: 'default.jpg') ?>" 
                                             alt="<?= htmlspecialchars($item['name']) ?>" 
                                             class="checkout-item-thumb" 
                                             width="52" height="52" loading="lazy"
                                             onerror="this.onerror=null; this.src='<?= $rootPath ?>assets/images/default.jpg';">
                                    </div>
                                    <div class="checkout-item-info">
                                        <h3 class="checkout-item-name" title="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars($item['name']) ?></h3>
                                        <div class="checkout-item-meta">
                                            <span class="checkout-item-qty">Qty: <?= (int)$item['qty'] ?></span>
                                            <span class="checkout-item-rate">&times; $<?= number_format($item['price'], 2) ?></span>
                                        </div>
                                    </div>
                                    <div class="checkout-item-total">
                                        $<?= number_format($item['line_total'], 2) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Price Breakdown -->
                        <div class="checkout-calc-table">
                            <div class="checkout-calc-row">
                                <span class="checkout-calc-label">Produce Subtotal</span>
                                <span class="checkout-calc-val">$<?= number_format($subTotal, 2) ?></span>
                            </div>
                            
                            <?php if ($discountAmount > 0): ?>
                                <div class="checkout-calc-row is-discount">
                                    <span class="checkout-calc-label">
                                        <i class="bi bi-tag-fill me-1" aria-hidden="true"></i>
                                        Coupon Discount (<?= htmlspecialchars($_SESSION['discount']['code'] ?? 'PROMO') ?>)
                                    </span>
                                    <span class="checkout-calc-val">-$<?= number_format($discountAmount, 2) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="checkout-calc-row">
                                <span class="checkout-calc-label">Farm Route Delivery</span>
                                <span class="checkout-calc-val text-success fw-semibold">Free</span>
                            </div>

                            <div class="checkout-total-divider"></div>

                            <div class="checkout-total-row">
                                <div>
                                    <span class="checkout-total-label">Total to Pay</span>
                                    <small class="checkout-total-subtext">Includes all seasonal produce &amp; taxes</small>
                                </div>
                                <div class="checkout-total-amount">
                                    $<?= number_format($finalTotal, 2) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Action -->
                        <button type="submit" class="checkout-submit-btn btn-confirm-order" id="confirmOrderBtn">
                            <span>Confirm Order &amp; Schedule Delivery</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>

                        <!-- Trust Guarantees -->
                        <div class="checkout-guarantee-badges">
                            <div class="checkout-guarantee-item">
                                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                                <span>SSL Encrypted Direct Checkout</span>
                            </div>
                            <div class="checkout-guarantee-item">
                                <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                                <span>100% Crisp Harvest Quality Guarantee</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentCards = document.querySelectorAll('.checkout-payment-card:not(.is-disabled)');
    paymentCards.forEach(function(card) {
        card.addEventListener('click', function() {
            paymentCards.forEach(c => c.classList.remove('is-selected'));
            this.classList.add('is-selected');
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        });
    });

    const form = document.getElementById('checkoutForm');
    const submitBtn = document.getElementById('confirmOrderBtn');
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            const nameInput = document.getElementById('customer_name');
            const addressInput = document.getElementById('address');
            if (!nameInput.value.trim() || !addressInput.value.trim()) {
                e.preventDefault();
                if (!nameInput.value.trim()) nameInput.focus();
                else addressInput.focus();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Scheduling Dispatch...</span> <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        });
    }

    // Mobile Collapsible Order Summary Accordion Toggle
    const summaryToggle = document.getElementById('mobileSummaryToggle');
    const summaryBody = document.getElementById('checkoutMobileSummaryBody');
    const summaryChevron = document.getElementById('mobileSummaryChevron');
    if (summaryToggle && summaryBody) {
        summaryToggle.addEventListener('click', function() {
            const isExpanded = summaryToggle.getAttribute('aria-expanded') === 'true';
            summaryToggle.setAttribute('aria-expanded', !isExpanded);
            if (!isExpanded) {
                summaryBody.style.display = 'block';
                if (summaryChevron) summaryChevron.style.transform = 'rotate(180deg)';
            } else {
                summaryBody.style.display = 'none';
                if (summaryChevron) summaryChevron.style.transform = 'rotate(0deg)';
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
