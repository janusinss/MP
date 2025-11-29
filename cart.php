<?php
include 'db.php';
session_start();

// 1. Handle Coupon Logic
$coupon_msg = '';
$coupon_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_coupon'])) {
    $code = trim($_POST['coupon_code']);
    
    // Check DB for valid coupon
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'Active' AND expiry_date >= CURDATE()");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($coupon) {
        $_SESSION['discount'] = [
            'code' => $coupon['code'],
            'percent' => $coupon['discount_percent']
        ];
        $coupon_msg = "Coupon '{$coupon['code']}' applied!";
    } else {
        $coupon_error = "Invalid or expired coupon.";
        unset($_SESSION['discount']);
    }
}

// Remove Coupon
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['discount']);
    header("Location: cart.php");
    exit;
}

// 2. Fetch Cart Items
$cartItems = [];
$subTotal = 0;

if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    // Fetch products
    $stmt = $pdo->query("SELECT * FROM products WHERE id IN ($ids)");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        $qty = $_SESSION['cart'][$product['id']];
        $lineTotal = $product['price'] * $qty;
        
        $cartItems[] = [
            'id' => $product['id'],
            'name' => $product['name'], 
            'price' => $product['price'],
            'image' => $product['image'], // Added image to array
            'category' => $product['category'], // Added category
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
    $discountAmount = ($subTotal * $_SESSION['discount']['percent']) / 100;
    $finalTotal = $subTotal - $discountAmount;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Shopping Cart | FreshCart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="container mt-5 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="m-0">Your Shopping Bag</h2>
            <p class="text-muted m-0"><?= array_sum($_SESSION['cart'] ?? []) ?> items in your cart</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-left me-2"></i> Continue Shopping
        </a>
    </div>

    <?php if (empty($cartItems)): ?>
        
        <div class="empty-cart-state animate-fade-in">
            <i class="bi bi-basket3 empty-icon"></i>
            <h3 class="mb-3" style="font-family: var(--font-serif);">Your bag is empty</h3>
            <p class="text-muted mb-4">Looks like you haven't added any fresh goodies yet.</p>
            <a href="index.php" class="btn btn-primary btn-lg rounded-pill px-5">Start Shopping</a>
        </div>

    <?php else: ?>

        <div class="row g-5 animate-fade-in">
            
            <div class="col-lg-8">
                <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item-card">
                        
                        <div class="cart-img-wrapper">
                            <?php $img = $item['image'] ? $item['image'] : 'default.jpg'; ?>
                            <img src="assets/images/<?= $img ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        </div>

                        <div class="cart-item-details">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-light text-dark border mb-1"><?= htmlspecialchars($item['category']) ?></span>
                                    <a href="product.php?id=<?= $item['id'] ?>" class="cart-item-title d-block"><?= htmlspecialchars($item['name']) ?></a>
                                    <div class="cart-item-meta mt-1">
                                        $<?= number_format($item['price'], 2) ?> / unit
                                    </div>
                                </div>
                                
                                <form action="remove_from_cart.php" method="POST">
                                    <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn-remove border-0" title="Remove Item">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </div>

                            <div class="d-flex align-items-center border rounded-pill px-2" style="width: fit-content;">
                                <form action="update_cart.php" method="POST" class="d-flex align-items-center">
                                    <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="action" value="decrease">
                                    <button type="submit" class="btn btn-sm text-muted p-0 border-0"><i class="bi bi-dash"></i></button>
                                </form>
                                
                                <span class="mx-3 fw-bold small"><?= $item['qty'] ?></span>
                                
                                <form action="update_cart.php" method="POST" class="d-flex align-items-center">
                                    <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="action" value="increase">
                                    <button type="submit" class="btn btn-sm text-muted p-0 border-0"><i class="bi bi-plus"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="col-lg-4">
                <div class="cart-summary-card shadow-sm">
                    <h5 class="mb-4" style="font-family: var(--font-serif);">Order Summary</h5>

                    <form method="POST" class="mb-4">
                        <label class="small text-muted mb-2 text-uppercase fw-bold">Discount Code</label>
                        <div class="coupon-input-group">
                            <input type="text" name="coupon_code" class="coupon-input" placeholder="WELCOME20">
                            <button type="submit" name="apply_coupon" class="coupon-btn">Apply</button>
                        </div>
                        
                        <?php if ($coupon_msg): ?>
                            <div class="text-success small"><i class="bi bi-check-circle-fill"></i> <?= $coupon_msg ?></div>
                        <?php endif; ?>
                        <?php if ($coupon_error): ?>
                            <div class="text-danger small"><i class="bi bi-exclamation-circle-fill"></i> <?= $coupon_error ?></div>
                        <?php endif; ?>
                    </form>

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span class="fw-bold">$<?= number_format($subTotal, 2) ?></span>
                    </div>

                    <?php if (isset($_SESSION['discount'])): ?>
                        <div class="summary-row text-success">
                            <span>Discount (<?= $_SESSION['discount']['percent'] ?>%)</span>
                            <span>-$<?= number_format($discountAmount, 2) ?></span>
                        </div>
                        <div class="text-end mb-2">
                            <a href="cart.php?remove_coupon=true" class="text-danger small text-decoration-none" style="font-size: 0.8rem;">[Remove Coupon]</a>
                        </div>
                    <?php endif; ?>

                    <div class="summary-row text-muted">
                        <span>Shipping</span>
                        <span>Free</span>
                    </div>

                    <div class="summary-total">
                        <span class="fs-5 fw-bold">Total</span>
                        <span class="summary-total-price">$<?= number_format($finalTotal, 2) ?></span>
                    </div>

                    <a href="checkout.php" class="btn btn-primary w-100 py-3 mt-4 shadow rounded-pill text-uppercase" style="letter-spacing: 0.1em;">
                        Proceed to Checkout
                    </a>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted"><i class="bi bi-shield-lock-fill me-1"></i> Secure Checkout</small>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>

</body>
</html>