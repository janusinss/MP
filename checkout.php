<?php
session_start();
include 'db.php';

// If cart is empty, kick them back to the shop
if (empty($_SESSION['cart'])) {
    header("Location: index.php");
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
// (We need this to display the summary list on the right)
$cartItems = [];
$ids = implode(',', array_keys($_SESSION['cart']));
$stmt = $pdo->query("SELECT * FROM products WHERE id IN ($ids)");
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

include 'includes/header.php';
?>

<div class="checkout-page-wrapper pt-5">
    <div class="container">
        
        <div class="mb-4">
            <a href="cart.php" class="text-decoration-none text-muted small text-uppercase fw-bold"><i class="bi bi-arrow-left me-1"></i> Back to Cart</a>
            <h2 class="mt-2" style="font-family: var(--font-serif);">Secure Checkout</h2>
        </div>

        <form action="place_order.php" method="POST">
            <div class="row g-5">
                
                <div class="col-lg-7">
                    
                    <div class="checkout-form-card mb-4">
                        <div class="step-header">
                            <div class="step-number">1</div>
                            <h4 class="step-title">Shipping Details</h4>
                        </div>
                        
                        <div class="mb-4">
                            <label class="auth-label">Full Name</label>
                            <input type="text" name="customer_name" class="form-control form-control-pill" 
                                   value="<?= htmlspecialchars($pre_name) ?>" placeholder="John Doe" required>
                        </div>

                        <div class="mb-4">
                            <label class="auth-label">Delivery Address</label>
                            <textarea name="address" class="form-control form-control-pill" rows="3" required 
                                      placeholder="123 Fresh Street, Green City..."><?= htmlspecialchars($pre_address) ?></textarea>
                        </div>
                    </div>

                    <div class="checkout-form-card">
                        <div class="step-header">
                            <div class="step-number">2</div>
                            <h4 class="step-title">Payment Method</h4>
                        </div>

                        <label class="payment-option-card active">
                            <input type="radio" name="payment_method" value="COD" checked class="payment-radio">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-cash-stack fs-3 text-success"></i>
                                <div>
                                    <span class="d-block fw-bold">Cash on Delivery (COD)</span>
                                    <small class="text-muted">Pay when your order arrives.</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="payment-option-card mt-3 opacity-50" style="cursor: not-allowed;">
                            <input type="radio" disabled class="payment-radio">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-credit-card fs-3"></i>
                                <div>
                                    <span class="d-block fw-bold">Credit Card / Online</span>
                                    <small class="text-muted">Coming soon to FreshCart.</small>
                                </div>
                            </div>
                        </label>

                    </div>

                </div>

                <div class="col-lg-5">
                    <div class="order-summary-card">
                        <h5 class="mb-4 font-serif">Order Summary</h5>
                        
                        <div class="mb-4" style="max-height: 300px; overflow-y: auto; padding-right: 5px;">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="checkout-item-row">
                                    <img src="assets/images/<?= $item['image'] ?: 'default.jpg' ?>" class="checkout-item-img">
                                    <div class="flex-grow-1">
                                        <h6 class="m-0 small fw-bold"><?= htmlspecialchars($item['name']) ?></h6>
                                        <small class="text-muted">Qty: <?= $item['qty'] ?></small>
                                    </div>
                                    <div class="fw-bold small">$<?= number_format($item['line_total'], 2) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex justify-content-between mb-2 small text-muted">
                            <span>Subtotal</span>
                            <span>$<?= number_format($subTotal, 2) ?></span>
                        </div>
                        
                        <?php if ($discountAmount > 0): ?>
                            <div class="d-flex justify-content-between mb-2 small text-success">
                                <span>Discount</span>
                                <span>-$<?= number_format($discountAmount, 2) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between mb-2 small text-muted">
                            <span>Shipping</span>
                            <span>Free</span>
                        </div>

                        <div class="checkout-total-row">
                            <span>Total to Pay</span>
                            <span>$<?= number_format($finalTotal, 2) ?></span>
                        </div>

                        <button type="submit" class="btn-confirm-order">
                            Confirm Order
                        </button>

                        <div class="text-center mt-3 small text-muted">
                            <i class="bi bi-lock-fill"></i> SSL Secure Payment
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>